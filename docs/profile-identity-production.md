# ExaEarn Profile Identity Production Setup

The profile identity system supports initials, ExaEarn avatar library selections, and custom profile-image uploads.

## Required PHP Extension

Custom image uploads require PHP GD with JPEG, PNG and WebP support enabled. The backend fails closed when GD/WebP is unavailable so unsafe raw uploads are not stored.

Verify on a server:

```bash
php -m | grep -i gd
php -r "var_export(function_exists('imagewebp')); echo PHP_EOL; print_r(gd_info());"
```

For the local Windows PHP runtime used in development, `php_gd.dll` exists under `C:\Program Files\php-8.2\ext`, but `extension=gd` must be enabled in the loaded `php.ini` or passed at runtime:

```powershell
php -d extension=gd vendor\bin\phpunit --filter ProfileIdentityTest
php -d extension=gd artisan serve
```

The Codex sandbox could not permanently edit `C:\Program Files\php-8.2\php.ini` because Windows denied write access to Program Files. To enable it manually, run PowerShell as Administrator and change:

```ini
;extension=gd
```

to:

```ini
extension=gd
```

Then restart PHP/Laravel workers.

## Environment

```env
PROFILE_IMAGE_MAX_KB=5120
PROFILE_IMAGE_DISK=local
PROFILE_REQUIRE_REVIEW=false
```

For production object storage, configure `PROFILE_IMAGE_DISK` to the private/controlled filesystem disk used by ExaEarn. Do not point profile uploads directly at an unrestricted public web directory.

## Verification

Run:

```bash
php -d extension=gd vendor/bin/phpunit --filter ProfileIdentityTest
php artisan route:list --path=profile
```

Expected: profile identity tests include the JPG upload, square crop, WebP standard image, WebP thumbnail, storage path, avatar, privacy, and KYC-separation checks.