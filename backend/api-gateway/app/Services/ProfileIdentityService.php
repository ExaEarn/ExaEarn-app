<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProfileIdentityService
{
    public function avatarCatalog(): array
    {
        $avatars = collect(config('profile.avatars', []));

        return $avatars
            ->groupBy('category')
            ->map(fn ($items, $category) => [
                'category' => $category,
                'avatars' => $items->values()->all(),
            ])
            ->values()
            ->all();
    }

    public function publicAvatar(?string $avatarId): ?array
    {
        if (!$avatarId) {
            return null;
        }

        foreach (config('profile.avatars', []) as $avatar) {
            if (($avatar['id'] ?? null) === $avatarId) {
                return $avatar;
            }
        }

        return null;
    }

    public function identityFor(User $user, string $context = 'self'): array
    {
        $displayName = trim((string) $user->name) ?: 'ExaEarn User';
        $displayType = in_array($user->profile_display_type, config('profile.display_types', []), true)
            ? (string) $user->profile_display_type
            : 'initials';
        $visibility = in_array($user->profile_visibility, config('profile.visibility', []), true)
            ? (string) $user->profile_visibility
            : 'self';
        $canShowCustom = $displayType === 'custom_image'
            && $user->profile_image_status === 'approved'
            && $user->profile_thumbnail_url
            && $this->isVisibleInContext($visibility, $context);

        $avatar = $this->publicAvatar($user->avatar_id);
        $imageUrl = $canShowCustom ? route('profile.image', ['user' => $user->id, 'variant' => 'standard'], false) : null;
        $thumbnailUrl = $canShowCustom ? route('profile.image', ['user' => $user->id, 'variant' => 'thumbnail'], false) : null;

        return [
            'display_type' => $canShowCustom ? 'custom_image' : ($avatar ? 'avatar' : 'initials'),
            'initials' => $this->initials($displayName),
            'gradient' => $this->gradientFor((string) $user->id),
            'avatar' => $avatar,
            'image_url' => $imageUrl,
            'thumbnail_url' => $thumbnailUrl,
            'visibility' => $visibility,
            'image_status' => $user->profile_image_status ?: 'none',
            'image_updated_at' => optional($user->profile_image_updated_at)->toIso8601String(),
        ];
    }

    public function selectAvatar(User $user, string $avatarId, string $visibility): User
    {
        if (!$this->publicAvatar($avatarId)) {
            throw new RuntimeException('Selected avatar is not available.');
        }

        return DB::transaction(function () use ($user, $avatarId, $visibility): User {
            $user->forceFill([
                'avatar_id' => $avatarId,
                'profile_display_type' => 'avatar',
                'profile_visibility' => $this->sanitizeVisibility($visibility),
            ])->save();

            AuditService::log($user->id, 'profile', 'avatar_selected', ['avatar_id' => $avatarId]);

            return $user->fresh();
        });
    }

    public function useInitials(User $user, string $visibility): User
    {
        return DB::transaction(function () use ($user, $visibility): User {
            $user->forceFill([
                'profile_display_type' => 'initials',
                'profile_visibility' => $this->sanitizeVisibility($visibility),
            ])->save();

            AuditService::log($user->id, 'profile', 'initials_selected');

            return $user->fresh();
        });
    }

    public function updateVisibility(User $user, string $visibility): User
    {
        $user->forceFill(['profile_visibility' => $this->sanitizeVisibility($visibility)])->save();
        AuditService::log($user->id, 'profile', 'visibility_updated', ['visibility' => $visibility]);

        return $user->fresh();
    }

    public function removeCustomImage(User $user, ?int $adminId = null, string $reason = 'user_removed'): User
    {
        return DB::transaction(function () use ($user, $adminId, $reason): User {
            $old = [$user->profile_image_url, $user->profile_thumbnail_url];
            $user->forceFill([
                'profile_image_url' => null,
                'profile_thumbnail_url' => null,
                'profile_display_type' => $user->avatar_id ? 'avatar' : 'initials',
                'profile_image_status' => 'removed',
                'profile_image_updated_at' => now(),
                'profile_image_moderation_note' => $adminId ? $reason : null,
            ])->save();

            foreach ($old as $path) {
                if ($path) {
                    Storage::disk((string) config('profile.image_disk', 'local'))->delete($path);
                }
            }

            AuditService::log($user->id, 'profile', 'custom_image_removed', ['reason' => $reason, 'admin_id' => $adminId]);

            return $user->fresh();
        });
    }

    public function suspendCustomImages(User $user, int $days, string $reason, ?int $adminId = null): User
    {
        $user->forceFill([
            'profile_image_privileges_suspended_until' => now()->addDays(max(1, $days)),
            'profile_image_moderation_note' => $reason,
        ])->save();

        AuditService::log($user->id, 'profile', 'custom_image_privileges_suspended', [
            'days' => $days,
            'reason' => $reason,
            'admin_id' => $adminId,
        ]);

        return $user->fresh();
    }

    public function uploadImage(User $user, UploadedFile $file, array $crop = [], string $visibility = 'self'): User
    {
        if ($user->profile_image_privileges_suspended_until && $user->profile_image_privileges_suspended_until->isFuture()) {
            throw new RuntimeException('Custom profile images are temporarily unavailable for this account.');
        }

        $this->assertSafeImage($file);

        if (!\function_exists('imagewebp')) {
            throw new RuntimeException('Image processing is unavailable on this server. Enable the PHP GD extension with WebP support.');
        }

        return DB::transaction(function () use ($user, $file, $crop, $visibility): User {
            $source = $this->createImageResource($file);
            [$sourceWidth, $sourceHeight] = getimagesize($file->getRealPath());
            $square = $this->cropSquare($source, $sourceWidth, $sourceHeight, $crop);

            $directory = trim((string) config('profile.image_directory', 'private/profile-images'), '/') . '/' . $user->id;
            $basename = (string) Str::uuid();
            $standardPath = $directory . '/' . $basename . '.webp';
            $thumbPath = $directory . '/' . $basename . '_thumb.webp';

            $this->storeWebp($square, $standardPath, (int) config('profile.standard_size', 512));
            $this->storeWebp($square, $thumbPath, (int) config('profile.thumbnail_size', 128));

            imagedestroy($source);
            imagedestroy($square);

            $old = [$user->profile_image_url, $user->profile_thumbnail_url];
            $user->forceFill([
                'profile_image_url' => $standardPath,
                'profile_thumbnail_url' => $thumbPath,
                'profile_display_type' => 'custom_image',
                'profile_visibility' => $this->sanitizeVisibility($visibility),
                'profile_image_status' => config('profile.require_review', false) ? 'pending_review' : 'approved',
                'profile_image_updated_at' => now(),
                'profile_image_moderation_note' => null,
            ])->save();

            foreach ($old as $path) {
                if ($path) {
                    Storage::disk((string) config('profile.image_disk', 'local'))->delete($path);
                }
            }

            AuditService::log($user->id, 'profile', 'custom_image_uploaded', [
                'status' => $user->profile_image_status,
                'mime' => $file->getMimeType(),
            ]);

            return $user->fresh();
        });
    }

    public function imageResponse(User $requestingUser, User $owner, string $variant)
    {
        $path = $variant === 'thumbnail' ? $owner->profile_thumbnail_url : $owner->profile_image_url;
        if (!$path || $owner->profile_image_status !== 'approved') {
            abort(404);
        }

        if ($requestingUser->id !== $owner->id && !$this->isVisibleInContext((string) $owner->profile_visibility, 'public')) {
            abort(403);
        }

        $disk = Storage::disk((string) config('profile.image_disk', 'local'));
        if (!$disk->exists($path)) {
            abort(404);
        }

        return response($disk->get($path), 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function isVisibleInContext(string $visibility, string $context): bool
    {
        if ($context === 'self') {
            return true;
        }
        if ($context === 'p2p') {
            return in_array($visibility, ['p2p', 'public'], true);
        }

        return $visibility === 'public';
    }

    private function sanitizeVisibility(string $visibility): string
    {
        if (!in_array($visibility, config('profile.visibility', []), true)) {
            throw new RuntimeException('Invalid profile visibility setting.');
        }

        return $visibility;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = array_map(fn ($part) => mb_strtoupper(mb_substr((string) $part, 0, 1)), array_slice(array_filter($parts), 0, 2));

        return implode('', $letters) ?: 'EX';
    }

    private function gradientFor(string $seed): string
    {
        $palette = [
            ['#2b1805', '#d4af37'],
            ['#111827', '#7c3aed'],
            ['#0f172a', '#0ea5e9'],
            ['#1f1307', '#f59e0b'],
            ['#07110f', '#10b981'],
            ['#160b1f', '#e879f9'],
        ];
        $index = abs(crc32($seed)) % count($palette);

        return 'linear-gradient(135deg,' . $palette[$index][0] . ',' . $palette[$index][1] . ')';
    }

    private function assertSafeImage(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new RuntimeException('The uploaded image could not be read.');
        }

        $maxKb = (int) config('profile.image_max_kb', 5120);
        if ($file->getSize() > $maxKb * 1024) {
            throw new RuntimeException('Profile image must be 5 MB or smaller.');
        }

        $path = $file->getRealPath();
        $imageInfo = @getimagesize($path);
        $mime = (string) ($imageInfo['mime'] ?? $file->getMimeType());
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];

        if (!$imageInfo || !in_array($mime, $allowed, true)) {
            throw new RuntimeException('Upload a valid JPG, PNG or WebP image.');
        }
    }

    private function createImageResource(UploadedFile $file)
    {
        $mime = (string) (getimagesize($file->getRealPath())['mime'] ?? '');

        return match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($file->getRealPath()),
            'image/png' => imagecreatefrompng($file->getRealPath()),
            'image/webp' => imagecreatefromwebp($file->getRealPath()),
            default => false,
        } ?: throw new RuntimeException('The uploaded image is malformed.');
    }

    private function cropSquare($source, int $width, int $height, array $crop)
    {
        $size = max(1, min($width, $height));
        $x = isset($crop['x']) ? max(0, min($width - $size, (int) $crop['x'])) : (int) floor(($width - $size) / 2);
        $y = isset($crop['y']) ? max(0, min($height - $size, (int) $crop['y'])) : (int) floor(($height - $size) / 2);
        $square = imagecreatetruecolor($size, $size);
        imagealphablending($square, false);
        imagesavealpha($square, true);
        imagecopyresampled($square, $source, 0, 0, $x, $y, $size, $size, $size, $size);

        return $square;
    }

    private function storeWebp($source, string $path, int $size): void
    {
        $target = imagecreatetruecolor($size, $size);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $size, $size, imagesx($source), imagesy($source));

        ob_start();
        imagewebp($target, null, (int) config('profile.webp_quality', 86));
        $bytes = ob_get_clean();
        imagedestroy($target);

        if (!$bytes) {
            throw new RuntimeException('Unable to optimize the profile image.');
        }

        Storage::disk((string) config('profile.image_disk', 'local'))->put($path, $bytes);
    }
}