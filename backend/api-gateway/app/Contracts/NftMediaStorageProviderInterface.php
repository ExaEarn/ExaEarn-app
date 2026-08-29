<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface NftMediaStorageProviderInterface
{
    public function upload(UploadedFile $file, string $key, string $visibility): array;
    public function delete(string $key): bool;
    public function exists(string $key): bool;
    public function getPublicUrl(string $key): ?string;
    public function getSignedUrl(string $key, int $ttlSeconds = 300): ?string;
    public function getMetadata(string $key): array;
    public function health(): array;
}
