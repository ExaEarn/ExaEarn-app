<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\NftMediaStorageProviderInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class NftLocalMediaStorageProvider implements NftMediaStorageProviderInterface
{
    private string $disk;

    public function __construct()
    {
        $this->disk = (string) config('nft.media.disk', 'public');
    }

    public function upload(UploadedFile $file, string $key, string $visibility): array
    {
        $disk = $visibility === 'PRIVATE' ? (string) config('nft.media.private_disk', 'local') : $this->disk;
        $stored = Storage::disk($disk)->putFileAs(dirname($key), $file, basename($key));

        return [
            'provider' => (string) config('nft.media.provider', 'local'),
            'disk' => $disk,
            'storage_key' => $stored,
            'public_uri' => $visibility === 'PUBLIC' ? Storage::disk($disk)->url($stored) : null,
        ];
    }

    public function delete(string $key): bool
    {
        return Storage::disk($this->disk)->delete($key);
    }

    public function exists(string $key): bool
    {
        return Storage::disk($this->disk)->exists($key) || Storage::disk((string) config('nft.media.private_disk', 'local'))->exists($key);
    }

    public function getPublicUrl(string $key): ?string
    {
        return Storage::disk($this->disk)->exists($key) ? Storage::disk($this->disk)->url($key) : null;
    }

    public function getSignedUrl(string $key, int $ttlSeconds = 300): ?string
    {
        return $this->exists($key) ? '/api/nft/private-media/'.rawurlencode($key).'?expires='.now()->addSeconds($ttlSeconds)->timestamp : null;
    }

    public function getMetadata(string $key): array
    {
        $disk = Storage::disk($this->disk)->exists($key) ? $this->disk : (string) config('nft.media.private_disk', 'local');

        return [
            'exists' => Storage::disk($disk)->exists($key),
            'size' => Storage::disk($disk)->exists($key) ? Storage::disk($disk)->size($key) : null,
            'disk' => $disk,
        ];
    }

    public function health(): array
    {
        return ['status' => 'HEALTHY', 'provider' => (string) config('nft.media.provider', 'local'), 'mode' => (string) config('nft.media.mode', 'LOCAL_TEST')];
    }
}
