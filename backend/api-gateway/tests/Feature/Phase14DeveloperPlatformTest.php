<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DeveloperApiKey;
use App\Models\DeveloperApiNonce;
use App\Models\DeveloperApiRequestLog;
use App\Models\DeveloperAuditLog;
use App\Models\DeveloperProject;
use App\Models\DeveloperRealtimeEvent;
use App\Models\DeveloperSandboxBalance;
use App\Models\DeveloperWebhookDelivery;
use App\Models\DeveloperWebhookEndpoint;
use App\Models\User;
use App\Models\Wallet;
use App\Services\DeveloperApiKeyService;
use App\Services\DeveloperRealtimeService;
use App\Services\DeveloperWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase14DeveloperPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_create_project_and_api_key_with_secret_shown_once(): void
    {
        $user = User::factory()->create();

        $projectResponse = $this->actingAs($user)->postJson('/api/developer/projects', [
            'name' => 'Trading Bot Sandbox',
            'environment' => 'sandbox',
        ]);

        $projectResponse->assertCreated()->assertJsonPath('success', true);
        $project = DeveloperProject::query()->firstOrFail();

        $keyResponse = $this->actingAs($user)->postJson("/api/developer/projects/{$project->id}/keys", [
            'name' => 'Readonly key',
            'permissions' => ['account.read', 'market.read'],
            'passphrase' => 'desk-passphrase',
        ]);

        $keyResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['api_key', 'api_secret', 'key' => ['id', 'permissions']]]);

        $this->assertSame(1, DeveloperApiKey::query()->count());
        $this->assertSame(2, DeveloperApiKey::query()->firstOrFail()->permissions()->count());
        $this->assertSame(2, DeveloperAuditLog::query()->count());
    }

    public function test_withdrawal_permission_requires_ip_whitelist(): void
    {
        $user = User::factory()->create();
        $project = app(DeveloperApiKeyService::class)->createProject($user->id, [
            'name' => 'Production OMS',
            'environment' => 'production',
        ]);

        $response = $this->actingAs($user)->postJson("/api/developer/projects/{$project->id}/keys", [
            'name' => 'Withdrawal key',
            'permissions' => ['wallet.withdraw'],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('IP whitelist', (string) $response->json('message'));
    }

    public function test_sandbox_faucet_credits_sandbox_balance_without_touching_real_wallet(): void
    {
        $user = User::factory()->create();
        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '12.00000000',
            'locked_balance' => '0.00000000',
        ]);
        $project = app(DeveloperApiKeyService::class)->createProject($user->id, [
            'name' => 'Sandbox',
            'environment' => 'sandbox',
        ]);

        $response = $this->actingAs($user)->postJson("/api/developer/projects/{$project->id}/sandbox/faucet", [
            'asset' => 'USDT',
            'amount' => '50',
        ]);

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('developer_sandbox_balances', [
            'project_id' => $project->id,
            'asset' => 'USDT',
            'available' => '50',
        ]);
        $this->assertSame('12.00000000', (string) Wallet::query()->where('user_id', $user->id)->where('currency', 'USDT')->firstOrFail()->available_balance);
    }

    public function test_signed_private_api_request_reads_sandbox_balances_and_logs_request(): void
    {
        $user = User::factory()->create();
        $credentials = $this->createSandboxKey($user, ['account.read']);
        DeveloperSandboxBalance::query()->create([
            'user_id' => $user->id,
            'project_id' => $credentials['project']->id,
            'asset' => 'BTC',
            'available' => '1.25000000',
            'reserved' => '0.25000000',
        ]);

        $response = $this->withHeaders($this->signedHeaders(
            $credentials['api_key'],
            $credentials['api_secret'],
            'GET',
            '/api/developer/v1/wallet/balances',
            '',
            '[]'
        ))->getJson('/api/developer/v1/wallet/balances');

        $response->assertOk($response->json())
            ->assertHeader('X-Exa-Request-Id')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.asset', 'BTC')
            ->assertJsonPath('data.0.total', '1.50000000')
            ->assertJsonPath('data.0.environment', 'sandbox');
        $this->assertSame(1, DeveloperApiRequestLog::query()->where('status_code', 200)->count());
        $this->assertNotNull(DeveloperApiKey::query()->firstOrFail()->last_used_at);
        $this->assertSame(1, DeveloperApiNonce::query()->count());
    }

    public function test_private_api_rejects_invalid_signature_expired_timestamp_missing_permission_and_ip_mismatch(): void
    {
        $user = User::factory()->create();
        $credentials = $this->createSandboxKey($user, ['market.read']);

        $this->withHeaders([
            'EXA-API-KEY' => $credentials['api_key'],
            'EXA-API-TIMESTAMP' => (string) time(),
            'EXA-API-NONCE' => 'bad-nonce',
            'EXA-API-SIGNATURE' => 'bad',
        ])->getJson('/api/developer/v1/wallet/balances')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_SIGNATURE');

        $this->withHeaders($this->signedHeaders(
            $credentials['api_key'],
            $credentials['api_secret'],
            'GET',
            '/api/developer/v1/wallet/balances',
            '',
            '[]',
            (string) (time() - 9999),
            'old-nonce'
        ))->getJson('/api/developer/v1/wallet/balances')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'TIMESTAMP_EXPIRED');

        $this->withHeaders($this->signedHeaders(
            $credentials['api_key'],
            $credentials['api_secret'],
            'GET',
            '/api/developer/v1/wallet/balances',
            '',
            '[]',
            null,
            'missing-scope-nonce'
        ))->getJson('/api/developer/v1/wallet/balances')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');

        $restricted = $this->createSandboxKey($user, ['account.read'], ['10.20.30.40']);
        $this->withHeaders($this->signedHeaders(
            $restricted['api_key'],
            $restricted['api_secret'],
            'GET',
            '/api/developer/v1/wallet/balances',
            '',
            '[]',
            null,
            'restricted-nonce'
        ))->getJson('/api/developer/v1/wallet/balances')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'IP_NOT_ALLOWED');
    }

    public function test_private_api_rejects_nonce_replay(): void
    {
        $user = User::factory()->create();
        $credentials = $this->createSandboxKey($user, ['account.read']);

        $headers = $this->signedHeaders(
            $credentials['api_key'],
            $credentials['api_secret'],
            'GET',
            '/api/developer/v1/wallet/balances',
            '',
            '[]',
            null,
            'same-nonce'
        );

        $this->withHeaders($headers)->getJson('/api/developer/v1/wallet/balances')->assertOk();
        $this->withHeaders($headers)->getJson('/api/developer/v1/wallet/balances')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'NONCE_REPLAYED');
    }

    public function test_public_market_api_uses_standard_developer_response_envelope(): void
    {
        $response = $this->getJson('/api/developer/v1/exchange-info');

        $response->assertOk()
            ->assertHeader('X-Exa-Request-Id')
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['symbols', 'timezone', 'server_time'], 'timestamp']);
        $this->assertSame(1, DeveloperApiRequestLog::query()->where('path', '/api/developer/v1/exchange-info')->count());
    }

    public function test_webhook_registration_delivery_retry_dead_letter_and_replay_are_signed(): void
    {
        Http::fake([
            'https://hooks.example.test/ok' => Http::response(['ok' => true], 200),
            'https://hooks.example.test/down' => Http::response(['ok' => false], 500),
        ]);

        $user = User::factory()->create();
        $project = app(DeveloperApiKeyService::class)->createProject($user->id, ['name' => 'Hooks', 'environment' => 'sandbox']);

        $ok = $this->actingAs($user)->postJson("/api/developer/projects/{$project->id}/webhooks", [
            'url' => 'https://hooks.example.test/ok',
            'events' => ['order.filled'],
        ])->assertCreated()->json('data');

        $down = $this->actingAs($user)->postJson("/api/developer/projects/{$project->id}/webhooks", [
            'url' => 'https://hooks.example.test/down',
            'events' => ['order.filled'],
        ])->assertCreated()->json('data');

        $this->assertStringStartsWith('whsec_', $ok['signing_secret']);
        $this->assertStringStartsWith('whsec_', $down['signing_secret']);

        app(DeveloperWebhookService::class)->enqueue($project, 'order.filled', ['order_id' => 'ord_1']);
        $result = app(DeveloperWebhookService::class)->deliverDue();

        $this->assertSame(1, $result['delivered']);
        $this->assertSame(1, $result['retrying']);
        $this->assertSame(1, DeveloperWebhookDelivery::query()->where('status', 'DELIVERED')->count());
        $this->assertSame(1, DeveloperWebhookDelivery::query()->where('status', 'RETRYING')->count());

        $delivery = DeveloperWebhookDelivery::query()->where('status', 'DELIVERED')->firstOrFail();
        $replay = $this->actingAs($user)->postJson("/api/developer/webhook-deliveries/{$delivery->id}/replay");
        $replay->assertCreated();
        $this->assertSame((string) $delivery->event_id, (string) DeveloperWebhookDelivery::query()->latest('id')->firstOrFail()->event_id);

        $this->actingAs($user)->postJson('/api/developer/webhooks/' . DeveloperWebhookEndpoint::query()->firstOrFail()->id . '/rotate-secret')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_realtime_session_sequence_and_replay(): void
    {
        $user = User::factory()->create();
        $credentials = $this->createSandboxKey($user, ['account.read']);
        $project = $credentials['project'];

        app(DeveloperRealtimeService::class)->publish($project, 'account.balance', 'account.balance.updated', ['asset' => 'USDT']);
        app(DeveloperRealtimeService::class)->publish($project, 'account.balance', 'account.balance.updated', ['asset' => 'BTC']);

        $session = $this->withHeaders($this->signedHeaders(
            $credentials['api_key'],
            $credentials['api_secret'],
            'POST',
            '/api/developer/v1/realtime/session',
            '',
            json_encode(['topics' => ['account.balance']], JSON_THROW_ON_ERROR),
            null,
            'rt-session'
        ))->postJson('/api/developer/v1/realtime/session', ['topics' => ['account.balance']]);

        $session->assertOk()->assertJsonPath('data.topics.0', 'account.balance');

        $replay = $this->withHeaders($this->signedHeaders(
            $credentials['api_key'],
            $credentials['api_secret'],
            'GET',
            '/api/developer/v1/realtime/replay',
            'after_sequence=1&stream=account.balance',
            '[]',
            null,
            'rt-replay'
        ))->getJson('/api/developer/v1/realtime/replay?stream=account.balance&after_sequence=1');

        $replay->assertOk()->assertJsonPath('data.0.sequence', 2);
        $this->assertSame([1, 2], DeveloperRealtimeEvent::query()->orderBy('sequence')->pluck('sequence')->all());
    }

    public function test_remaining_product_developer_apis_require_scopes_and_route_to_existing_products(): void
    {
        $user = User::factory()->create();
        $readonly = $this->createSandboxKey($user, ['account.read']);
        $full = $this->createSandboxKey($user, [
            'futures.read',
            'margin.read',
            'staking.read',
            'copy.read',
            'exaai.read',
        ]);

        foreach ([
            '/api/developer/v1/futures/positions',
            '/api/developer/v1/margin/accounts',
            '/api/developer/v1/staking/products',
            '/api/developer/v1/copy/relationships',
            '/api/developer/v1/exaai/readiness',
        ] as $index => $path) {
            $this->withHeaders($this->signedHeaders(
                $readonly['api_key'],
                $readonly['api_secret'],
                'GET',
                $path,
                '',
                '[]',
                null,
                'missing-product-scope-' . $index
            ))->getJson($path)->assertForbidden()->assertJsonPath('error.code', 'PERMISSION_DENIED');

            $this->withHeaders($this->signedHeaders(
                $full['api_key'],
                $full['api_secret'],
                'GET',
                $path,
                '',
                '[]',
                null,
                'product-read-' . $index
            ))->getJson($path)->assertOk();
        }
    }

    public function test_product_manage_endpoints_require_specific_scopes_and_nonce_idempotency(): void
    {
        $user = User::factory()->create();
        $readOnly = $this->createSandboxKey($user, ['staking.read']);
        $manage = $this->createSandboxKey($user, [
            'futures.trade',
            'margin.manage',
            'staking.manage',
            'copy.manage',
            'exaai.manage',
        ]);

        $body = json_encode(['amount' => '10'], JSON_THROW_ON_ERROR);
        $this->withHeaders($this->signedHeaders(
            $readOnly['api_key'],
            $readOnly['api_secret'],
            'POST',
            '/api/developer/v1/staking/positions',
            '',
            $body,
            null,
            'staking-manage-missing'
        ))->postJson('/api/developer/v1/staking/positions', ['amount' => '10'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');

        foreach ([
            ['POST', '/api/developer/v1/futures/orders', ['symbol' => 'BTCUSDT']],
            ['POST', '/api/developer/v1/margin/borrow', ['asset' => 'USDT']],
            ['POST', '/api/developer/v1/staking/positions', ['amount' => '10']],
            ['POST', '/api/developer/v1/copy/follow', ['amount_allocated' => '10']],
            ['POST', '/api/developer/v1/exaai/allocations', ['asset' => 'USDT']],
        ] as $index => [$method, $path, $payload]) {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
            $headers = $this->signedHeaders(
                $manage['api_key'],
                $manage['api_secret'],
                $method,
                $path,
                '',
                $encoded,
                null,
                'manage-route-' . $index
            );

            $this->withHeaders($headers)->postJson($path, $payload)->assertStatus(422);

            if ($index === 0) {
                $this->withHeaders($headers)->postJson($path, $payload)
                    ->assertUnauthorized()
                    ->assertJsonPath('error.code', 'NONCE_REPLAYED');
            }
        }
    }

    public function test_realtime_gateway_supports_one_thousand_durable_events_and_replay(): void
    {
        $user = User::factory()->create();
        $credentials = $this->createSandboxKey($user, ['account.read']);
        $project = $credentials['project'];
        $service = app(DeveloperRealtimeService::class);
        $startedAt = microtime(true);

        for ($i = 1; $i <= 1000; $i++) {
            $event = $service->publish($project, 'account.balance', 'account.balance.updated', ['index' => $i]);
            $this->assertSame($i, $event->sequence);
        }

        $session = $service->createSession($project, ['account.balance', 'market.BTCUSDT.ticker']);
        $tail = $service->replay($project, 'account.balance', 995, 10);

        $this->assertSame(1000, DeveloperRealtimeEvent::query()->where('project_id', $project->id)->count());
        $this->assertCount(5, $tail);
        $this->assertSame(996, $tail[0]['sequence']);
        $this->assertSame(1000, $tail[4]['sequence']);
        $this->assertContains('market.BTCUSDT.ticker', $session['topics']);
        $this->assertLessThan(10.0, microtime(true) - $startedAt, '1K durable realtime event probe exceeded local threshold.');
    }

    public function test_webhook_batch_load_delivery_retry_dead_letter_and_replay(): void
    {
        Http::fake([
            'https://hooks.example.test/load-ok' => Http::response(['ok' => true], 200),
            'https://hooks.example.test/load-down' => Http::response(['ok' => false], 500),
        ]);

        $user = User::factory()->create();
        $project = app(DeveloperApiKeyService::class)->createProject($user->id, ['name' => 'Webhook Load', 'environment' => 'sandbox']);
        $ok = app(DeveloperWebhookService::class)->register($project, ['url' => 'https://hooks.example.test/load-ok', 'events' => ['order.filled']]);
        app(DeveloperWebhookService::class)->register($project, ['url' => 'https://hooks.example.test/load-down', 'events' => ['order.filled']]);

        for ($i = 0; $i < 25; $i++) {
            app(DeveloperWebhookService::class)->enqueue($project, 'order.filled', ['order_id' => 'ord_' . $i], 'evt_load_' . $i);
        }

        $result = app(DeveloperWebhookService::class)->deliverDue(100);

        $this->assertSame(25, $result['delivered']);
        $this->assertSame(25, $result['retrying']);
        $this->assertSame(50, DeveloperWebhookDelivery::query()->count());

        $retrying = DeveloperWebhookDelivery::query()->where('status', 'RETRYING')->get();
        $retrying->each(fn (DeveloperWebhookDelivery $delivery) => $delivery->forceFill([
            'attempts' => (int) config('developer_api.webhooks.max_attempts', 8) - 1,
            'next_attempt_at' => now()->subSecond(),
        ])->save());

        $deadLetter = app(DeveloperWebhookService::class)->deliverDue(100);
        $this->assertSame(25, $deadLetter['dead_lettered']);
        $this->assertSame(25, DeveloperWebhookDelivery::query()->where('status', 'DEAD_LETTERED')->count());

        $delivered = DeveloperWebhookDelivery::query()->where('status', 'DELIVERED')->firstOrFail();
        $replay = app(DeveloperWebhookService::class)->replay($delivered);
        $this->assertSame($delivered->event_id, $replay->event_id);
        $this->assertStringStartsWith('whsec_', $ok['signing_secret']);
    }

    private function createSandboxKey(User $user, array $permissions, array $ipWhitelist = []): array
    {
        $project = app(DeveloperApiKeyService::class)->createProject($user->id, [
            'name' => 'Sandbox Project',
            'environment' => 'sandbox',
        ]);
        $credentials = app(DeveloperApiKeyService::class)->createKey($user->id, $project, [
            'name' => 'Test key',
            'permissions' => $permissions,
            'ip_whitelist' => $ipWhitelist,
        ]);

        return $credentials + ['project' => $project];
    }

    private function signedHeaders(
        string $apiKey,
        string $apiSecret,
        string $method,
        string $path,
        string $query = '',
        string $body = '',
        ?string $timestamp = null,
        ?string $nonce = null
    ): array {
        $timestamp ??= (string) time();
        $nonce ??= 'nonce-' . uniqid('', true);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $query . "\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $body);

        return [
            'EXA-API-KEY' => $apiKey,
            'EXA-API-TIMESTAMP' => $timestamp,
            'EXA-API-NONCE' => $nonce,
            'EXA-API-SIGNATURE' => hash_hmac('sha256', $canonical, $apiSecret),
        ];
    }
}
