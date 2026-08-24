# ExaEarn Test Memory Diagnosis

## 1. PHP Binary Used

```text
C:\Program Files\php-8.2\php.exe
```

Version:

```text
PHP 8.2.30 (cli)
```

## 2. Loaded php.ini

```text
C:\Program Files\php-8.2\php.ini
```

## 3. Additional ini Files

```text
none
```

## 4. Effective Memory Before Fix

Default CLI memory limit:

```text
128M
```

Direct PHP override check:

```text
php -d memory_limit=512M -r "echo ini_get('memory_limit');"
512M
```

## 5. Exact Source of 128M Override

The previous `128M` was not caused by ExaEarn application code, PHPUnit XML, Pest, `.env.testing`, or a repository-level `ini_set()`.

The source was the Laravel `artisan test` runner path. `php -d memory_limit=512M artisan test` starts Artisan with `512M`, but Laravel's test runner invokes PHPUnit in a child PHP process that did not preserve the parent `-d memory_limit=512M` flag. The child process loaded the CLI default from `C:\Program Files\php-8.2\php.ini`, returning to `128M`.

Direct PHPUnit execution preserved the override:

```text
php -d memory_limit=512M vendor/bin/phpunit --filter=MemoryLimitDiagnosticTest
DIAG_MEMORY_LIMIT=512M
```

## 6. Fix Applied

Added a test-only Composer script in `backend/api-gateway/composer.json`:

```json
"test:full": [
  "@php -d memory_limit=512M -d extension=gd -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit"
]
```

This bypasses the Artisan test child-process memory propagation issue and runs PHPUnit directly with the required test-only memory limit.

## 7. Whether a Genuine Memory Leak Existed

No functional memory leak was identified during the verified full run. The complete backend suite finished with PHPUnit-reported memory usage:

```text
156.00 MB
```

That is above the default `128M` CLI limit but comfortably below the test-only `512M` limit.

## 8. Memory-Retention Fixes

No production memory-retention changes were required.

Phase 12 load tests already avoid retaining thousands of queued job instances in the PHPUnit process by using a count-only fanout path for local load validation while preserving dispatch as the production default.

## 9. Final Test Command

```text
php -d memory_limit=512M vendor/bin/phpunit
```

or:

```text
composer test:full
```

from `backend/api-gateway`.

## 10. Final Effective Memory Limit

```text
512M
```

## 11. Full-Suite Results

```text
Tests: 337
Assertions: 1406
Failed: 0
Skipped: 1
Runtime: 03:24.986
Memory: 156.00 MB
```

## 12. Peak Memory

PHPUnit reported:

```text
156.00 MB
```

## 13. Production Runtime Settings Changed

```text
NO
```

No PHP-FPM, web-worker, queue-worker, or global system memory limit was changed.
