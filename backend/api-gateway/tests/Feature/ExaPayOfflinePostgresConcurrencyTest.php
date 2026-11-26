<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\ExaPay\Offline\OfflineIssuanceContract;
use App\Domain\ExaPay\Offline\OfflinePaymentMode;
use App\Domain\ExaPay\Offline\OfflineRiskPolicyContract;
use App\Models\Account;
use App\Models\ExaPayOfflineInstrument;
use App\Models\ExaPayOfflineReconciliationClaim;
use App\Models\ExaPayOfflineRedemption;
use App\Models\ExaPayOfflineReserve;
use App\Models\ExaPayOfflineRiskPolicy;
use App\Models\ExaPayOfflineSecurityControl;
use App\Models\ExaPayOfflineValueOutput;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\ExaPay\Offline\OfflineInstrumentCrypto;
use App\Services\ExaPay\Offline\OfflineReconciliationClaimService;
use App\Services\ExaPay\Offline\OfflineStoredValueService;
use App\Services\FinancialDecimal;
use App\Services\LedgerService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Mockery;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Runs only against a dedicated disposable local PostgreSQL database whose name begins
 * with exaearn_sim_phase3_concurrency_. It intentionally skips SQLite: SQLite cannot verify
 * PostgreSQL row locking or cross-process redemption behavior.
 */
#[Group('postgres-integration')]
final class ExaPayOfflinePostgresConcurrencyTest extends TestCase
{
    public function test_cross_process_redemption_is_exactly_once_and_conflicting_claims_cannot_double_consume(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL cross-process verification requires DB_CONNECTION=pgsql.');
        }

        $database = (string) config('database.connections.pgsql.database');
        $host = strtolower((string) config('database.connections.pgsql.host'));
        if (!str_starts_with($database, 'exaearn_sim_phase3_concurrency_')) {
            $this->markTestSkipped('Set DB_DATABASE to a dedicated exaearn_sim_phase3_concurrency_* database to run the destructive concurrency harness.');
        }
        $this->assertContains($host, ['127.0.0.1', 'localhost', '::1'], 'PostgreSQL integration tests must use loopback only.');

