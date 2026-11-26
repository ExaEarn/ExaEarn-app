<?php

declare(strict_types=1);

use App\Domain\ExaPay\Offline\OfflinePaymentException;
use App\Models\User;
use App\Services\ExaPay\Offline\OfflineRedemptionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$readyFile = (string) getenv('EXAPAY_PG_WORKER_READY');
$startFile = (string) getenv('EXAPAY_PG_WORKER_START');
$userId = (int) getenv('EXAPAY_PG_WORKER_USER_ID');
$claimUuid = (string) getenv('EXAPAY_PG_WORKER_CLAIM_UUID');
$idempotencyKey = (string) getenv('EXAPAY_PG_WORKER_IDEMPOTENCY_KEY');
$mode = (string) (getenv('EXAPAY_PG_WORKER_MODE') ?: 'redeem');

if ($readyFile === '' || $startFile === '' || ($mode === 'redeem' && ($userId < 1 || $claimUuid === '' || $idempotencyKey === ''))) {
    fwrite(STDERR, "Invalid isolated PostgreSQL worker input.\n");
    exit(2);
}

file_put_contents($readyFile, 'ready', LOCK_EX);
$deadline = microtime(true) + 15;
while (!is_file($startFile) && microtime(true) < $deadline) {
    usleep(10000);
}
if (!is_file($startFile)) {
    fwrite(STDERR, "Concurrency test start barrier timed out.\n");
    exit(2);
}

if ($mode === 'deadlock_probe') {
    $firstUserId = (int) getenv('EXAPAY_PG_WORKER_FIRST_USER_ID');
    $secondUserId = (int) getenv('EXAPAY_PG_WORKER_SECOND_USER_ID');
    $workerIndex = (int) getenv('EXAPAY_PG_WORKER_INDEX');
    $acquiredFile = (string) getenv('EXAPAY_PG_WORKER_ACQUIRED');
    $peerAcquiredFile = (string) getenv('EXAPAY_PG_WORKER_PEER_ACQUIRED');
    $continueFile = (string) getenv('EXAPAY_PG_WORKER_CONTINUE');
    $runs = 0;
    try {
        DB::transaction(function () use (&$runs, $firstUserId, $secondUserId, $workerIndex, $acquiredFile, $peerAcquiredFile, $continueFile): void {
            $runs++;
            $first = User::query()->whereKey($firstUserId)->lockForUpdate()->firstOrFail();
            $first->name = 'pg-deadlock-worker-' . $workerIndex;
            $first->save();

            // The two first-row locks create an intentional opposite-order wait
            // on the second row. The retry skips this one-time barrier.
            if ($runs === 1) {
                file_put_contents($acquiredFile, 'locked', LOCK_EX);
                $deadline = microtime(true) + 15;
                while (!is_file($peerAcquiredFile) && microtime(true) < $deadline) usleep(10000);
                while (!is_file($continueFile) && microtime(true) < $deadline) usleep(10000);
                if (!is_file($peerAcquiredFile) || !is_file($continueFile)) {
                    throw new RuntimeException('Deadlock probe synchronization timed out.');
                }
            }

            $second = User::query()->whereKey($secondUserId)->lockForUpdate()->firstOrFail();
            $second->name = 'pg-deadlock-worker-' . $workerIndex;
            $second->save();
        }, 3);
        fwrite(STDOUT, json_encode(['ok' => true, 'transaction_attempts' => $runs], JSON_THROW_ON_ERROR) . PHP_EOL);
        exit(0);
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Deadlock probe failed: ' . $exception::class . PHP_EOL);
        exit(2);
    }
}

try {
    $user = User::query()->findOrFail($userId);
    $result = app(OfflineRedemptionService::class)->redeem($user, $claimUuid, $idempotencyKey);
    fwrite(STDOUT, json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR) . PHP_EOL);
    exit(0);
} catch (OfflinePaymentException $exception) {
    fwrite(STDOUT, json_encode(['ok' => false, 'error_code' => $exception->errorCode, 'status' => $exception->httpStatus], JSON_THROW_ON_ERROR) . PHP_EOL);
    exit($exception->httpStatus === 409 ? 20 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Worker failed: ' . $exception::class . PHP_EOL);
    exit(2);
}
