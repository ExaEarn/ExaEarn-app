<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\MalwareScannerInterface;
use App\Jobs\ScanListingDocument;
use App\Models\ListingApplication;
use App\Models\ListingDocument;
use App\Models\ListingOrganization;
use App\Models\ListingOrganizationDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ListingDocumentSecurityService
{
    public const SAFE_STATUS = 'CLEAN';

    public function __construct(private readonly MalwareScannerInterface $scanner) {}

    public function storeQuarantined(UploadedFile $file, string $domain, string $ownerUuid, string $documentUuid): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, (array) config('listing.documents.allowed_extensions', []), true)) throw new RuntimeException('Unsupported evidence file type.');
        $original = $file->getClientOriginalName();
        if (str_contains($original, "\r") || str_contains($original, "\n") || str_contains($original, "\0") || str_contains($original, '..')) throw new RuntimeException('Unsafe evidence filename.');
        if (! $file->isValid() || $file->getSize() < 1) throw new RuntimeException('Evidence file is empty or invalid.');
        $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        if (! in_array($mime, (array) config('listing.documents.allowed_mime_types', []), true)) throw new RuntimeException('Evidence content type is not allowed.');
        $checksum = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs("{$domain}/{$ownerUuid}", $documentUuid.'.'.$extension, 'listing_quarantine');
        if (! $path) throw new RuntimeException('Secure quarantine storage failed.');
        return ['storage_disk' => 'listing_quarantine', 'storage_path' => $path, 'checksum_sha256' => $checksum, 'mime_type' => $mime, 'size_bytes' => $file->getSize()];
    }

    public function queue(string $domain, Model $document): void
    {
        ScanListingDocument::dispatch($domain, (int) $document->id, (string) $document->checksum_sha256)->afterCommit();
    }

    public function scan(string $domain, int $documentId, string $expectedChecksum): void
    {
        $document = $this->document($domain, $documentId);
        if ((string) $document->checksum_sha256 !== $expectedChecksum) throw new RuntimeException('Document checksum changed before scanning.');
        if ($document->scan_status === 'CLEAN' && $document->clean_at && (string) $document->checksum_sha256 === $expectedChecksum) return;
        $disk = Storage::disk((string) $document->storage_disk);
        if (! $disk->exists((string) $document->storage_path)) throw new RuntimeException('Quarantined document is unavailable.');
        $actualChecksum = hash('sha256', $disk->get((string) $document->storage_path));
        if (! hash_equals($expectedChecksum, $actualChecksum)) {
            $document->update(['scan_status' => 'SUSPICIOUS', 'clean_at' => null]);
            throw new RuntimeException('Quarantined document checksum mismatch.');
        }

        $correlation = (string) Str::uuid(); $started = now();
        $document->update(['scan_status' => 'SCANNING', 'clean_at' => null]);
        try {
            $result = $this->scanner->supports((string) $document->mime_type)
                ? $this->scanner->scan($disk->path((string) $document->storage_path), $expectedChecksum, (string) $document->mime_type)
                : ['result' => ($this->scanner->health()['status'] ?? null) === 'CONNECTED' ? 'ERROR' : 'NOT_CONNECTED', 'reason_code' => 'SCANNER_UNAVAILABLE_OR_UNSUPPORTED'];
        } catch (Throwable $e) {
            $result = ['result' => 'SCAN_ERROR', 'reason_code' => $e::class];
        }
        $normalized = strtoupper((string) ($result['result'] ?? 'SCAN_ERROR'));
        if (! in_array($normalized, ['CLEAN', 'INFECTED', 'SUSPICIOUS', 'SCAN_ERROR', 'TIMEOUT', 'NOT_CONNECTED'], true)) $normalized = 'SUSPICIOUS';
        DB::table('listing_document_scan_attempts')->insert([
            'attempt_uuid' => (string) Str::uuid(), 'document_domain' => $domain, 'document_id' => $document->id,
            'document_version' => $document->version, 'checksum_sha256' => $expectedChecksum,
            'provider' => $this->scanner->providerName(), 'scan_reference' => $result['scan_reference'] ?? null,
            'result' => $normalized, 'reason_code' => $result['reason_code'] ?? null, 'engine_version' => $result['engine_version'] ?? null,
            'correlation_id' => $correlation, 'started_at' => $started, 'completed_at' => now(),
            'metadata' => json_encode(['mime_type' => $document->mime_type], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($normalized === 'CLEAN') {
            $approvedPath = "listing-approved/{$domain}/{$document->document_uuid}";
            Storage::disk('local')->put($approvedPath, $disk->get((string) $document->storage_path));
            if (! Storage::disk('local')->exists($approvedPath)) throw new RuntimeException('Clean-document promotion failed.');
            $disk->delete((string) $document->storage_path);
            $document->update(['storage_disk' => 'local', 'storage_path' => $approvedPath, 'scan_status' => 'CLEAN', 'clean_at' => now(), 'scan_provider' => $this->scanner->providerName(), 'scan_reference' => $result['scan_reference'] ?? null]);
            return;
        }
        $document->update(['scan_status' => $normalized === 'TIMEOUT' ? 'SCAN_ERROR' : $normalized, 'clean_at' => null, 'scan_provider' => $this->scanner->providerName(), 'scan_reference' => $result['scan_reference'] ?? null]);
    }

    public function assertDownloadable(Model $document): void
    {
        if (! $this->isClean($document)) throw new RuntimeException('Document is quarantined until its security check passes.');
        $disk = Storage::disk((string) $document->storage_disk);
        if (! $disk->exists((string) $document->storage_path) || ! hash_equals((string) $document->checksum_sha256, hash('sha256', $disk->get((string) $document->storage_path)))) throw new RuntimeException('Document integrity verification failed.');
    }

    public function assertKybDocumentsClean(ListingOrganization $organization): void
    {
        $current = $organization->kybDocuments()->orderByDesc('version')->get()->unique('document_type');
        foreach ((array) config('listing.required_kyb_documents', []) as $type) {
            $document = $current->firstWhere('document_type', $type);
            if (! $document || ! $this->isClean($document)) throw new RuntimeException("KYB approval blocked: current {$type} document is not malware-cleared.");
        }
    }

    public function assertListingDocumentsClean(ListingApplication $application): void
    {
        $current = $application->documents()->orderByDesc('version')->get()->unique('document_type');
        foreach ($current as $document) if (! $this->isClean($document)) throw new RuntimeException("Listing approval blocked: current {$document->document_type} document is not malware-cleared.");
        foreach ((array) config('listing.required_listing_documents', []) as $type) if (! $current->firstWhere('document_type', $type)) throw new RuntimeException("Listing approval blocked: required {$type} document is missing.");
    }

    public function health(): array
    {
        return ['provider' => $this->scanner->providerName(), 'scanner' => $this->scanner->health(), 'queued' => ListingDocument::query()->whereIn('scan_status', ['QUARANTINED', 'SCAN_QUEUED', 'SCANNING'])->count() + ListingOrganizationDocument::query()->whereIn('scan_status', ['QUARANTINED', 'SCAN_QUEUED', 'SCANNING'])->count(), 'infected' => ListingDocument::query()->where('scan_status', 'INFECTED')->count() + ListingOrganizationDocument::query()->where('scan_status', 'INFECTED')->count()];
    }

    private function document(string $domain, int $id): Model
    {
        return match ($domain) { 'listing' => ListingDocument::query()->findOrFail($id), 'kyb' => ListingOrganizationDocument::query()->findOrFail($id), default => throw new RuntimeException('Unsupported document domain.') };
    }

    private function isClean(Model $document): bool
    {
        if ($document->scan_status !== self::SAFE_STATUS || ! $document->clean_at) return false;
        $domain = $document instanceof ListingDocument ? 'listing' : 'kyb';
        return DB::table('listing_document_scan_attempts')
            ->where('document_domain', $domain)->where('document_id', $document->id)
            ->where('document_version', $document->version)->where('checksum_sha256', $document->checksum_sha256)
            ->where('result', 'CLEAN')->exists();
    }
}
