<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\NftMediaStorageProviderInterface;
use App\Models\Nft;
use App\Models\Admin;
use App\Models\NftMediaAsset;
use App\Models\NftReconciliationBreak;
use App\Models\NftReport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class NftMediaService
{
    public function __construct(private readonly NftMediaStorageProviderInterface $storage)
    {
    }

    public function upload(User $user, UploadedFile $file, array $payload): NftMediaAsset
    {
        $this->assertProviderAvailable();
        $mediaType = strtoupper((string) $payload['media_type']);
        $visibility = strtoupper((string) ($payload['visibility'] ?? 'PUBLIC'));
        $this->validateUpload($file, $mediaType, $visibility);

        $checksum = hash_file('sha256', $file->getRealPath());
        $duplicate = NftMediaAsset::query()->where('checksum', $checksum)->where('status', 'READY')->first();
        if ($duplicate && (bool) config('nft.media.reuse_duplicates', true)) {
            return $duplicate;
        }

        return DB::transaction(function () use ($user, $file, $payload, $mediaType, $visibility, $checksum): NftMediaAsset {
            $safe = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.strtolower($file->getClientOriginalExtension());
            $key = implode('/', ['nft', strtolower($visibility), strtolower($mediaType), $checksum.'-'.$safe]);
            $stored = $this->storage->upload($file, $key, $visibility);
            $metadata = $this->metadataPayload($payload, $stored['public_uri'] ?? null, $checksum);

            $asset = NftMediaAsset::query()->create([
                'owner_user_id' => $user->id,
                'nft_id' => $payload['nft_id'] ?? null,
                'collection_id' => $payload['collection_id'] ?? null,
                'media_type' => $mediaType,
                'visibility' => $visibility,
                'storage_provider' => $stored['provider'],
                'storage_key' => $stored['storage_key'],
                'original_filename' => $file->getClientOriginalName(),
                'safe_filename' => $safe,
                'mime_type' => (string) $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'checksum' => $checksum,
                'content_hash' => $checksum,
                'status' => 'READY',
                'processing_status' => $this->processingStateFor($mediaType),
                'public_uri' => $stored['public_uri'] ?? null,
                'metadata_uri' => 'exaearn://nft/media/'.$checksum,
                'metadata' => $metadata,
                'metadata_hash' => hash('sha256', json_encode($metadata, JSON_THROW_ON_ERROR)),
                'metadata_version' => 1,
                'created_by_user_id' => $user->id,
            ]);

            if ($asset->nft_id && in_array($mediaType, ['IMAGE', 'THUMBNAIL', 'ANIMATION', 'VIDEO', 'AUDIO'], true)) {
                Nft::query()->whereKey($asset->nft_id)->update([
                    'media_url' => $asset->public_uri,
                    'metadata_hash' => $asset->metadata_hash,
                    'metadata_url' => $asset->metadata_uri,
                ]);
            }

            return $asset;
        });
    }

    public function uploadEvidence(User $user, NftReport $report, UploadedFile $file): NftMediaAsset
    {
        $asset = $this->upload($user, $file, [
            'nft_id' => $report->nft_id,
            'media_type' => 'DOCUMENT',
            'visibility' => 'PRIVATE',
            'name' => 'NFT report evidence',
        ]);
        $report->update(['evidence' => array_merge($report->evidence ?? [], ['media_asset_id' => $asset->id])]);

        return $asset;
    }

    public function adminTransition(NftMediaAsset $asset, string $status, User|Admin $actor, ?string $reason = null): NftMediaAsset
    {
        $status = strtoupper($status);
        if (! in_array($status, ['APPROVED', 'RESTRICTED', 'QUARANTINED', 'REMOVED', 'READY'], true)) {
            throw new RuntimeException('Unsupported NFT media moderation action.');
        }
        if ($asset->immutable_finalized_at && in_array($status, ['REMOVED'], true)) {
            throw new RuntimeException('Immutable finalized NFT media cannot be silently removed.');
        }

        $asset->update([
            'status' => $status === 'APPROVED' ? 'READY' : $status,
            'processing_status' => $status === 'QUARANTINED' ? 'QUARANTINED' : $asset->processing_status,
            'metadata' => array_merge($asset->metadata ?? [], ['last_admin_action' => ['actor_id' => $actor->id, 'status' => $status, 'reason' => $reason, 'at' => now()->toISOString()]]),
        ]);

        if (in_array($status, ['RESTRICTED', 'QUARANTINED', 'REMOVED'], true) && $asset->nft_id) {
            Nft::query()->whereKey($asset->nft_id)->update(['moderation_status' => $status]);
        }

        return $asset->fresh();
    }

    public function privateUrl(User $user, NftMediaAsset $asset): string
    {
        if ($asset->visibility !== 'PRIVATE' && $asset->public_uri) {
            return $asset->public_uri;
        }
        if ((int) $asset->owner_user_id !== (int) $user->id) {
            throw new RuntimeException('NFT media access denied.');
        }
        return $this->storage->getSignedUrl($asset->storage_key) ?? throw new RuntimeException('NFT private media is unavailable.');
    }

    public function health(): array
    {
        return $this->storage->health();
    }

    public function reconciliation(): array
    {
        $findings = [];
        NftMediaAsset::query()->whereIn('status', ['READY', 'PROCESSING'])->chunk(100, function ($assets) use (&$findings): void {
            foreach ($assets as $asset) {
                if (! $this->storage->exists($asset->storage_key)) {
                    $findings[] = ['type' => 'media_missing_object', 'media_asset_id' => $asset->id, 'severity' => 'high'];
                }
                if ($asset->visibility === 'PRIVATE' && $asset->public_uri) {
                    $findings[] = ['type' => 'private_media_public_uri', 'media_asset_id' => $asset->id, 'severity' => 'critical'];
                }
                if ($asset->nft_id && $asset->status !== 'READY') {
                    $findings[] = ['type' => 'nft_references_unready_media', 'media_asset_id' => $asset->id, 'severity' => 'medium'];
                }
            }
        });

        foreach ($findings as $finding) {
            NftReconciliationBreak::query()->firstOrCreate(
                ['break_type' => $finding['type'], 'status' => 'OPEN'],
                ['nft_id' => NftMediaAsset::query()->find($finding['media_asset_id'])?->nft_id, 'severity' => $finding['severity'], 'evidence' => $finding]
            );
        }

        return ['status' => collect($findings)->contains(fn ($f) => in_array($f['severity'], ['high', 'critical'], true)) ? 'FAIL' : 'PASS', 'findings' => $findings];
    }

    private function assertProviderAvailable(): void
    {
        if ((string) config('nft.media.mode') === 'PRODUCTION' && ! (bool) config('nft.media.production_configured')) {
            throw new RuntimeException('MEDIA_PROVIDER_UNAVAILABLE');
        }
    }

    private function validateUpload(UploadedFile $file, string $mediaType, string $visibility): void
    {
        if (! in_array($mediaType, config('nft.media.supported_types', []), true)) {
            throw new RuntimeException('Unsupported NFT media type.');
        }
        if (! in_array($visibility, ['PUBLIC', 'PRIVATE'], true)) {
            throw new RuntimeException('Unsupported NFT media visibility.');
        }
        $mime = (string) $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($mime, config('nft.media.allowed_mimes', []), true) || ! in_array($extension, config('nft.media.allowed_extensions', []), true)) {
            throw new RuntimeException('Unsupported NFT media MIME type.');
        }
        if (in_array($extension, ['php', 'phtml', 'exe', 'bat', 'cmd', 'js', 'html', 'svg'], true)) {
            throw new RuntimeException('Executable or script media is not allowed.');
        }
        $limit = (int) data_get(config('nft.media.size_limits'), strtolower($mediaType), config('nft.media.max_size_bytes', 20971520));
        if ((int) $file->getSize() > $limit) {
            throw new RuntimeException('NFT media file is too large.');
        }
        if (str_starts_with($mime, 'image/') && ! @getimagesize($file->getRealPath())) {
            throw new RuntimeException('Malformed image upload.');
        }
    }

    private function metadataPayload(array $payload, ?string $publicUri, string $checksum): array
    {
        return [
            'name' => $payload['name'] ?? 'ExaEarn NFT Media',
            'description' => $payload['description'] ?? null,
            'image' => $publicUri,
            'animation_url' => $payload['animation_url'] ?? null,
            'external_url' => $payload['external_url'] ?? null,
            'attributes' => $payload['attributes'] ?? [],
            'media_hash' => $checksum,
        ];
    }

    private function processingStateFor(string $mediaType): string
    {
        return in_array($mediaType, ['VIDEO', 'AUDIO', 'ANIMATION'], true) ? 'PROCESSING' : 'READY';
    }
}
