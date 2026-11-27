<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\ExaPay\Offline\OfflineJournalContract;
use App\Domain\ExaPay\Offline\OfflineIssuanceContract;
use App\Domain\ExaPay\Offline\OfflinePaymentMode;
use App\Domain\ExaPay\Offline\OfflineRiskPolicyContract;
use App\Domain\ExaPay\Offline\OfflineTransferCommitment;
use App\Domain\ExaPay\Offline\OfflineTransferState;
use App\Models\Account;
use App\Models\ExaPayOfflineDevice;
use App\Models\ExaPayOfflineInstrument;
use App\Models\ExaPayOfflineReserve;
use App\Models\ExaPayOfflineRiskPolicy;
use App\Models\ExaPayOfflineValueOutput;
use App\Models\ExaPayOfflineReconciliationClaim;
use App\Models\ExaPayOfflineReconciliationEvidence;
use App\Models\ExaPayOfflineRedemption;
use App\Models\ExaPayOfflineTransferSession;
use App\Models\ExaPayOfflineDeviceKeyHistory;
use App\Models\ExaPayOfflineSecurityControl;
use App\Models\ExaPayOfflineFraudCase;
use App\Models\ExaPayOfflinePolicyVersion;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ExaPay\Offline\OfflineStoredValueService;
use App\Services\ExaPay\Offline\OfflineInstrumentCrypto;
use App\Services\ExaPay\Offline\OfflineTransferReconciliationService;
use App\Services\ExaPay\Offline\OfflineReconciliationClaimService;
use App\Services\ExaPay\Offline\OfflineRedemptionService;
use App\Services\ExaPay\Offline\OfflineEnvironmentGuard;
use App\Services\ExaPay\Offline\InMemoryOfflineTransferTransportAdapter;
use App\Services\ExaPay\Offline\OfflineExposureService;
use App\Services\FinancialDecimal;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class ExaPayOfflineSimulationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $deviceUuid;
    private string $devicePrivateKey;
    private string $devicePublicKey;

    public function test_hardware_attestation_requires_fresh_one_time_server_challenge_and_stays_unverified_without_adapter(): void
    {
        $this->enrollDevice();
        $path = '/api/exapay/offline/devices/'.$this->deviceUuid.'/attestation-challenge';
        $challengeResponse = $this->actingAs($this->user)->getJson($path)->assertOk()
            ->assertJsonPath('data.real_funds_enabled', false)
            ->assertJsonPath('data.expires_in_seconds', 300);
        $challenge = (string) $challengeResponse->json('data.challenge');
        $this->assertSame(43, strlen($challenge));

        $payload = ['format' => 'android_keystore', 'challenge' => $challenge, 'attestation' => []];
        $this->actingAs($this->user)->postJson('/api/exapay/offline/devices/'.$this->deviceUuid.'/attestation', $payload)
            ->assertAccepted()
            ->assertJsonPath('data.attestation_status', 'UNSUPPORTED')
            ->assertJsonPath('data.trust_class', 'SOFTWARE_ONLY')
            ->assertJsonPath('data.real_funds_accessible', false);

        $this->actingAs($this->user)->postJson('/api/exapay/offline/devices/'.$this->deviceUuid.'/attestation', $payload)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'attestation_challenge_expired_or_invalid');
    }

    public function test_attestation_challenge_cannot_be_requested_for_another_users_device(): void
    {
        $device = $this->enrollDevice();
        $other = User::factory()->create();
        $this->actingAs($other)->getJson('/api/exapay/offline/devices/'.$device->device_uuid.'/attestation-challenge')
            ->assertNotFound();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Each test gets an isolated throttle bucket while exercising the real middleware.
        Cache::store('array')->flush();
        config([
            'app.env' => 'testing',
            'exapay_offline.enabled' => true,
            'exapay_offline.allowed_environments' => ['testing', 'staging'],
            'exapay_offline.issuer_private_key' => $this->newPrivateKey(),
            'exapay_offline.issuer_private_key_passphrase' => '',
        ]);
        $this->signRiskPolicy(ExaPayOfflineRiskPolicy::query()->whereKey(1)->firstOrFail(), ['enabled' => true]);
        $this->user = User::factory()->create();
        $this->newDeviceKeyPair();
    }

    public function test_40_of_100_simusdt_is_reserved_with_conserved_value_and_balanced_ledger(): void
    {
        $this->fundSimulatedBalance(10000);
        $this->enrollDevice();

        $first = $this->issue(4000, 'offline-issue-test-key-0001')->assertCreated()->assertJsonPath('data.amount_minor', 4000);
        $reference = (string) $first->json('data.reference');
        $instrumentUuid = (string) $first->json('data.instrument_id');
        $this->assertNotSame('', $reference);
        $this->assertSame('SIMUSDT', $first->json('data.asset'));
        $this->assertSame(false, $first->json('data.real_funds'));
        $this->assertSame('pending_acknowledgment', $first->json('data.status'));

        $online = app(LedgerService::class)->getBalance((int) $this->user->id, 'SIMUSDT', 'funding');
        $reserveAccount = Account::query()->whereNull('user_id')->where('account_type', 'exapay_offline_reserve')->where('asset', 'SIMUSDT')->firstOrFail();
        $reserve = ExaPayOfflineReserve::query()->where('asset', 'SIMUSDT')->firstOrFail();
        $this->assertSame(6000, $this->minor($online));
        $this->assertSame(4000, $this->minor((string) $reserveAccount->balance));
        $this->assertSame(4000, (int) $reserve->reserved_minor);
        $this->assertSame(4000, (int) $reserve->outstanding_liability_minor);
        $this->assertSame(10000, $this->minor($online) + (int) $reserve->reserved_minor);
        $this->assertDatabaseHas('exapay_offline_instruments', [
            'instrument_uuid' => $instrumentUuid, 'reference' => $reference, 'amount_minor' => 4000,
            'status' => 'pending_acknowledgment', 'delivery_status' => 'pending', 'asset' => 'SIMUSDT',
        ]);

        $ledgerTransaction = LedgerTransaction::query()->where('transaction_type', 'exapay_offline_issue')->firstOrFail();
        $entries = LedgerEntry::query()->where('reference', $ledgerTransaction->reference)->get();
        $this->assertCount(2, $entries);
        $this->assertSame(0, FinancialDecimal::compare((string) $entries[0]->amount, FinancialDecimal::sub('0', (string) $entries[1]->amount)));
        $sum = '0';
        foreach ($entries as $entry) $sum = FinancialDecimal::add($sum, (string) $entry->amount);
        $this->assertSame(0, FinancialDecimal::compare($sum, '0'));

        $this->actingAs($this->user)->getJson('/api/exapay/offline/balance')
            ->assertOk()->assertJsonPath('data.online_available_minor', 6000)
            ->assertJsonPath('data.offline_outstanding_minor', 4000)
            ->assertJsonPath('data.real_funds_accessible', false);
    }

    public function test_issuance_is_idempotent_and_rejects_key_reuse_with_different_amount(): void
    {
        $this->fundSimulatedBalance(10000);
        $this->enrollDevice();
        $key = 'offline-issue-idempotency-0001';

        $first = $this->issue(2500, $key)->assertCreated();
        $reference = $first->json('data.reference');
        $this->issue(2500, $key)->assertOk()->assertJsonPath('data.reference', $reference)->assertJsonPath('data.idempotent_replay', true);
        $this->issue(2600, $key)->assertStatus(409)->assertJsonPath('error_code', 'idempotency_conflict');
        $this->assertSame(1, ExaPayOfflineInstrument::query()->count());
        $this->assertSame(1, LedgerTransaction::query()->where('transaction_type', 'exapay_offline_issue')->count());
        $this->assertSame(7500, $this->minor(app(LedgerService::class)->getBalance((int) $this->user->id, 'SIMUSDT', 'funding')));
    }

    public function test_insufficient_funds_fractional_minor_units_invalid_assets_and_amounts_are_rejected(): void
    {
        $this->fundSimulatedBalance(10000);
        $this->enrollDevice();
        $this->issue(10001, 'offline-issue-limit-check-01')->assertStatus(422)->assertJsonPath('error_code', 'amount_limit_exceeded');
        $this->actingAs($this->user)->withHeader('Idempotency-Key', 'offline-issue-invalid-amount-01')
            ->postJson('/api/exapay/offline/instruments', ['device_uuid' => $this->deviceUuid, 'amount_minor' => 0, 'asset' => 'SIMUSDT'])->assertUnprocessable();
        $this->actingAs($this->user)->withHeader('Idempotency-Key', 'offline-issue-invalid-asset-01')
            ->postJson('/api/exapay/offline/instruments', ['device_uuid' => $this->deviceUuid, 'amount_minor' => 100, 'asset' => 'USDT'])->assertUnprocessable();
        $this->issue(1, 'offline-issue-small-balance-01')->assertCreated();
    }

    public function test_insufficient_simulated_balance_does_not_issue_an_instrument(): void
    {
        $this->fundSimulatedBalance(100);
        $this->enrollDevice();

        $this->issue(101, 'offline-issue-insufficient-balance-01')->assertStatus(422)->assertJsonPath('error_code', 'insufficient_simulated_balance');
        $this->assertSame(100, $this->minor(app(LedgerService::class)->getBalance((int) $this->user->id, 'SIMUSDT', 'funding')));
        $this->assertSame(0, ExaPayOfflineInstrument::query()->count());
    }

    public function test_invalid_or_suspended_devices_and_cross_account_status_access_are_denied(): void
    {
        $this->fundSimulatedBalance(10000);
        $this->actingAs($this->user)->postJson('/api/exapay/offline/devices/enroll', [
            'device_uuid' => (string) Str::uuid(), 'public_key' => $this->devicePublicKey,
            'nonce' => bin2hex(random_bytes(24)), 'signed_at' => now()->timestamp, 'signature' => base64_encode('invalid'),
        ])->assertForbidden()->assertJsonPath('error_code', 'device_proof_invalid');

        $device = $this->enrollDevice();
        $issued = $this->issue(500, 'offline-issue-owner-check-0001')->assertCreated();
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)->getJson('/api/exapay/offline/instruments/' . $issued->json('data.reference'))
            ->assertNotFound()->assertJsonPath('error_code', 'instrument_not_found');

        app(OfflineStoredValueService::class)->suspendDevice((int) $device->id, 'device compromised');
        $this->issue(100, 'offline-issue-suspended-check-01')->assertForbidden()->assertJsonPath('error_code', 'device_suspended');
        $this->assertSame(500, (int) ExaPayOfflineReserve::query()->value('reserved_minor'));
    }

    public function test_interrupted_delivery_recovers_same_instrument_and_acknowledgment_is_signed(): void
    {
        $this->fundSimulatedBalance(5000);
        $this->enrollDevice();
        $issued = $this->issue(1250, 'offline-issue-delivery-recovery-01')->assertCreated();
        $reference = (string) $issued->json('data.reference');
        $instrumentUuid = (string) $issued->json('data.instrument_id');
        $originalSignature = (string) $issued->json('data.issuer_signature');

        $this->actingAs($this->user)->getJson('/api/exapay/offline/instruments/' . $reference)
            ->assertOk()->assertJsonPath('data.status', 'pending_acknowledgment')->assertJsonPath('data.liability_released', false);
        $recoveryProof = $this->deviceProof('exaearn-offline-recover:v1|' . $reference . '|' . $this->deviceUuid . '|');
        $this->actingAs($this->user)->postJson('/api/exapay/offline/instruments/' . $reference . '/recover', $recoveryProof)
            ->assertOk()->assertJsonPath('data.instrument_id', $instrumentUuid)->assertJsonPath('data.issuer_signature', $originalSignature);

        $ackProof = $this->deviceProof('exaearn-offline-ack:v1|' . $reference . '|' . $this->deviceUuid . '|');
        $this->actingAs($this->user)->postJson('/api/exapay/offline/instruments/' . $reference . '/acknowledge', $ackProof)
            ->assertOk()->assertJsonPath('data.delivery_status', 'acknowledged')->assertJsonPath('data.status', 'acknowledged');
        $this->assertSame(1, LedgerTransaction::query()->where('transaction_type', 'exapay_offline_issue')->count());
        $this->assertSame(1250, (int) ExaPayOfflineReserve::query()->value('outstanding_liability_minor'));
    }

    public function test_signed_journal_lineage_and_double_consumption_conflict_never_redeem(): void
    {
        $this->fundSimulatedBalance(5000);
        $this->enrollDevice();
        $issued = $this->issue(1250, 'offline-issue-journal-lineage-01')->assertCreated();
        $instrumentUuid = (string) $issued->json('data.instrument_id');
        $claim = ['instrument_id' => $instrumentUuid, 'claim_id' => (string) Str::uuid(), 'consumed_at' => now()->timestamp];
        $firstPayload = $this->journalPayload(1, '', [$claim]);
        $first = $this->actingAs($this->user)->postJson('/api/exapay/offline/journals', $firstPayload)->assertAccepted();
        $digest = (string) $first->json('data.event_digest');
        $this->assertSame('pending_review', $first->json('data.status'));
        $this->assertSame('not_redeemed', $first->json('data.redemption_status'));

        $nonceCountBeforeRetry = \Illuminate\Support\Facades\DB::table('exapay_offline_device_nonces')->count();
        $this->travel(6)->minutes();
        $retry = $this->actingAs($this->user)->postJson('/api/exapay/offline/journals', $firstPayload)->assertAccepted();
        $this->assertSame($digest, $retry->json('data.event_digest'));
        $this->assertSame($nonceCountBeforeRetry, \Illuminate\Support\Facades\DB::table('exapay_offline_device_nonces')->count());

        $staleSignedAt = $firstPayload['signed_at'] - 360;
        $staleNewEvent = $this->journalPayload(2, $digest, [[
            'instrument_id' => $instrumentUuid,
            'claim_id' => (string) Str::uuid(),
            'consumed_at' => $staleSignedAt,
        ]], $staleSignedAt);
        $staleResponse = $this->actingAs($this->user)->postJson('/api/exapay/offline/journals', $staleNewEvent);
        $this->assertSame(422, $staleResponse->status(), $staleResponse->getContent());
        $this->assertSame('device_proof_expired', $staleResponse->json('error_code'), $staleResponse->getContent());
        $this->travelBack();

        $duplicateClaim = ['instrument_id' => $instrumentUuid, 'claim_id' => (string) Str::uuid(), 'consumed_at' => now()->timestamp];
        $second = $this->submitJournal(2, $digest, [$duplicateClaim])->assertAccepted();
        $this->assertSame('conflict', $second->json('data.status'));
        $this->assertSame('duplicate_consumption_claim', $second->json('data.conflict_code'));
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('exapay_offline_consumption_claims')->count());
        $this->assertSame(1250, (int) ExaPayOfflineReserve::query()->value('reserved_minor'));
        $this->assertSame(1250, (int) ExaPayOfflineReserve::query()->value('outstanding_liability_minor'));
        $this->assertNull(ExaPayOfflineInstrument::query()->firstOrFail()->consumed_at);

        $gapClaim = ['instrument_id' => $instrumentUuid, 'claim_id' => (string) Str::uuid(), 'consumed_at' => now()->timestamp];
        $gap = $this->submitJournal(4, str_repeat('a', 64), [$gapClaim])->assertAccepted();
        $this->assertSame('conflict', $gap->json('data.status'));
        $this->assertSame('counter_gap', $gap->json('data.conflict_code'));
    }

    public function test_ledger_or_audit_failure_rolls_back_issuance_and_reserve_changes(): void
    {
        $this->fundSimulatedBalance(5000);
        $this->enrollDevice();
        $audit = Mockery::mock(AuditLogService::class);
        $audit->shouldReceive('log')->once()->andThrow(new RuntimeException('audit unavailable'));
        $this->app->instance(AuditLogService::class, $audit);

        try {
            app(OfflineStoredValueService::class)->issue($this->user, new OfflineIssuanceContract(
                OfflinePaymentMode::OFFLINE_STORED_VALUE, $this->deviceUuid, 1000, 'SIMUSDT', 'offline-issue-rollback-test-01',
            ));
            $this->fail('Expected the audit failure to abort issuance.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit unavailable', $exception->getMessage());
        }

        $this->assertSame(5000, $this->minor(app(LedgerService::class)->getBalance((int) $this->user->id, 'SIMUSDT', 'funding')));
        $this->assertSame(0, ExaPayOfflineInstrument::query()->count());
        $this->assertSame(0, LedgerTransaction::query()->where('transaction_type', 'exapay_offline_issue')->count());
        $this->assertSame(0, (int) ExaPayOfflineReserve::query()->value('reserved_minor'));
        $this->assertSame(0, (int) ExaPayOfflineReserve::query()->value('outstanding_liability_minor'));
    }

    public function test_capabilities_and_issuance_fail_closed_in_production(): void
    {
        $this->actingAs($this->user);
        config(['app.env' => 'production', 'exapay_offline.allowed_environments' => ['testing', 'staging', 'production'], 'exapay_offline.enabled' => true, 'exapay_offline.redemption_enabled' => true]);
        $this->getJson('/api/exapay/offline/capabilities')->assertOk()
            ->assertJsonPath('data.offline_simulation_enabled', false)
            ->assertJsonPath('data.redemption_enabled', false)
            ->assertJsonPath('data.real_funds_accessible', false);
        $proof = $this->deviceProof('exaearn-offline-enroll:v1|' . $this->user->id . '|' . $this->deviceUuid . '|');
        $this->postJson('/api/exapay/offline/devices/enroll', [
            'device_uuid' => $this->deviceUuid, 'public_key' => $this->devicePublicKey,
        ] + $proof)->assertNotFound()->assertJsonPath('error_code', 'offline_simulation_disabled');
        $this->expectException(\App\Domain\ExaPay\Offline\OfflinePaymentException::class);
        $this->expectExceptionMessage('SIMUSDT redemption is unavailable in this environment.');
        app(OfflineRedemptionService::class)->redeem($this->user, (string) Str::uuid(), 'production-redemption-key-0001');
    }

    public function test_offline_simulation_fails_closed_when_environment_is_missing_unsupported_or_mismatched(): void
    {
        config(['exapay_offline.enabled' => true]);
        foreach ([null, '', 'local', 'production', 'staging'] as $configuredEnvironment) {
            config(['app.env' => $configuredEnvironment]);
            $this->assertFalse(OfflineEnvironmentGuard::allowsSimulation(), 'Unexpectedly allowed APP_ENV: ' . var_export($configuredEnvironment, true));
        }

        config(['app.env' => 'testing']);
        $this->assertTrue(OfflineEnvironmentGuard::allowsSimulation());
    }

    public function test_recipient_reconciles_two_hop_change_chain_without_online_credit(): void
    {
        $this->fundSimulatedBalance(4000);
        $deviceA = $this->enrollDevice();
        $userB = User::factory()->create();
        $userC = User::factory()->create();
        // The test database reuses user IDs; clear only this user's limiter keys
        // so a prior test cannot consume the current test's isolated quota.
        RateLimiter::clear(sha1((string) $userC->getAuthIdentifier()));
        foreach ([
            'exapay-offline-claim-create', 'exapay-offline-claim-evidence', 'exapay-offline-claim-redeem',
        ] as $prefix) {
            RateLimiter::clear($prefix . sha1((string) $userC->getAuthIdentifier()));
        }
        [$deviceB, $privateB] = $this->enrollUserDevice($userB);
        [$deviceC, $privateC] = $this->enrollUserDevice($userC);
        $root = $this->issue(4000, 'offline-issue-multihop-root-0001')->assertCreated();
        $this->acknowledgeInstrument((string) $root->json('data.reference'));
        $rootOutput = ExaPayOfflineValueOutput::query()->where('output_uuid', $root->json('data.instrument_id'))->firstOrFail();

        $first = $this->signedTransfer($deviceA, $deviceB, $this->devicePrivateKey, $privateB, $rootOutput->output_uuid, 4000, 3000, 1000, 2, 1, $rootOutput->lineage_digest, 'transfer-a-b-idem-000001');
        $firstDigest = $this->commitmentFromEntry($first)->digest();
        $secondParent = hash('sha256', $firstDigest . '|recipient|' . $first['recipient_output_uuid']);
        $second = $this->signedTransfer($deviceB, $deviceC, $privateB, $privateC, $first['recipient_output_uuid'], 3000, 1000, 2000, 1, 2, $secondParent, 'transfer-b-c-idem-000001');

        $service = app(OfflineTransferReconciliationService::class);
        $forged = $first;
        $forged['sender_signature'] = base64_encode(random_bytes(256));
        try {
            $service->reconcile($userC, [$forged, $second]);
            $this->fail('A forged sender signature must be rejected.');
        } catch (\App\Domain\ExaPay\Offline\OfflinePaymentException $exception) {
            $this->assertSame('transfer_sender_signature_invalid', $exception->errorCode);
        }
        $this->assertSame(0, ExaPayOfflineTransferSession::query()->count());
        $result = $service->reconcile($userC, [$first, $second]);
        $this->assertSame('evidence_recorded', $result['status']);
        $this->assertFalse($result['online_credit']);
        $this->assertSame(2, ExaPayOfflineTransferSession::query()->count());
        $this->assertSame(4000, (int) ExaPayOfflineValueOutput::query()->whereIn('status', ['available', 'locked', 'quarantined'])->sum('amount_minor'));
        $this->assertSame(1000, (int) ExaPayOfflineValueOutput::query()->where('owner_user_id', $this->user->id)->where('status', 'available')->sum('amount_minor'));
        $this->assertSame(2000, (int) ExaPayOfflineValueOutput::query()->where('owner_user_id', $userB->id)->where('status', 'available')->sum('amount_minor'));
        $this->assertSame(1000, (int) ExaPayOfflineValueOutput::query()->where('owner_user_id', $userC->id)->where('status', 'available')->sum('amount_minor'));
        $this->assertSame(4000, (int) ExaPayOfflineReserve::query()->value('outstanding_liability_minor'));
        $this->assertSame(0, $this->minor(app(LedgerService::class)->getBalance((int) $userC->id, 'SIMUSDT', 'funding')));
        $this->assertTrue($service->reconcile($userC, [$first, $second])['transfers'][0]['idempotent_replay']);
    }

    public function test_valid_disconnected_transfer_can_be_reconciled_after_online_proof_window(): void
    {
        $this->fundSimulatedBalance(1000);
        $deviceA = $this->enrollDevice();
        $userB = User::factory()->create();
        [$deviceB, $privateB] = $this->enrollUserDevice($userB);
        $root = $this->issue(1000, 'offline-issue-delayed-reconcile-01')->assertCreated();
        $this->acknowledgeInstrument((string) $root->json('data.reference'));
        $deviceA->refresh();
        $rootOutput = ExaPayOfflineValueOutput::query()->where('output_uuid', $root->json('data.instrument_id'))->firstOrFail();
        $entry = $this->signedTransfer($deviceA, $deviceB, $this->devicePrivateKey, $privateB,
            $rootOutput->output_uuid, 1000, 400, 600, (int) $deviceA->device_counter + 1, 1, $rootOutput->lineage_digest, 'delayed-transfer-idem-01');

        $signedAt = (int) $entry['signed_at'];
        Carbon::setTestNow(now()->copy()->addMinutes(10));
        try {
            $this->assertGreaterThan((int) config('exapay_offline.proof_max_age_seconds'), now()->timestamp - $signedAt);
            $result = app(OfflineTransferReconciliationService::class)->reconcile($userB, [$entry]);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame('evidence_recorded', $result['status']);
        $this->assertSame(400, (int) ExaPayOfflineValueOutput::query()->where('owner_user_id', $userB->id)->where('status', 'available')->sum('amount_minor'));
        $this->assertSame(0, $this->minor(app(LedgerService::class)->getBalance((int) $userB->id, 'SIMUSDT', 'funding')));
    }

    public function test_recipient_only_claim_resumes_and_redeems_exactly_once_with_conserved_liability(): void
    {
        config(['exapay_offline.redemption_enabled' => true]);
        $this->fundSimulatedBalance(10000);
        $deviceA = $this->enrollDevice();
        $userB = User::factory()->create();
        $userC = User::factory()->create();
        [$deviceB, $privateB] = $this->enrollUserDevice($userB);
        [$deviceC, $privateC] = $this->enrollUserDevice($userC);
        $root = $this->issue(4000, 'offline-issue-phase3-chain-0001')->assertCreated();
        $this->acknowledgeInstrument((string) $root->json('data.reference'));
        $deviceA->refresh();
        $rootOutput = ExaPayOfflineValueOutput::query()->where('output_uuid', $root->json('data.instrument_id'))->firstOrFail();

        $first = $this->signedTransfer($deviceA, $deviceB, $this->devicePrivateKey, $privateB,
            $rootOutput->output_uuid, 4000, 3000, 1000, (int) $deviceA->device_counter + 1, 1,
            $rootOutput->lineage_digest, 'phase3-chain-a-b-idempotency');
        $firstDigest = $this->commitmentFromEntry($first)->digest();
        $secondOutputUuid = (string) $first['recipient_output_uuid'];
        $secondParent = hash('sha256', $firstDigest . '|recipient|' . $secondOutputUuid);
        $second = $this->signedTransfer($deviceB, $deviceC, $privateB, $privateC,
            $secondOutputUuid, 3000, 1000, 2000, (int) $deviceB->device_counter + 1, 2,
            $secondParent, 'phase3-chain-b-c-idempotency');

        $claims = app(OfflineReconciliationClaimService::class);
        $claimResponse = $this->actingAs($userC)->withHeader('Idempotency-Key', 'phase3-claim-idempotency-0001')
            ->postJson('/api/exapay/offline/reconciliation/claims', [
                'expected_output_uuid' => $second['recipient_output_uuid'], 'expected_amount_minor' => 1000, 'transfers' => [$second],
            ])->assertAccepted()->assertJsonPath('data.status', 'EVIDENCE_INCOMPLETE');
        $claim = $claimResponse->json('data');
        $this->assertSame('EVIDENCE_INCOMPLETE', $claim['status']);
        $claim = $this->actingAs($userC)->postJson('/api/exapay/offline/reconciliation/claims/' . $claim['claim_uuid'] . '/evidence', [
            'transfers' => [$first],
        ])->assertAccepted()->json('data');
        $this->assertSame('VERIFIED', $claim['status']);
        $this->assertSame(false, $claim['result']['online_credit']);
        $this->assertTrue($claim['audit_chain_valid']);
        try {
            $claims->show(User::factory()->create(), $claim['claim_uuid']);
            $this->fail('Claims must be isolated to their authenticated owner.');
        } catch (\App\Domain\ExaPay\Offline\OfflinePaymentException $exception) {
            $this->assertSame('reconciliation_claim_not_found', $exception->errorCode);
        }
        $preRedemptionReserve = ExaPayOfflineReserve::query()->firstOrFail();
        $preRedemptionAccount = Account::query()->whereNull('user_id')->where('account_type', 'exapay_offline_reserve')->where('asset', 'SIMUSDT')->firstOrFail();
        $this->assertSame(4000, (int) $preRedemptionReserve->reserved_minor);
        $this->assertSame(4000, (int) $preRedemptionReserve->outstanding_liability_minor);
        $this->assertSame(4000, (int) $preRedemptionReserve->issued_minor);
        $this->assertSame(0, FinancialDecimal::compare((string) $preRedemptionAccount->balance, '40.00'));

        $redemptions = app(OfflineRedemptionService::class);
        $idempotencyKey = 'phase3-redeem-idempotency-0001';
        Redis::shouldReceive('publish')->zeroOrMoreTimes()->andThrow(new RuntimeException('injected Redis outage'));
        $redeemed = $this->actingAs($userC)->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/exapay/offline/reconciliation/claims/' . $claim['claim_uuid'] . '/redeem')->assertOk()->json('data');
        $replay = $this->actingAs($userC)->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/exapay/offline/reconciliation/claims/' . $claim['claim_uuid'] . '/redeem')->assertOk()->json('data');
        $this->assertSame('REDEEMED', $redeemed['status']);
        $this->assertSame($redeemed['redemption_uuid'], $replay['redemption_uuid']);
        $this->assertTrue($replay['idempotent_replay']);
        try {
            $redemptions->redeem($userC, $claim['claim_uuid'], 'phase3-redeem-second-key-0001');
            $this->fail('A redeemed output cannot be consumed a second time under another key.');
        } catch (\App\Domain\ExaPay\Offline\OfflinePaymentException $exception) {
            $this->assertSame('offline_output_already_redeemed', $exception->errorCode);
        }
        $this->assertSame(1000, $this->minor(app(LedgerService::class)->getBalance((int) $userC->id, 'SIMUSDT', 'funding')));
        $this->assertSame(3000, (int) ExaPayOfflineReserve::query()->value('outstanding_liability_minor'));
        $lateConflict = $first;
        $lateConflict['parent_digest'] = str_repeat('f', 64);
        $lateEvidence = $claims->appendEvidence($userC, $claim['claim_uuid'], [$lateConflict]);
        $this->assertSame('REDEEMED', $lateEvidence['status']);
        $this->assertTrue($lateEvidence['audit_chain_valid']);
        $this->assertContains('late_conflicting_evidence', array_column($lateEvidence['events'], 'event_type'));
        $this->assertSame('CONFLICT', ExaPayOfflineReconciliationEvidence::query()
            ->where('claim_id', ExaPayOfflineReconciliationClaim::query()->where('claim_uuid', $claim['claim_uuid'])->value('id'))
            ->where('transfer_uuid', $first['transfer_uuid'])->where('status', 'CONFLICT')->value('status'));
        $this->assertSame(1000, $this->minor(app(LedgerService::class)->getBalance((int) $userC->id, 'SIMUSDT', 'funding')));
        $this->assertSame(1, LedgerTransaction::query()->where('transaction_type', 'exapay_offline_redemption')->count());
        $reserve = ExaPayOfflineReserve::query()->firstOrFail();
        $this->assertSame(4000, (int) $reserve->issued_minor);
        $this->assertSame(1000, (int) $reserve->redeemed_minor);
        $this->assertSame(0, (int) $reserve->extinguished_minor);
        $this->assertSame((int) $reserve->issued_minor, (int) $reserve->outstanding_liability_minor + (int) $reserve->redeemed_minor + (int) $reserve->extinguished_minor);
        $this->assertSame(1000, (int) ExaPayOfflineValueOutput::query()->where('owner_user_id', $this->user->id)->where('status', 'available')->sum('amount_minor'));
        $this->assertSame(2000, (int) ExaPayOfflineValueOutput::query()->where('owner_user_id', $userB->id)->where('status', 'available')->sum('amount_minor'));
        $this->assertSame(0, (int) ExaPayOfflineValueOutput::query()->where('owner_user_id', $userC->id)->where('status', 'available')->sum('amount_minor'));

        $ledgerTx = LedgerTransaction::query()->where('reference', $redeemed['ledger_reference'])->firstOrFail();
        $entries = LedgerEntry::query()->where('reference', $ledgerTx->reference)->get();
        $this->assertSame(2, $entries->count());
        $this->assertSame(0, FinancialDecimal::compare((string) $entries->sum('amount'), '0'));

        // A different verified output cannot reuse the first output's redemption key.
        $deviceB->refresh();
        $thirdParent = hash('sha256', $this->commitmentFromEntry($second)->digest() . '|change|' . $second['change_output_uuid']);
        $third = $this->signedTransfer($deviceB, $deviceC, $privateB, $privateC,
            (string) $second['change_output_uuid'], 2000, 500, 1500, (int) $deviceB->device_counter + 1, 3,
            $thirdParent, 'phase3-chain-b-c-second-idem');
        $secondClaim = $claims->submit($userC, 'phase3-claim-idempotency-0002', (string) $third['recipient_output_uuid'], 500, [$third]);
        $this->assertSame('VERIFIED', $secondClaim['status']);
        $secondOutput = ExaPayOfflineValueOutput::query()->where('output_uuid', $third['recipient_output_uuid'])->firstOrFail();
        $realLedger = app(LedgerService::class);
        $failingLedger = Mockery::mock($realLedger)->makePartial();
        $failingLedger->shouldReceive('postDoubleEntry')->once()->andThrow(new RuntimeException('injected ledger failure'));
        $this->app->instance(LedgerService::class, $failingLedger);
        try {
            app(OfflineRedemptionService::class)->redeem($userC, $secondClaim['claim_uuid'], 'phase3-redeem-rollback-key-1');
            $this->fail('A ledger-posting failure must abort the entire redemption transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('injected ledger failure', $exception->getMessage());
        } finally {
            $this->app->instance(LedgerService::class, $realLedger);
        }
        $this->assertSame('VERIFIED', ExaPayOfflineReconciliationClaim::query()->where('claim_uuid', $secondClaim['claim_uuid'])->value('status'));
        $this->assertSame('available', $secondOutput->fresh()->status);
        $this->assertSame(1, ExaPayOfflineRedemption::query()->count());
        $this->assertSame(3000, (int) ExaPayOfflineReserve::query()->value('outstanding_liability_minor'));
        try {
            $redemptions->redeem($userC, $secondClaim['claim_uuid'], $idempotencyKey);
            $this->fail('A redemption key reused for a different output must be rejected.');
        } catch (\App\Domain\ExaPay\Offline\OfflinePaymentException $exception) {
            $this->assertSame('redemption_idempotency_conflict', $exception->errorCode);
        }
        $this->assertSame(1000, $this->minor(app(LedgerService::class)->getBalance((int) $userC->id, 'SIMUSDT', 'funding')));
    }

    public function test_recovery_finalizes_an_existing_ledger_posting_without_reposting_credit(): void
    {
        config(['exapay_offline.redemption_enabled' => true]);
        $this->fundSimulatedBalance(10000);
        $this->enrollDevice();
        $root = $this->issue(1000, 'offline-issue-phase3-recovery-0001')->assertCreated();
        $this->acknowledgeInstrument((string) $root->json('data.reference'));
        $output = ExaPayOfflineValueOutput::query()->where('output_uuid', $root->json('data.instrument_id'))->firstOrFail();

        $claims = app(OfflineReconciliationClaimService::class);
        $claimData = $claims->submit($this->user, 'phase3-recovery-claim-idempotency-01', $output->output_uuid, 1000, []);
        $this->assertSame('VERIFIED', $claimData['status']);
        $claim = ExaPayOfflineReconciliationClaim::query()->where('claim_uuid', $claimData['claim_uuid'])->firstOrFail();
        $claims->transition($claim, \App\Domain\ExaPay\Offline\OfflineReconciliationClaimState::REDEMPTION_PENDING,
            'redemption_started', null, 'user', (int) $this->user->id, ['output_uuid' => $output->output_uuid]);

        $ledger = app(LedgerService::class);
        $reserveAccount = Account::query()->whereNull('user_id')->where('account_type', 'exapay_offline_reserve')->where('asset', 'SIMUSDT')->firstOrFail();
        $ledger->getOrCreateAccount((int) $this->user->id, 'funding', 'SIMUSDT');
        $funding = Account::query()->where('user_id', $this->user->id)->where('account_type', 'funding')->where('asset', 'SIMUSDT')->firstOrFail();
        $reference = 'EXAPAY-OFFLINE-RECOVERY-TEST-0001';
        $transaction = $ledger->postDoubleEntry($reference, 'Simulated offline redemption before recovery', [
            ['account_id' => $reserveAccount->id, 'amount' => '-10.00', 'asset' => 'SIMUSDT'],
            ['account_id' => $funding->id, 'amount' => '10.00', 'asset' => 'SIMUSDT', 'user_id' => (int) $this->user->id],
        ], 'exapay_offline_redemption', ['real_funds' => false]);
        $redemption = ExaPayOfflineRedemption::query()->create([
            'redemption_uuid' => (string) Str::uuid(), 'claim_id' => $claim->id,
            'output_uuid' => $output->output_uuid, 'user_id' => $this->user->id,
            'asset' => 'SIMUSDT', 'amount_minor' => 1000,
            'idempotency_key' => 'phase3-recovery-redemption-key-0001',
            'request_digest' => hash('sha256', 'phase3-recovery-test'),
            'ledger_reference' => $reference, 'status' => 'REDEMPTION_PENDING',
        ]);
        $beforeRecoveryBalance = $this->minor($ledger->getBalance((int) $this->user->id, 'SIMUSDT', 'funding'));
        $this->assertSame(10000, $beforeRecoveryBalance);

        $recovered = app(OfflineRedemptionService::class)->recover($redemption->redemption_uuid);
        $this->assertSame('REDEEMED', $recovered['status']);
        $this->assertSame($transaction->id, (int) $redemption->fresh()->ledger_transaction_id);
        $this->assertSame(10000, $this->minor($ledger->getBalance((int) $this->user->id, 'SIMUSDT', 'funding')));
        $this->assertSame(0, (int) ExaPayOfflineReserve::query()->value('outstanding_liability_minor'));
        $this->assertSame(1000, (int) ExaPayOfflineReserve::query()->value('redeemed_minor'));
        $this->assertSame('redeemed', $output->fresh()->status);

        $replayed = app(OfflineRedemptionService::class)->recover($redemption->redemption_uuid);
        $this->assertSame('REDEEMED', $replayed['status']);
        $this->assertSame(10000, $this->minor($ledger->getBalance((int) $this->user->id, 'SIMUSDT', 'funding')));
        $this->assertSame(2, LedgerEntry::query()->where('reference', $reference)->count());
    }

    public function test_cloned_conflicting_spend_quarantines_lineage_and_does_not_credit_recipient(): void
    {
        $this->fundSimulatedBalance(4000);
        $deviceA = $this->enrollDevice();
        $userB = User::factory()->create();
        $userD = User::factory()->create();
        [$deviceB, $privateB] = $this->enrollUserDevice($userB);
        [$deviceD, $privateD] = $this->enrollUserDevice($userD);
        $root = $this->issue(4000, 'offline-issue-cloned-spend-root-01')->assertCreated();
        $this->acknowledgeInstrument((string) $root->json('data.reference'));
        $rootOutput = ExaPayOfflineValueOutput::query()->where('output_uuid', $root->json('data.instrument_id'))->firstOrFail();
        $legitimate = $this->signedTransfer($deviceA, $deviceB, $this->devicePrivateKey, $privateB, $rootOutput->output_uuid, 4000, 3000, 1000, 2, 1, $rootOutput->lineage_digest, 'clone-spend-legit-key-01');
        $service = app(OfflineTransferReconciliationService::class);
        $service->reconcile($userB, [$legitimate]);
        $clone = $this->signedTransfer($deviceA, $deviceD, $this->devicePrivateKey, $privateD, $rootOutput->output_uuid, 4000, 2000, 2000, 2, 1, $rootOutput->lineage_digest, 'clone-spend-fraud-key-01');
        $conflict = $service->reconcile($userD, [$clone]);

        $this->assertSame('conflict_quarantined', $conflict['status']);
        $this->assertSame('quarantined', ExaPayOfflineTransferSession::query()->where('transfer_uuid', $legitimate['transfer_uuid'])->value('status'));
        $this->assertSame('quarantined', ExaPayOfflineValueOutput::query()->where('output_uuid', $rootOutput->output_uuid)->value('status'));
        $this->assertSame('quarantined', ExaPayOfflineValueOutput::query()->where('output_uuid', $legitimate['recipient_output_uuid'])->value('status'));
        $this->assertSame(0, $this->minor(app(LedgerService::class)->getBalance((int) $userD->id, 'SIMUSDT', 'funding')));
    }

    public function test_signed_five_device_chain_can_reconcile_one_hundred_consecutive_transfers(): void
    {
        $this->signRiskPolicy(ExaPayOfflineRiskPolicy::query()->whereKey(1)->firstOrFail(), ['enabled' => true, 'max_hops' => 100]);
        $this->fundSimulatedBalance(4000);
        $devices = [[$this->enrollDevice(), $this->devicePrivateKey, $this->user]];
        for ($i = 0; $i < 4; $i++) {
            $user = User::factory()->create();
            [$device, $private] = $this->enrollUserDevice($user);
            $devices[] = [$device, $private, $user];
        }
        $root = $this->issue(4000, 'offline-issue-hundred-chain-root-01')->assertCreated();
        $this->acknowledgeInstrument((string) $root->json('data.reference'));
        foreach ($devices as $tuple) $tuple[0]->refresh();
        $output = ExaPayOfflineValueOutput::query()->where('output_uuid', $root->json('data.instrument_id'))->firstOrFail();
        $inputUuid = (string) $output->output_uuid;
        $inputMinor = 4000;
        $parentDigest = (string) $output->lineage_digest;
        $counters = array_map(static fn (array $tuple): int => (int) $tuple[0]->device_counter, $devices);
        $chain = [];

        for ($i = 0; $i < 100; $i++) {
            $senderIndex = $i % 5;
            $recipientIndex = ($i + 1) % 5;
            [$sender, $senderPrivate] = $devices[$senderIndex];
            [$recipientDevice, $recipientPrivate] = $devices[$recipientIndex];
            $sendMinor = min(100, $inputMinor);
            $changeMinor = $inputMinor - $sendMinor;
            $entry = $this->signedTransfer($sender, $recipientDevice, $senderPrivate, $recipientPrivate,
                $inputUuid, $inputMinor, $sendMinor, $changeMinor, ++$counters[$senderIndex], $i + 1,
                $parentDigest, 'hundred-transfer-idem-' . str_pad((string) $i, 6, '0', STR_PAD_LEFT));
            $chain[] = $entry;
            $digest = $this->commitmentFromEntry($entry)->digest();
            $inputUuid = $entry['recipient_output_uuid'];
            $inputMinor = $sendMinor;
            $parentDigest = hash('sha256', $digest . '|recipient|' . $inputUuid);
        }

        $result = app(OfflineTransferReconciliationService::class)->reconcile($this->user, $chain);
        $this->assertSame('evidence_recorded', $result['status']);
        $this->assertCount(100, $result['transfers']);
        $this->assertSame(100, ExaPayOfflineTransferSession::query()->count());
        $this->assertSame(4000, (int) ExaPayOfflineValueOutput::query()->whereIn('status', ['available', 'locked', 'quarantined'])->sum('amount_minor'));
        $this->assertSame(4000, (int) ExaPayOfflineValueOutput::query()->where('status', 'available')->sum('amount_minor'));
    }

    public function test_transport_fragmentation_retransmission_and_interruption_recovery(): void
    {
        $adapter = new InMemoryOfflineTransferTransportAdapter();
        $message = str_repeat('{"protocol_version":1,"commitment":"signed-value"}', 4);
        $frames = $adapter->fragment((string) Str::uuid(), $message, 32);
        $this->assertGreaterThan(1, count($frames));
        $partial = array_slice($frames, 0, -1);
        try {
            $adapter->reassemble($partial);
            $this->fail('A partial transfer must not be accepted.');
        } catch (\App\Domain\ExaPay\Offline\OfflinePaymentException $exception) {
            $this->assertSame('transport_frames_incomplete', $exception->errorCode);
        }
        $retransmitted = [...$partial, $frames[0], $frames[count($frames) - 1]];
        $this->assertSame($message, $adapter->reassemble($retransmitted));
        $tampered = $frames;
        $tampered[0] = new \App\Domain\ExaPay\Offline\OfflineTransportFrame(
            $frames[0]->transferUuid, 0, $frames[0]->total, $frames[0]->digest, 'tampered-fragment',
        );
        try {
            $adapter->reassemble($tampered);
            $this->fail('A tampered fragmented message must be rejected.');
        } catch (\App\Domain\ExaPay\Offline\OfflinePaymentException $exception) {
            $this->assertSame('transport_digest_mismatch', $exception->errorCode);
        }
    }

    public function test_transfer_state_machine_never_cancels_or_refunds_after_commit(): void
    {
        $this->assertTrue(OfflineTransferState::PREPARED->mayTransitionTo(OfflineTransferState::CANCELLED));
        $this->assertFalse(OfflineTransferState::COMMITTED->mayTransitionTo(OfflineTransferState::CANCELLED));
        $this->assertTrue(OfflineTransferState::COMMITTED->mayTransitionTo(OfflineTransferState::RECOVERY_REQUIRED));
        $this->assertTrue(OfflineTransferState::RECOVERY_REQUIRED->mayTransitionTo(OfflineTransferState::ACKNOWLEDGED));
        $this->assertFalse(OfflineTransferState::ACKNOWLEDGED->mayTransitionTo(OfflineTransferState::CANCELLED));
    }

    public function test_signed_risk_policy_tampering_fails_closed(): void
    {
        $this->fundSimulatedBalance(1000);
        $this->enrollDevice();
        ExaPayOfflineRiskPolicy::query()->whereKey(1)->update(['max_amount_minor' => 9000]);

        $this->issue(100, 'offline-issue-tampered-policy-01')->assertStatus(503)->assertJsonPath('error_code', 'risk_policy_signature_invalid');
        $this->assertSame(1000, $this->minor(app(LedgerService::class)->getBalance((int) $this->user->id, 'SIMUSDT', 'funding')));
        $this->assertSame(0, ExaPayOfflineInstrument::query()->count());
    }

    public function test_device_exposure_and_emergency_suspension_fail_closed_without_releasing_liabilities(): void
    {
        $this->fundSimulatedBalance(15000);
        $device = $this->enrollDevice();
        $this->issue(8000, 'offline-phase4-device-limit-0001')->assertCreated();
        $this->issue(3000, 'offline-phase4-device-limit-0002')->assertStatus(422)
            ->assertJsonPath('error_code', 'device_exposure_limit_exceeded');

        $reserveBefore = (int) ExaPayOfflineReserve::query()->where('asset', 'SIMUSDT')->value('outstanding_liability_minor');
        ExaPayOfflineSecurityControl::query()->whereKey(1)->update(['issuance_suspended' => true, 'redemption_suspended' => true, 'trust_epoch' => 2]);
        $this->getJson('/api/exapay/offline/capabilities')->assertOk()
            ->assertJsonPath('data.offline_simulation_enabled', false)->assertJsonPath('data.redemption_enabled', false);
        $this->issue(100, 'offline-phase4-emergency-block-01')->assertStatus(503)
            ->assertJsonPath('error_code', 'offline_issuance_suspended');
        $this->assertSame($reserveBefore, (int) ExaPayOfflineReserve::query()->where('asset', 'SIMUSDT')->value('outstanding_liability_minor'));
        $this->assertTrue(app(OfflineExposureService::class)->snapshot()['accounting_conserved']);
        $this->assertSame('SOFTWARE_ONLY', $device->fresh()->trust_class);
    }

    public function test_unverified_hardware_claim_is_not_trusted_and_stale_epoch_is_recorded_as_fraud(): void
    {
        $device = $this->enrollDevice();
        $this->actingAs($this->user)->postJson('/api/exapay/offline/devices/' . $device->device_uuid . '/attestation', [
            'format' => 'strongbox', 'attestation' => ['claimed_hardware_backed' => true],
        ])->assertStatus(202)->assertJsonPath('data.attestation_status', 'NOT_VERIFIED')
            ->assertJsonPath('data.trust_class', 'SOFTWARE_ONLY');

        ExaPayOfflineSecurityControl::query()->whereKey(1)->update(['trust_epoch' => 2]);
        $this->submitJournal(1, '', [[
            'instrument_id' => (string) Str::uuid(), 'claim_id' => (string) Str::uuid(), 'consumed_at' => now()->timestamp,
        ]])->assertAccepted()->assertJsonPath('data.status', 'conflict')
            ->assertJsonPath('data.conflict_code', 'stale_trust_epoch');
        $this->assertDatabaseHas('exapay_offline_fraud_cases', [
            'reason_code' => 'STALE_DEVICE_TRUST_EPOCH', 'device_id' => $device->id, 'severity' => 'HIGH',
        ]);
        $this->assertSame('SOFTWARE_ONLY', $device->fresh()->trust_class);
    }

    public function test_duplicate_device_identity_with_a_different_key_is_quarantined_as_a_clone_case(): void
    {
        $device = $this->enrollDevice();
        $clonePrivate = $this->newPrivateKey();
        $clonePublic = (string) (openssl_pkey_get_details(openssl_pkey_get_private($clonePrivate))['key'] ?? '');
        $nonce = bin2hex(random_bytes(24));
        $signedAt = now()->timestamp;
        $message = implode('|', ['exaearn-offline-enroll:v1', (string) $this->user->id, $device->device_uuid, $nonce, (string) $signedAt]);
        openssl_sign($message, $signature, $clonePrivate, OPENSSL_ALGO_SHA256);

        try {
            app(OfflineStoredValueService::class)->enrollDevice($this->user, [
                'device_uuid' => $device->device_uuid, 'public_key' => $clonePublic,
                'nonce' => $nonce, 'signed_at' => $signedAt, 'signature' => base64_encode($signature),
            ]);
            $this->fail('A different public key must not take over an existing device identity.');
        } catch (\App\Domain\ExaPay\Offline\OfflinePaymentException $exception) {
            $this->assertSame('device_key_conflict', $exception->errorCode);
        }
        $this->assertDatabaseHas('exapay_offline_fraud_cases', ['reason_code' => 'CLONED_DEVICE_IDENTITY', 'severity' => 'CRITICAL']);
        $this->assertSame('active', $device->fresh()->status);
    }

    public function test_signed_policy_rollback_to_an_older_version_is_rejected(): void
    {
        $this->fundSimulatedBalance(1000);
        $this->enrollDevice();
        $policy = ExaPayOfflineRiskPolicy::query()->whereKey(1)->firstOrFail();
        $olderVersion = ExaPayOfflinePolicyVersion::query()->latest('policy_version')->firstOrFail();
        $olderPayload = json_decode((string) $olderVersion->canonical_payload, true, 512, JSON_THROW_ON_ERROR);
        $this->signRiskPolicy($policy, ['max_amount_minor' => 5000]);
        $policy->forceFill([
            'enabled' => $olderPayload['enabled'], 'max_amount_minor' => $olderPayload['max_amount_minor'],
            'max_outstanding_per_user_minor' => $olderPayload['max_outstanding_per_user_minor'],
            'max_devices_per_user' => $olderPayload['max_devices_per_user'], 'instrument_lifetime_seconds' => $olderPayload['instrument_lifetime_seconds'],
            'max_hops' => $olderPayload['max_hops'], 'max_transfers_per_journal' => $olderPayload['max_transfers_per_journal'],
            'max_cumulative_spending_minor' => $olderPayload['max_cumulative_spending_minor'],
            'minimum_protocol_version' => $olderPayload['minimum_protocol_version'], 'max_device_exposure_minor' => $olderPayload['max_device_exposure_minor'],
            'max_incoming_exposure_per_user_minor' => $olderPayload['max_incoming_exposure_per_user_minor'],
            'max_transactions_per_device' => $olderPayload['max_transactions_per_device'], 'max_journal_bytes' => $olderPayload['max_journal_bytes'],
            'supported_assets' => $olderPayload['supported_assets'], 'allowed_device_classes' => $olderPayload['allowed_device_classes'],
            'trust_epoch' => $olderPayload['trust_epoch'], 'require_enrolled_device' => $olderPayload['require_enrolled_device'],
            'policy_version' => $olderVersion->policy_version, 'policy_digest' => $olderVersion->policy_digest,
            'policy_signature' => $olderVersion->policy_signature, 'policy_public_key' => $olderVersion->issuer_public_key,
            'policy_expires_at' => $olderVersion->expires_at,
        ])->save();

        $this->issue(100, 'offline-phase4-policy-rollback-01')->assertStatus(503)
            ->assertJsonPath('error_code', 'risk_policy_downgrade_detected');
    }

    public function test_key_rotation_revokes_old_key_and_preserves_versioned_audit_history(): void
    {
        $this->fundSimulatedBalance(1000);
        $device = $this->enrollDevice();
        $userB = User::factory()->create();
        [$deviceB, $privateB] = $this->enrollUserDevice($userB);
        $root = $this->issue(1000, 'offline-issue-key-rotation-root-01')->assertCreated();
        $this->acknowledgeInstrument((string) $root->json('data.reference'));
        $rootOutput = ExaPayOfflineValueOutput::query()->where('output_uuid', $root->json('data.instrument_id'))->firstOrFail();
        $newPrivate = $this->newPrivateKey();
        $newPublic = (string) (openssl_pkey_get_details(openssl_pkey_get_private($newPrivate))['key'] ?? '');
        $fingerprint = hash('sha256', preg_replace('/\s+/', '', $newPublic) ?: $newPublic);
        $nonce = bin2hex(random_bytes(24));
        $signedAt = now()->timestamp;
        $message = implode('|', ['exaearn-offline-key-rotate:v1', $device->device_uuid, $fingerprint, '2', $nonce, (string) $signedAt]);
        openssl_sign($message, $signature, $this->devicePrivateKey, OPENSSL_ALGO_SHA256);

        $rotated = app(OfflineStoredValueService::class)->rotateDeviceKey($this->user, $this->deviceUuid, $newPublic, 2, $nonce, $signedAt, base64_encode($signature));
        $this->assertSame(2, (int) $rotated->key_version);
        $this->assertSame('revoked', ExaPayOfflineDeviceKeyHistory::query()->where('device_id', $device->id)->where('key_version', 1)->value('status'));
        $this->assertSame('active', ExaPayOfflineDeviceKeyHistory::query()->where('device_id', $device->id)->where('key_version', 2)->value('status'));
        $this->assertNotNull($rotated->key_rotated_at);

        $staleDevice = $rotated->replicate();
        $staleDevice->key_version = 1;
        $stale = $this->signedTransfer($staleDevice, $deviceB, $this->devicePrivateKey, $privateB, $rootOutput->output_uuid,
            1000, 500, 500, 2, 1, $rootOutput->lineage_digest, 'key-rotation-stale-commit-01');
        try {
            app(OfflineTransferReconciliationService::class)->reconcile($userB, [$stale]);
            $this->fail('A revoked device key must not authenticate a post-rotation transfer.');
        } catch (\App\Domain\ExaPay\Offline\OfflinePaymentException $exception) {
            $this->assertSame('transfer_key_version_invalid', $exception->errorCode);
        }
        $this->assertSame('available', $rootOutput->fresh()->status);
    }

    private function fundSimulatedBalance(int $amountMinor): void
    {
        $ledger = app(LedgerService::class);
        $funding = $ledger->getOrCreateAccount((int) $this->user->id, 'funding', 'SIMUSDT');
        $simulatedIssuer = $ledger->getOrCreateAccount(null, 'test_simulation_issuer', 'SIMUSDT');
        $amount = $this->decimal($amountMinor);
        $ledger->postDoubleEntry('TEST-SIM-FUND-' . Str::uuid(), 'Test-only SIMUSDT seed', [
            ['account_id' => $simulatedIssuer->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => 'SIMUSDT'],
            ['account_id' => $funding->id, 'amount' => $amount, 'asset' => 'SIMUSDT', 'user_id' => (int) $this->user->id],
        ], 'test_simulation_fund', ['source_service' => 'test_fixture', 'real_funds' => false]);
    }

    private function signRiskPolicy(ExaPayOfflineRiskPolicy $policy, array $changes = []): void
    {
        $policy->fill($changes);
        $policy->policy_version = max(1, (int) $policy->policy_version + 1);
        $policy->policy_expires_at = now()->addHours(24);
        $contract = new OfflineRiskPolicyContract(
            (int) $policy->policy_version, (bool) $policy->enabled, (int) $policy->max_amount_minor,
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

    /** @return array{0:ExaPayOfflineDevice,1:string} */
    private function enrollUserDevice(User $user): array
    {
        $private = $this->newPrivateKey();
        $key = openssl_pkey_get_private($private);
        $this->assertNotFalse($key);
        $public = (string) (openssl_pkey_get_details($key)['key'] ?? '');
        $uuid = (string) Str::uuid();
        $nonce = bin2hex(random_bytes(24));
        $signedAt = now()->timestamp;
        $message = implode('|', ['exaearn-offline-enroll:v1', (string) $user->id, $uuid, $nonce, (string) $signedAt]);
        openssl_sign($message, $signature, $private, OPENSSL_ALGO_SHA256);
        $device = app(OfflineStoredValueService::class)->enrollDevice($user, [
            'device_uuid' => $uuid, 'public_key' => $public, 'nonce' => $nonce, 'signed_at' => $signedAt,
            'signature' => base64_encode($signature),
        ]);

        return [$device->refresh(), (string) $private];
    }

    private function signedTransfer(
        ExaPayOfflineDevice $sender,
        ExaPayOfflineDevice $recipient,
        string $senderPrivate,
        string $recipientPrivate,
        string $inputUuid,
        int $inputMinor,
        int $sendMinor,
        int $changeMinor,
        int $counter,
        int $hop,
        string $parentDigest,
        string $idempotencyKey,
    ): array {
        $contract = new OfflineTransferCommitment(
            (string) Str::uuid(), (string) $sender->device_uuid, (string) $recipient->device_uuid,
            (int) $sender->key_version, (int) $recipient->key_version,
            $inputUuid, (string) Str::uuid(), $changeMinor > 0 ? (string) Str::uuid() : null,
            $inputMinor, $sendMinor, $changeMinor, $counter, 1, $hop, bin2hex(random_bytes(24)),
            now()->timestamp, $parentDigest, $idempotencyKey,
        );
        openssl_sign($contract->canonicalPayload(), $senderSignature, $senderPrivate, OPENSSL_ALGO_SHA256);
        $senderSignature = base64_encode($senderSignature);
        openssl_sign($contract->recipientChallenge($senderSignature), $recipientSignature, $recipientPrivate, OPENSSL_ALGO_SHA256);

        return $contract->canonicalArray() + [
            'sender_signature' => $senderSignature,
            'recipient_signature' => base64_encode($recipientSignature),
        ];
    }

    private function commitmentFromEntry(array $entry): OfflineTransferCommitment
    {
        return new OfflineTransferCommitment(
            $entry['transfer_uuid'], $entry['sender_device_uuid'], $entry['recipient_device_uuid'],
            $entry['sender_key_version'], $entry['recipient_key_version'], $entry['input_output_uuid'],
            $entry['recipient_output_uuid'], $entry['change_output_uuid'], $entry['input_amount_minor'],
            $entry['transfer_amount_minor'], $entry['change_amount_minor'], $entry['transfer_counter'],
            $entry['protocol_version'], $entry['hop_count'], $entry['nonce'], $entry['signed_at'],
            $entry['parent_digest'], $entry['idempotency_key'], $entry['asset'],
        );
    }

    private function enrollDevice(): ExaPayOfflineDevice
    {
        $proof = $this->deviceProof('exaearn-offline-enroll:v1|' . $this->user->id . '|' . $this->deviceUuid . '|');
        $response = $this->actingAs($this->user)->postJson('/api/exapay/offline/devices/enroll', [
            'device_uuid' => $this->deviceUuid, 'public_key' => $this->devicePublicKey,
        ] + $proof)->assertCreated()->assertJsonPath('data.real_funds_enabled', false);
        $certificate = $response->json('data.device_certificate');
        $this->assertIsArray($certificate);
        $certificatePayload = json_decode((string) $certificate['canonical_payload'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($this->deviceUuid, $certificatePayload['device_uuid']);
        $this->assertSame('SIMUSDT', $certificatePayload['asset']);
        $this->assertTrue($certificatePayload['simulation_only']);
        $this->assertSame(1, openssl_verify(
            (string) $certificate['canonical_payload'],
            (string) base64_decode((string) $certificate['signature'], true),
            (string) $certificate['issuer_public_key'],
            OPENSSL_ALGO_SHA256,
        ));

        return ExaPayOfflineDevice::query()->where('device_uuid', $this->deviceUuid)->firstOrFail();
    }

    private function acknowledgeInstrument(string $reference): void
    {
        $proof = $this->deviceProof('exaearn-offline-ack:v1|' . $reference . '|' . $this->deviceUuid . '|');
        app(OfflineStoredValueService::class)->acknowledge($this->user, $reference, $proof);
    }

    private function issue(int $amountMinor, string $key)
    {
        return $this->actingAs($this->user)->withHeader('Idempotency-Key', $key)->postJson('/api/exapay/offline/instruments', [
            'device_uuid' => $this->deviceUuid, 'amount_minor' => $amountMinor, 'asset' => 'SIMUSDT',
        ]);
    }

    private function submitJournal(int $counter, string $previousDigest, array $claims)
    {
        return $this->actingAs($this->user)->postJson('/api/exapay/offline/journals', $this->journalPayload($counter, $previousDigest, $claims));
    }

    private function journalPayload(int $counter, string $previousDigest, array $claims, ?int $signedAt = null): array
    {
        $nonce = bin2hex(random_bytes(24));
        $signedAt ??= now()->timestamp;
        $contract = new OfflineJournalContract($this->deviceUuid, $counter, $previousDigest, $nonce, $signedAt, $claims, '', 1);

        return [
            'device_uuid' => $this->deviceUuid, 'counter' => $counter, 'previous_digest' => $previousDigest,
            'protocol_version' => 1, 'nonce' => $nonce, 'signed_at' => $signedAt,
            'consumption_claims' => $claims, 'signature' => $this->sign($contract->canonicalPayload()),
        ];
    }

    private function deviceProof(string $prefix): array
    {
        $nonce = bin2hex(random_bytes(24));
        $signedAt = now()->timestamp;

        return ['device_uuid' => $this->deviceUuid, 'nonce' => $nonce, 'signed_at' => $signedAt, 'signature' => $this->sign($prefix . $nonce . '|' . $signedAt)];
    }

    private function sign(string $message): string
    {
        openssl_sign($message, $signature, $this->devicePrivateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    private function newPrivateKey(): string
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);

        return (string) $privateKey;
    }

    private function newDeviceKeyPair(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);
        $this->devicePrivateKey = (string) $privateKey;
        $details = openssl_pkey_get_details($key);
        $this->devicePublicKey = (string) ($details['key'] ?? '');
        $this->deviceUuid = (string) Str::uuid();
    }

    private function minor(string $amount): int
    {
        $normalized = FinancialDecimal::normalize($amount, 2);
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '00');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function decimal(int $minor): string
    {
        return intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
