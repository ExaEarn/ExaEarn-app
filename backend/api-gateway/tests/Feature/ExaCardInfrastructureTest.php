<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Admin;
use App\Models\Card;
use App\Models\CardAuthorization;
use App\Models\CardDispute;
use App\Models\CardFundingRequest;
use App\Models\CardProviderBalance;
use App\Models\CardTransaction;
use App\Models\CardWebhookEvent;
use App\Models\DeveloperRealtimeEvent;
use App\Models\FinanceFinancialEvent;
use App\Models\LedgerTransaction;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\SreOperationalAlert;
use App\Models\User;
use App\Services\Cards\CardReconciliationService;
use App\Services\Cards\CardRealtimeService;
use App\Services\Cards\CardTreasuryService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExaCardInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('security-ratelimit.enabled', false);
        Config::set('exacard.provider_mode', 'sandbox');
        Config::set('exacard.production_issuance_enabled', false);
    }

    public function test_products_and_virtual_card_issuance_are_provider_backed_and_idempotent(): void
    {
        $user = $this->verifiedUser();

        $this->actingAs($user)->getJson('/api/cards/products')
            ->assertOk()
            ->assertJsonPath('data.provider.mode', 'SANDBOX');

        $first = $this->actingAs($user)->withHeader('Idempotency-Key', 'issue-card-1')->postJson('/api/cards', [
            'product_code' => 'USD_VIRTUAL',
            'nickname' => 'Main spending',
        ])->assertCreated()->json('data');

        $second = $this->actingAs($user)->withHeader('Idempotency-Key', 'issue-card-1')->postJson('/api/cards', [
            'product_code' => 'USD_VIRTUAL',
            'nickname' => 'Duplicate request',
        ])->assertCreated()->json('data');

        $this->assertSame($first['card_uuid'], $second['card_uuid']);
        $this->assertSame(1, Card::query()->count());
        $this->assertDatabaseHas('card_audit_logs', ['action' => 'CARD_ISSUED']);
        $this->assertSame(1, DeveloperRealtimeEvent::query()->where('user_id', $user->id)->where('stream', CardRealtimeService::STREAM)->where('event_type', 'card.created')->count());
        $this->assertSame(1, Notification::query()->where('user_id', $user->id)->where('type', 'exacard.card.created')->count());
        $this->assertArrayNotHasKey('pan', $first);
        $this->assertArrayNotHasKey('cvv', $first);
    }

    public function test_physical_card_is_not_issued_without_real_provider_enablement(): void
    {
        $this->actingAs($this->verifiedUser())->withHeader('Idempotency-Key', 'physical-1')->postJson('/api/cards', [
            'product_code' => 'PHYSICAL',
        ])->assertUnprocessable();

        $this->assertSame(0, Card::query()->count());
    }

    public function test_card_funding_reserves_then_settles_through_canonical_ledger(): void
    {
        $user = $this->verifiedUser();
        $this->fundUser($user, 'USD', '1000');
        $card = $this->issueCard($user);

        $quote = $this->actingAs($user)->postJson("/api/cards/{$card['card_uuid']}/funding-quotes", [
            'source_asset' => 'USD',
            'amount' => '100',
        ])->assertCreated()->json('data');

        $funding = $this->actingAs($user)->withHeader('Idempotency-Key', 'fund-card-1')->postJson('/api/cards/funding-requests', [
            'quote_uuid' => $quote['quote_uuid'],
        ])->assertCreated()->json('data');

        $duplicate = $this->actingAs($user)->withHeader('Idempotency-Key', 'fund-card-1')->postJson('/api/cards/funding-requests', [
            'quote_uuid' => $quote['quote_uuid'],
        ])->assertCreated()->json('data');

        $this->assertSame($funding['funding_uuid'], $duplicate['funding_uuid']);
        $this->assertSame('COMPLETED', $funding['status']);
        $this->assertSame('900.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('account_type', 'funding')->where('asset', 'USD')->value('balance'));
        $this->assertSame('100.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('account_type', 'exacard')->where('asset', 'USD')->value('balance'));
        $this->assertSame(1, LedgerTransaction::query()->where('transaction_type', 'card_funding')->count());
        $this->assertSame(1, FinanceFinancialEvent::query()->where('event_type', 'CARD_FUNDED')->count());
        $this->assertSame(Reservation::STATUS_CONSUMED, Reservation::query()->value('status'));
        $this->assertSame(1, DeveloperRealtimeEvent::query()->where('user_id', $user->id)->where('event_type', 'card.funding.completed')->count());
        $this->assertSame(1, Notification::query()->where('user_id', $user->id)->where('type', 'exacard.funding.completed')->count());
    }

    public function test_failed_provider_funding_releases_reservation_without_ledger_debit(): void
    {
        $user = $this->verifiedUser();
        $this->fundUser($user, 'USD', '1000');
        $card = $this->issueCard($user);
        $quote = $this->actingAs($user)->postJson("/api/cards/{$card['card_uuid']}/funding-quotes", [
            'source_asset' => 'USD',
            'amount' => '100',
        ])->assertCreated()->json('data');

        $this->actingAs($user)->withHeader('Idempotency-Key', 'fund-card-fail')->postJson('/api/cards/funding-requests', [
            'quote_uuid' => $quote['quote_uuid'],
            'test_behavior' => 'FAILED',
        ])->assertAccepted()->assertJsonPath('data.status', 'FAILED');

        $this->assertSame('1000.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('account_type', 'funding')->where('asset', 'USD')->value('balance'));
        $this->assertSame(Reservation::STATUS_RELEASED, Reservation::query()->value('status'));
        $this->assertSame(0, LedgerTransaction::query()->where('transaction_type', 'card_funding')->count());
        $this->assertSame(1, DeveloperRealtimeEvent::query()->where('user_id', $user->id)->where('event_type', 'card.funding.failed')->count());
        $this->assertSame(1, Notification::query()->where('user_id', $user->id)->where('type', 'exacard.funding.failed')->count());
    }

    public function test_unknown_provider_funding_keeps_reservation_for_reconciliation_without_ledger_debit(): void
    {
        $user = $this->verifiedUser();
        $this->fundUser($user, 'USD', '1000');
        $card = $this->issueCard($user);
        $quote = $this->actingAs($user)->postJson("/api/cards/{$card['card_uuid']}/funding-quotes", [
            'source_asset' => 'USD',
            'amount' => '100',
        ])->assertCreated()->json('data');

        $this->actingAs($user)->withHeader('Idempotency-Key', 'fund-card-unknown')->postJson('/api/cards/funding-requests', [
            'quote_uuid' => $quote['quote_uuid'],
            'test_behavior' => 'UNKNOWN',
        ])->assertAccepted()->assertJsonPath('data.status', 'PROVIDER_UNKNOWN');

        $this->assertSame('1000.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('account_type', 'funding')->where('asset', 'USD')->value('balance'));
        $this->assertSame(Reservation::STATUS_ACTIVE, Reservation::query()->value('status'));
        $this->assertSame(0, LedgerTransaction::query()->where('transaction_type', 'card_funding')->count());
        $this->assertSame(1, DeveloperRealtimeEvent::query()->where('user_id', $user->id)->where('event_type', 'card.funding.provider_pending')->count());
        $this->assertSame(1, Notification::query()->where('user_id', $user->id)->where('type', 'exacard.funding.provider_unknown')->count());
    }

    public function test_card_unload_moves_card_balance_back_to_funding(): void
    {
        $user = $this->verifiedUser();
        $this->fundUser($user, 'USD', '1000');
        $card = $this->issueCard($user);
        $quote = $this->actingAs($user)->postJson("/api/cards/{$card['card_uuid']}/funding-quotes", [
            'source_asset' => 'USD',
            'amount' => '100',
        ])->assertCreated()->json('data');
        $this->actingAs($user)->withHeader('Idempotency-Key', 'fund-card-unload')->postJson('/api/cards/funding-requests', [
            'quote_uuid' => $quote['quote_uuid'],
        ])->assertCreated();

        $unload = $this->actingAs($user)->withHeader('Idempotency-Key', 'unload-card-1')->postJson("/api/cards/{$card['card_uuid']}/unload", [
            'amount' => '40',
        ])->assertCreated()->json('data');

        $this->assertSame('COMPLETED', $unload['status']);
        $this->assertSame('940.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('account_type', 'funding')->where('asset', 'USD')->value('balance'));
        $this->assertSame('60.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('account_type', 'exacard')->where('asset', 'USD')->value('balance'));
        $this->assertSame(1, DeveloperRealtimeEvent::query()->where('user_id', $user->id)->where('event_type', 'card.unload.completed')->count());
    }

    public function test_card_webhooks_are_signed_and_idempotent(): void
    {
        $user = $this->verifiedUser();
        $issued = $this->issueCard($user);
        $card = Card::query()->where('card_uuid', $issued['card_uuid'])->firstOrFail();
        $payload = [
            'event_id' => 'evt-card-capture-1',
            'event_type' => 'TRANSACTION.CAPTURED',
            'provider_card_id' => $card->provider_card_id,
            'transaction_id' => 'provider-tx-1',
            'amount' => '12.50',
            'currency' => 'USD',
            'merchant' => 'Coffee Desk',
        ];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $raw, (string) config('exacard.webhook_secret'));

        $this->postJson('/api/webhooks/cards/fake', $payload, ['X-ExaCard-Signature' => 'bad'])
            ->assertUnauthorized();

        $this->postJson('/api/webhooks/cards/fake', $payload, ['X-ExaCard-Signature' => $signature])
            ->assertOk()
            ->assertJsonPath('data.status', 'PROCESSED');
        $this->postJson('/api/webhooks/cards/fake', $payload, ['X-ExaCard-Signature' => $signature])
            ->assertOk();

        $this->assertSame(1, CardWebhookEvent::query()->count());
        $this->assertSame(1, CardTransaction::query()->where('provider_transaction_id', 'provider-tx-1')->count());
        $this->assertSame(1, DeveloperRealtimeEvent::query()->where('user_id', $user->id)->where('event_type', 'card.transaction.completed')->count());
        $this->assertSame(1, Notification::query()->where('user_id', $user->id)->where('type', 'exacard.purchase.approved')->count());
    }

    public function test_authorization_chargeback_and_transaction_history_are_operational(): void
    {
        $user = $this->verifiedUser();
        $issued = $this->issueCard($user);
        $card = Card::query()->where('card_uuid', $issued['card_uuid'])->firstOrFail();

        $this->sendSignedWebhook([
            'event_id' => 'evt-auth-1',
            'event_type' => 'AUTHORIZATION.APPROVED',
            'provider_card_id' => $card->provider_card_id,
            'authorization_id' => 'provider-auth-1',
            'amount' => '21.10',
            'currency' => 'USD',
            'merchant' => 'Terminal Labs',
        ])->assertOk();

        $this->sendSignedWebhook([
            'event_id' => 'evt-chargeback-1',
            'event_type' => 'CHARGEBACK.CREATED',
            'provider_card_id' => $card->provider_card_id,
            'transaction_id' => 'provider-chargeback-tx-1',
            'dispute_id' => 'provider-dispute-1',
            'amount' => '21.10',
            'currency' => 'USD',
            'merchant' => 'Terminal Labs',
        ])->assertOk();

        $this->actingAs($user)->getJson("/api/cards/{$issued['card_uuid']}/authorizations")
            ->assertOk()
            ->assertJsonPath('data.0.merchant', 'Terminal Labs');
        $this->actingAs($user)->getJson("/api/cards/{$issued['card_uuid']}/transactions")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'CHARGEBACK.CREATED');

        $this->assertSame(1, CardAuthorization::query()->where('provider_authorization_id', 'provider-auth-1')->count());
        $this->assertSame(1, CardDispute::query()->where('provider_dispute_id', 'provider-dispute-1')->count());
        $this->assertSame(1, DeveloperRealtimeEvent::query()->where('user_id', $user->id)->where('event_type', 'card.authorization.updated')->count());
        $this->assertSame(1, DeveloperRealtimeEvent::query()->where('user_id', $user->id)->where('event_type', 'card.chargeback.updated')->count());
        $this->assertSame(1, Notification::query()->where('user_id', $user->id)->where('type', 'exacard.dispute.updated')->count());
    }

    public function test_controls_treasury_and_reconciliation_are_operational(): void
    {
        $user = $this->verifiedUser();
        $card = $this->issueCard($user);

        $this->actingAs($user)->putJson("/api/cards/{$card['card_uuid']}/controls", [
            'online' => false,
            'international' => true,
        ])->assertOk()->assertJsonPath('data.controls.online', false);

        app(CardTreasuryService::class)->upsertProviderBalance('fake', 'USD', '10000', '1000', '20000');
        $run = app(CardReconciliationService::class)->run();

        $this->assertSame('PASS', $run->status);
        $this->assertSame(1, CardProviderBalance::query()->count());
    }

    public function test_details_token_lost_stolen_termination_and_account_closure_blockers(): void
    {
        $user = $this->verifiedUser();
        $card = $this->issueCard($user);

        $details = $this->actingAs($user)->postJson("/api/cards/{$card['card_uuid']}/details-token")
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('token', $details);
        $this->assertArrayNotHasKey('pan', $details);
        $this->assertArrayNotHasKey('cvv', $details);

        $this->actingAs($user)->postJson("/api/cards/{$card['card_uuid']}/report-lost-stolen", [
            'reason' => 'Card is no longer under my control.',
        ])->assertOk()->assertJsonPath('data.status', 'BLOCKED');

        $this->actingAs($user)->postJson("/api/cards/{$card['card_uuid']}/terminate", [
            'reason' => 'Replacing compromised card.',
        ])->assertOk()->assertJsonPath('data.status', 'TERMINATED');

        $this->assertSame([], app(\App\Services\Cards\CardService::class)->accountClosureBlockers($user));
        $this->assertDatabaseHas('card_audit_logs', ['action' => 'CARD_LOST_OR_STOLEN_REPORTED']);
        $this->assertDatabaseHas('card_audit_logs', ['action' => 'CARD_TERMINATED']);
        $this->assertSame(1, DeveloperRealtimeEvent::query()->where('user_id', $user->id)->where('event_type', 'card.blocked')->count());
        $this->assertSame(1, Notification::query()->where('user_id', $user->id)->where('type', 'exacard.card.status.blocked')->count());
    }

    public function test_termination_is_blocked_until_card_balance_is_unloaded(): void
    {
        $user = $this->verifiedUser();
        $this->fundUser($user, 'USD', '1000');
        $card = $this->issueCard($user);
        $quote = $this->actingAs($user)->postJson("/api/cards/{$card['card_uuid']}/funding-quotes", [
            'source_asset' => 'USD',
            'amount' => '25',
        ])->assertCreated()->json('data');
        $this->actingAs($user)->withHeader('Idempotency-Key', 'fund-before-terminate')->postJson('/api/cards/funding-requests', [
            'quote_uuid' => $quote['quote_uuid'],
        ])->assertCreated();

        $this->actingAs($user)->postJson("/api/cards/{$card['card_uuid']}/terminate", [
            'reason' => 'Close card after balance check.',
        ])->assertUnprocessable();

        $blockers = app(\App\Services\Cards\CardService::class)->accountClosureBlockers($user);
        $this->assertContains('CARD_BALANCE_REMAINING', $blockers[$card['card_uuid']]);
    }

    public function test_admin_operations_expose_card_program_visibility_and_rebalance_required(): void
    {
        $user = $this->verifiedUser();
        $this->issueCard($user);
        app(CardTreasuryService::class)->upsertProviderBalance('fake', 'USD', '50', '1000', '5000');

        $admin = $this->admin();
        foreach (['overview', 'customers', 'cards', 'transactions', 'funding', 'disputes', 'treasury', 'providers', 'revenue', 'audit-logs'] as $path) {
            $this->actingAs($admin)->getJson("/api/admin/v1/exacard/{$path}")->assertOk();
        }

        $this->assertSame('REBALANCE_REQUIRED', CardProviderBalance::query()->where('provider', 'fake')->where('currency', 'USD')->value('status'));
        $this->assertSame(1, SreOperationalAlert::query()->where('alert_key', 'CARD_PROVIDER_BALANCE_LOW:fake:USD')->where('status', 'OPEN')->count());
    }

    public function test_card_realtime_replay_is_user_scoped_sequenced_and_gap_aware(): void
    {
        $user = $this->verifiedUser();
        $other = $this->verifiedUser();
        $this->issueCard($user);
        $this->issueCard($other);

        $events = DeveloperRealtimeEvent::query()->where('user_id', $user->id)->where('stream', CardRealtimeService::STREAM)->orderBy('sequence')->get();
        $this->assertSame([1], $events->pluck('sequence')->all());

        $this->actingAs($user)->getJson('/api/cards/realtime/replay?after_sequence=0')
            ->assertOk()
            ->assertJsonPath('data.events.0.sequence', 1)
            ->assertJsonPath('data.events.0.event_type', 'card.created')
            ->assertJsonMissing(['user_id' => $other->id]);

        $this->actingAs($other)->getJson('/api/cards/realtime/replay?after_sequence=0')
            ->assertOk()
            ->assertJsonPath('data.events.0.sequence', 1);

        DeveloperRealtimeEvent::query()->where('user_id', $user->id)->update(['sequence' => 3]);
        $this->actingAs($user)->getJson('/api/cards/realtime/replay?after_sequence=0')
            ->assertOk()
            ->assertJsonPath('data.gap_detected', true)
            ->assertJsonPath('data.reconcile_required', true);
    }

    public function test_card_notification_deduplication_prevents_duplicate_webhook_messages(): void
    {
        $user = $this->verifiedUser();
        $issued = $this->issueCard($user);
        $card = Card::query()->where('card_uuid', $issued['card_uuid'])->firstOrFail();
        $payload = [
            'event_id' => 'evt-card-capture-dedup',
            'event_type' => 'TRANSACTION.CAPTURED',
            'provider_card_id' => $card->provider_card_id,
            'transaction_id' => 'provider-tx-dedup',
            'amount' => '18.75',
            'currency' => 'USD',
            'merchant' => 'Book Store',
        ];

        $this->sendSignedWebhook($payload)->assertOk();
        $this->sendSignedWebhook($payload)->assertOk();

        $this->assertSame(1, Notification::query()->where('user_id', $user->id)->where('type', 'exacard.purchase.approved')->count());
        $this->assertSame(1, DeveloperRealtimeEvent::query()->where('user_id', $user->id)->where('event_type', 'card.transaction.completed')->count());
    }

    public function test_webhook_failure_and_reconciliation_break_create_operations_alerts(): void
    {
        $this->postJson('/api/webhooks/cards/fake', ['event_id' => 'bad'], ['X-ExaCard-Signature' => 'bad'])->assertUnauthorized();
        $this->assertSame(1, SreOperationalAlert::query()->where('alert_key', 'like', 'CARD_WEBHOOK_FAILURE:fake:%')->count());

        $user = $this->verifiedUser();
        $this->fundUser($user, 'USD', '20');
        Account::query()->create([
            'owner_type' => 'user',
            'owner_id' => $user->id,
            'user_id' => $user->id,
            'account_type' => 'exacard',
            'asset' => 'USD',
            'balance' => '10',
            'status' => 'active',
        ]);
        app(CardReconciliationService::class)->run();

        $this->assertSame(1, SreOperationalAlert::query()->where('alert_key', 'CARD_RECONCILIATION_BREAK:USD')->count());
        $this->assertSame(2, DeveloperRealtimeEvent::query()->whereNull('user_id')->where('stream', CardRealtimeService::ADMIN_STREAM)->count());
    }

    public function test_financial_invariants_survive_notification_provider_failure(): void
    {
        $user = $this->verifiedUser();
        $this->fundUser($user, 'USD', '1000');
        $card = $this->issueCard($user);
        $quote = $this->actingAs($user)->postJson("/api/cards/{$card['card_uuid']}/funding-quotes", [
            'source_asset' => 'USD',
            'amount' => '100',
        ])->assertCreated()->json('data');

        $this->app->bind(NotificationService::class, fn () => new class extends NotificationService {
            public function create(
                User|int $user,
                string $type,
                string $title,
                string $message,
                array $channels = ['in_app', 'email', 'push'],
                ?array $data = null
            ): Notification {
                throw new \RuntimeException('notification provider unavailable');
            }
        });

        $this->actingAs($user)->withHeader('Idempotency-Key', 'fund-card-notify-fail')->postJson('/api/cards/funding-requests', [
            'quote_uuid' => $quote['quote_uuid'],
        ])->assertCreated()->assertJsonPath('data.status', 'COMPLETED');

        $this->assertSame(1, LedgerTransaction::query()->where('transaction_type', 'card_funding')->count());
        $this->assertSame('900.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('account_type', 'funding')->where('asset', 'USD')->value('balance'));
        $this->assertSame('100.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('account_type', 'exacard')->where('asset', 'USD')->value('balance'));
        $this->assertSame(1, DeveloperRealtimeEvent::query()->where('user_id', $user->id)->where('event_type', 'card.funding.completed')->count());
    }

    private function verifiedUser(): User
    {
        return User::factory()->create([
            'kyc_level' => 1,
            'verified_country' => 'US',
            'residence_country' => 'US',
            'account_status' => 'FULLY_ACTIVE',
        ]);
    }

    private function fundUser(User $user, string $asset, string $amount): void
    {
        Account::query()->create([
            'owner_type' => 'user',
            'owner_id' => $user->id,
            'user_id' => $user->id,
            'account_type' => 'funding',
            'asset' => strtoupper($asset),
            'balance' => $amount,
            'status' => 'active',
        ]);
    }

    private function issueCard(User $user): array
    {
        return $this->actingAs($user)->withHeader('Idempotency-Key', 'issue-card-'.uniqid())->postJson('/api/cards', [
            'product_code' => 'USD_VIRTUAL',
        ])->assertCreated()->json('data');
    }

    private function admin(): Admin
    {
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);

        return Admin::query()->create([
            'name' => 'ExaCard Admin',
            'email' => 'exacard-admin-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }

    private function sendSignedWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $raw, (string) config('exacard.webhook_secret'));

        return $this->postJson('/api/webhooks/cards/fake', $payload, ['X-ExaCard-Signature' => $signature]);
    }
}