        // Migrate only this isolated database, truncate its fixture rows, then
        // round-trip the Phase 4 controls, RBAC permissions, realtime counters and Phase 3 reconciliation migrations.
        Artisan::call('migrate', ['--force' => true]);
        $this->truncateIsolatedDatabase();
        Artisan::call('migrate:rollback', ['--step' => 4, '--force' => true]);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('exapay_offline_reconciliation_events'));
        Artisan::call('migrate', ['--force' => true]);
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('exapay_offline_reconciliation_events'));
        config([
            'app.env' => 'testing', 'exapay_offline.enabled' => true,
            'exapay_offline.redemption_enabled' => true,
            'exapay_offline.allowed_environments' => ['testing', 'staging'],
            'exapay_offline.issuer_private_key_passphrase' => '',
        ]);
        $this->ensureRiskPolicyExists();
        $issuerPrivateKey = $this->newPrivateKey();
        config(['exapay_offline.issuer_private_key' => $issuerPrivateKey]);
        $this->enableSignedPolicy($issuerPrivateKey);

        $user = User::factory()->create();
        $this->seedBalance($user, 3000);
        [$deviceUuid, $devicePrivateKey] = $this->enrollDevice($user, $issuerPrivateKey);
        $first = $this->createVerifiedRootClaim($user, $deviceUuid, $devicePrivateKey, 1000, 'pg-race-first');
        $gate = $this->newStartGate();
        try {
            $sameKey = $this->runConcurrentWorkers($gate, $user, $first['claim_uuid'], 'pg-redemption-same-key-0001');
            $this->assertCount(2, $sameKey);
            $this->assertSame([0, 0], $sameKey['exit_codes']);
            $this->assertSame(1, count(array_filter($sameKey['results'], fn (array $result): bool => (bool) ($result['result']['idempotent_replay'] ?? false))));
            $this->assertSame(1, ExaPayOfflineRedemption::query()->where('output_uuid', $first['output_uuid'])->count());
            $this->assertSame(3000, $this->minor(app(LedgerService::class)->getBalance((int) $user->id, 'SIMUSDT', 'funding')));
            $this->assertAccountingIsConserved();

            // A second output races with distinct idempotency keys: one redemption wins;
            // the other must observe the unique consumed output and return a conflict.
            $second = $this->createVerifiedRootClaim($user, $deviceUuid, $devicePrivateKey, 1000, 'pg-race-second');
            $differentKeys = $this->runConcurrentWorkers($gate, $user, $second['claim_uuid'], null);
            sort($differentKeys['exit_codes']);
            $this->assertSame([0, 20], $differentKeys['exit_codes']);
            $this->assertSame(['offline_output_already_redeemed'], array_values(array_filter(
                array_column($differentKeys['results'], 'error_code'), fn ($code): bool => is_string($code),
            )));
            $this->assertSame(1, ExaPayOfflineRedemption::query()->where('output_uuid', $second['output_uuid'])->count());
            $this->assertSame(3000, $this->minor(app(LedgerService::class)->getBalance((int) $user->id, 'SIMUSDT', 'funding')));
            $this->assertSame(2, LedgerTransaction::query()->where('transaction_type', 'exapay_offline_redemption')->count());
            $this->assertSame(4, LedgerEntry::query()->whereIn('reference', LedgerTransaction::query()
                ->where('transaction_type', 'exapay_offline_redemption')->select('reference'))->count());
            $this->assertAccountingIsConserved();

            $rollbackClaim = $this->createVerifiedRootClaim($user, $deviceUuid, $devicePrivateKey, 1000, 'pg-rollback');
            $rollbackOutput = ExaPayOfflineValueOutput::query()->where('output_uuid', $rollbackClaim['output_uuid'])->firstOrFail();
            $rollbackLiability = (int) ExaPayOfflineReserve::query()->value('outstanding_liability_minor');
            $rollbackBalance = $this->minor(app(LedgerService::class)->getBalance((int) $user->id, 'SIMUSDT', 'funding'));
            $realLedger = app(LedgerService::class);
            $failingLedger = Mockery::mock($realLedger)->makePartial();
            $failingLedger->shouldReceive('postDoubleEntry')->once()->andThrow(new RuntimeException('injected PostgreSQL rollback failure'));
            $this->app->instance(LedgerService::class, $failingLedger);
            try {
                app(\App\Services\ExaPay\Offline\OfflineRedemptionService::class)
                    ->redeem($user, $rollbackClaim['claim_uuid'], 'pg-redemption-rollback-idempotency-0001');
                $this->fail('Injected ledger failure must abort redemption atomically in PostgreSQL.');
            } catch (RuntimeException $exception) {
                $this->assertSame('injected PostgreSQL rollback failure', $exception->getMessage());
            } finally {
                $this->app->instance(LedgerService::class, $realLedger);
            }
            $this->assertSame('VERIFIED', ExaPayOfflineReconciliationClaim::query()->where('claim_uuid', $rollbackClaim['claim_uuid'])->value('status'));
            $this->assertSame('available', $rollbackOutput->fresh()->status);
            $this->assertSame($rollbackLiability, (int) ExaPayOfflineReserve::query()->value('outstanding_liability_minor'));
            $this->assertSame($rollbackBalance, $this->minor(app(LedgerService::class)->getBalance((int) $user->id, 'SIMUSDT', 'funding')));
            $this->assertSame(0, ExaPayOfflineRedemption::query()->where('output_uuid', $rollbackOutput->output_uuid)->count());
            $this->assertSame(2, LedgerTransaction::query()->where('transaction_type', 'exapay_offline_redemption')->count());
            $this->assertAccountingIsConserved();

            $secondUser = User::factory()->create();
            $deadlock = $this->runConcurrentDeadlockWorkers($user, $secondUser);
            $this->assertSame([0, 0], $deadlock['exit_codes']);
            $this->assertGreaterThanOrEqual(2, max(array_column($deadlock['results'], 'transaction_attempts')),
                'PostgreSQL should detect one intentional deadlock and Laravel should retry the aborted transaction.');
            $finalNames = User::query()->whereIn('id', [$user->id, $secondUser->id])->orderBy('id')->pluck('name')->all();
            $this->assertCount(1, array_unique($finalNames), 'Deadlock rollback/retry must leave both rows in one atomic committed state.');
        } finally {
            $this->removeStartGate($gate);
        }
    }

    private function enableSignedPolicy(string $privateKey): void
    {
        $policy = ExaPayOfflineRiskPolicy::query()->whereKey(1)->firstOrFail();
        $policy->enabled = true;
        $policy->policy_version = max(1, (int) $policy->policy_version + 1);
        $policy->policy_expires_at = now()->addHours(24);
        $contract = new OfflineRiskPolicyContract(
            (int) $policy->policy_version, true, (int) $policy->max_amount_minor,
            (int) $policy->max_outstanding_per_user_minor, (int) $policy->max_devices_per_user,
            (int) $policy->instrument_lifetime_seconds, (int) $policy->max_hops,
            (int) $policy->max_transfers_per_journal, (int) $policy->max_cumulative_spending_minor,
            (int) $policy->minimum_protocol_version, $policy->policy_expires_at->timestamp,
            (int) $policy->max_device_exposure_minor, (int) $policy->max_incoming_exposure_per_user_minor,
            (int) $policy->max_transactions_per_device, (int) $policy->max_journal_bytes,
            (array) $policy->supported_assets, (array) $policy->allowed_device_classes,
            (int) $policy->trust_epoch, (bool) $policy->require_enrolled_device,
        );
        $canonical = $contract->canonicalPayload();
        $signed = app(OfflineInstrumentCrypto::class)->signIssuerPayload(json_decode($canonical, true, 512, JSON_THROW_ON_ERROR));
        $policy->policy_digest = hash('sha256', $canonical);
        $policy->policy_signature = $signed['signature'];
        $policy->policy_public_key = $signed['issuer_public_key'];
        $policy->save();
        app(\App\Services\ExaPay\Offline\OfflineDeviceTrustService::class)
            ->recordPolicyVersion($policy, $canonical, $signed['signature'], $signed['issuer_public_key']);
    }

    private function seedBalance(User $user, int $amountMinor): void
    {
        $ledger = app(LedgerService::class);
        $funding = $ledger->getOrCreateAccount((int) $user->id, 'funding', 'SIMUSDT');
        $issuer = $ledger->getOrCreateAccount(null, 'test_simulation_issuer', 'SIMUSDT');
        $amount = $this->decimal($amountMinor);
        $ledger->postDoubleEntry('EXAPAY-PG-SEED-' . Str::uuid(), 'Isolated PostgreSQL SIMUSDT fixture', [
            ['account_id' => $issuer->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => 'SIMUSDT'],
            ['account_id' => $funding->id, 'amount' => $amount, 'asset' => 'SIMUSDT', 'user_id' => (int) $user->id],
        ], 'test_simulation_fund', ['source_service' => 'postgres_integration_fixture', 'real_funds' => false]);
    }

    /** @return array{0:string,1:string} device UUID and private PEM */
    private function enrollDevice(User $user, string $issuerPrivateKey): array
    {
        $privateKey = $this->newPrivateKey();
        $details = openssl_pkey_get_details(openssl_pkey_get_private($privateKey));
        $publicKey = (string) $details['key'];
        $uuid = (string) Str::uuid();
        $nonce = bin2hex(random_bytes(24));
        $signedAt = now()->timestamp;
        $message = implode('|', ['exaearn-offline-enroll:v1', (string) $user->id, $uuid, $nonce, (string) $signedAt]);
        openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        app(OfflineStoredValueService::class)->enrollDevice($user, [
            'device_uuid' => $uuid, 'public_key' => $publicKey, 'nonce' => $nonce,
            'signed_at' => $signedAt, 'signature' => base64_encode($signature),
        ]);

        return [$uuid, $privateKey];
    }

    /** @return array{claim_uuid:string,output_uuid:string} */
    private function createVerifiedRootClaim(User $user, string $deviceUuid, string $devicePrivateKey, int $amountMinor, string $referenceSuffix): array
    {
        static $sequence = 0;
        $sequence++;
        $issued = app(OfflineStoredValueService::class)->issue($user, new OfflineIssuanceContract(
            OfflinePaymentMode::OFFLINE_STORED_VALUE, $deviceUuid, $amountMinor, 'SIMUSDT', 'pg-issue-' . $referenceSuffix . '-' . $sequence,
        ));
        $reference = (string) $issued['reference'];
        $nonce = bin2hex(random_bytes(24));
        $signedAt = now()->timestamp;
        $ackMessage = implode('|', ['exaearn-offline-ack:v1', $reference, $deviceUuid, $nonce, (string) $signedAt]);
        openssl_sign($ackMessage, $signature, $devicePrivateKey, OPENSSL_ALGO_SHA256);
        app(OfflineStoredValueService::class)->acknowledge($user, $reference, [
            'device_uuid' => $deviceUuid, 'nonce' => $nonce, 'signed_at' => $signedAt, 'signature' => base64_encode($signature),
        ]);

        $outputUuid = (string) $issued['instrument_id'];
        $claim = app(OfflineReconciliationClaimService::class)->submit(
            $user, 'pg-claim-' . $referenceSuffix . '-' . $sequence . '-idempotency', $outputUuid, $amountMinor, [],
        );
        $this->assertSame('VERIFIED', $claim['status']);

        return ['claim_uuid' => $claim['claim_uuid'], 'output_uuid' => $outputUuid];
    }

    /** @return array{exit_codes:array<int,int>,results:array<int,array<string,mixed>>} */
    private function runConcurrentWorkers(array $gate, User $user, string $claimUuid, ?string $sameKey): array
    {
        $worker = base_path('tests/Support/ExaPayOfflineRedemptionWorker.php');
        $connection = config('database.connections.pgsql');
        $processes = [];
        foreach ([0, 1] as $index) {
            $key = $sameKey ?? ('pg-redemption-race-' . $claimUuid . '-' . $index . '-idempotency');
            $environment = [
                'APP_ENV' => 'testing', 'APP_KEY' => 'base64:' . base64_encode(str_repeat('p', 32)),
                'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
                'DB_HOST' => (string) $connection['host'], 'DB_PORT' => (string) $connection['port'],
                'DB_DATABASE' => (string) $connection['database'], 'DB_USERNAME' => (string) $connection['username'],
                'DB_PASSWORD' => (string) ($connection['password'] ?? ''), 'DB_SSLMODE' => 'disable',
                'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync',
                'EXAPAY_OFFLINE_SIMULATION_ENABLED' => 'true', 'EXAPAY_OFFLINE_REDEMPTION_ENABLED' => 'true',
                'EXAPAY_PG_WORKER_READY' => $gate['ready'][$index], 'EXAPAY_PG_WORKER_START' => $gate['start'],
                'EXAPAY_PG_WORKER_USER_ID' => (string) $user->id, 'EXAPAY_PG_WORKER_CLAIM_UUID' => $claimUuid,
                'EXAPAY_PG_WORKER_IDEMPOTENCY_KEY' => $key,
            ];
            $processes[$index] = new Process([PHP_BINARY, $worker], base_path(), $environment, null, 45);
            $processes[$index]->start();
        }

        $deadline = microtime(true) + 20;
        while ((!is_file($gate['ready'][0]) || !is_file($gate['ready'][1])) && microtime(true) < $deadline) {
            usleep(10000);
        }
        if (!is_file($gate['ready'][0]) || !is_file($gate['ready'][1])) {
            $diagnostics = [];
            foreach ($processes as $index => $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
                $diagnostics[] = sprintf('worker_%d_exit=%s stderr=%s stdout=%s', $index, (string) $process->getExitCode(),
                    trim($process->getErrorOutput()), trim($process->getOutput()));
            }
            $this->fail('PostgreSQL workers did not reach the start barrier: ' . implode(' | ', $diagnostics));
        }
        $this->assertFileExists($gate['ready'][0], 'First PostgreSQL worker did not reach the start barrier.');
        $this->assertFileExists($gate['ready'][1], 'Second PostgreSQL worker did not reach the start barrier.');
        file_put_contents($gate['start'], 'go', LOCK_EX);

        $results = [];
        $exitCodes = [];
        foreach ($processes as $index => $process) {
            $process->wait();
            $exitCodes[$index] = $process->getExitCode() ?? -1;
            $outputLines = array_values(array_filter(preg_split('/\R/', trim($process->getOutput())) ?: []));
            $lastLine = $outputLines === [] ? '' : $outputLines[array_key_last($outputLines)];
            $decoded = json_decode($lastLine, true);
            $this->assertIsArray($decoded, 'PostgreSQL worker returned no structured outcome. ' . $process->getErrorOutput());
            $results[$index] = $decoded;
        }

        return ['exit_codes' => $exitCodes, 'results' => $results];
    }

    /** @return array{exit_codes:array<int,int>,results:array<int,array<string,mixed>>} */
    private function runConcurrentDeadlockWorkers(User $firstUser, User $secondUser): array
    {
        $gate = $this->newStartGate();
        try {
            $worker = base_path('tests/Support/ExaPayOfflineRedemptionWorker.php');
            $connection = config('database.connections.pgsql');
            $processes = [];
            foreach ([0, 1] as $index) {
                $environment = [
                    'APP_ENV' => 'testing', 'APP_KEY' => 'base64:' . base64_encode(str_repeat('p', 32)),
                    'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => (string) $connection['host'],
                    'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => (string) $connection['database'],
                    'DB_USERNAME' => (string) $connection['username'], 'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
                    'DB_SSLMODE' => 'disable', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync',
                    'EXAPAY_OFFLINE_SIMULATION_ENABLED' => 'true', 'EXAPAY_OFFLINE_REDEMPTION_ENABLED' => 'true',
                    'EXAPAY_PG_WORKER_MODE' => 'deadlock_probe', 'EXAPAY_PG_WORKER_READY' => $gate['ready'][$index],
                    'EXAPAY_PG_WORKER_START' => $gate['start'], 'EXAPAY_PG_WORKER_FIRST_USER_ID' => (string) ($index === 0 ? $firstUser->id : $secondUser->id),
                    'EXAPAY_PG_WORKER_SECOND_USER_ID' => (string) ($index === 0 ? $secondUser->id : $firstUser->id),
                    'EXAPAY_PG_WORKER_INDEX' => (string) $index, 'EXAPAY_PG_WORKER_ACQUIRED' => $gate['acquired'][$index],
                    'EXAPAY_PG_WORKER_PEER_ACQUIRED' => $gate['acquired'][1 - $index], 'EXAPAY_PG_WORKER_CONTINUE' => $gate['continue'],
                ];
                $processes[$index] = new Process([PHP_BINARY, $worker], base_path(), $environment, null, 45);
                $processes[$index]->start();
            }

            $deadline = microtime(true) + 20;
            while ((!is_file($gate['ready'][0]) || !is_file($gate['ready'][1])) && microtime(true) < $deadline) usleep(10000);
            $this->assertFileExists($gate['ready'][0], 'First deadlock worker did not reach the start barrier.');
            $this->assertFileExists($gate['ready'][1], 'Second deadlock worker did not reach the start barrier.');
            file_put_contents($gate['start'], 'go', LOCK_EX);

            $deadline = microtime(true) + 20;
            while ((!is_file($gate['acquired'][0]) || !is_file($gate['acquired'][1])) && microtime(true) < $deadline) usleep(10000);
            $this->assertFileExists($gate['acquired'][0], 'First deadlock worker did not acquire its initial row lock.');
            $this->assertFileExists($gate['acquired'][1], 'Second deadlock worker did not acquire its initial row lock.');
            file_put_contents($gate['continue'], 'go', LOCK_EX);

            $results = [];
            $exitCodes = [];
            foreach ($processes as $index => $process) {
                $process->wait();
                $exitCodes[$index] = $process->getExitCode() ?? -1;
                $outputLines = array_values(array_filter(preg_split('/\R/', trim($process->getOutput())) ?: []));
                $decoded = json_decode($outputLines === [] ? '' : $outputLines[array_key_last($outputLines)], true);
                $this->assertIsArray($decoded, 'Deadlock worker returned no structured outcome. ' . $process->getErrorOutput());
                $results[$index] = $decoded;
            }

            return ['exit_codes' => $exitCodes, 'results' => $results];
        } finally {
            $this->removeStartGate($gate);
        }
    }

    /** @return array{start:string,continue:string,ready:array<int,string>,acquired:array<int,string>} */
    private function newStartGate(): array
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'exaearn-pg-phase3-' . Str::uuid();
        mkdir($directory, 0700, true);
        return [
            'start' => $directory . DIRECTORY_SEPARATOR . 'start',
            'continue' => $directory . DIRECTORY_SEPARATOR . 'continue',
            'ready' => [$directory . DIRECTORY_SEPARATOR . 'ready-0', $directory . DIRECTORY_SEPARATOR . 'ready-1'],
            'acquired' => [$directory . DIRECTORY_SEPARATOR . 'acquired-0', $directory . DIRECTORY_SEPARATOR . 'acquired-1'],
        ];
    }

    private function removeStartGate(array $gate): void
    {
        $directory = dirname($gate['start']);
        foreach ([$gate['start'], $gate['continue'], ...$gate['ready'], ...$gate['acquired']] as $path) {
            if (is_file($path)) unlink($path);
        }
        if (is_dir($directory)) rmdir($directory);
    }

    private function assertAccountingIsConserved(): void
    {
        $reserve = ExaPayOfflineReserve::query()->where('asset', 'SIMUSDT')->firstOrFail();
        $this->assertSame((int) $reserve->issued_minor,
            (int) $reserve->outstanding_liability_minor + (int) $reserve->redeemed_minor + (int) $reserve->extinguished_minor);
        $reserveAccount = Account::query()->whereNull('user_id')->where('account_type', 'exapay_offline_reserve')->where('asset', 'SIMUSDT')->firstOrFail();
        $this->assertSame(0, FinancialDecimal::compare((string) $reserveAccount->balance, $this->decimal((int) $reserve->reserved_minor)));
        $this->assertSame((int) $reserve->reserved_minor, (int) $reserve->outstanding_liability_minor);
    }

    private function truncateIsolatedDatabase(): void
    {
        $tables = collect(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename NOT IN ('migrations', 'exapay_offline_risk_policies')"))
            ->map(fn (object $row): string => DB::getQueryGrammar()->wrapTable((string) $row->tablename))
            ->all();
        if ($tables !== []) {
            DB::statement('TRUNCATE TABLE ' . implode(', ', $tables) . ' RESTART IDENTITY CASCADE');
        }
    }

    private function ensureRiskPolicyExists(): void
    {
        if (!ExaPayOfflineRiskPolicy::query()->whereKey(1)->exists()) {
            DB::table('exapay_offline_risk_policies')->insert([
                'id' => 1, 'enabled' => false, 'max_amount_minor' => 10000,
                'max_outstanding_per_user_minor' => 50000, 'max_devices_per_user' => 3,
                'instrument_lifetime_seconds' => 86400, 'max_hops' => 5,
                'max_transfers_per_journal' => 100, 'max_cumulative_spending_minor' => 50000,
                'minimum_protocol_version' => 1, 'policy_version' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        ExaPayOfflineReserve::query()->firstOrCreate(['asset' => 'SIMUSDT'], [
            'reserved_minor' => 0, 'outstanding_liability_minor' => 0,
            'issued_minor' => 0, 'redeemed_minor' => 0, 'extinguished_minor' => 0, 'version' => 0,
        ]);
        ExaPayOfflineSecurityControl::query()->firstOrCreate(['id' => 1], [
            'issuance_suspended' => false, 'redemption_suspended' => false, 'trust_epoch' => 1,
            'blocked_device_families' => [],
        ]);
        app(LedgerService::class)->getOrCreateAccount(null, 'exapay_offline_reserve', 'SIMUSDT');
    }

    private function minor(string $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function decimal(int $minor): string
    {
        return intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    private function newPrivateKey(): string
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);
        return (string) $privateKey;
    }
}
