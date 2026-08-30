<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DeveloperApiKey;
use App\Models\DeveloperApiNonce;
use App\Models\DeveloperApiRequestLog;
use App\Models\DeveloperAuditLog;
use App\Models\DeveloperProject;
use App\Models\DeveloperProfile;
use App\Models\DeveloperOrganization;
use App\Models\DeveloperOrganizationMembership;
use App\Models\DeveloperRealtimeEvent;
use App\Models\DeveloperSandboxBalance;
use App\Models\DeveloperWebhookDelivery;
use App\Models\DeveloperWebhookEndpoint;
use App\Models\User;
use App\Models\Wallet;
use App\Models\SreHealthSnapshot;
use App\Models\SreService;
use App\Services\DeveloperApiKeyService;
use App\Services\DeveloperRealtimeService;
use App\Services\DeveloperWebhookService;
use App\Services\Security\DnsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Notifications\VerifyEmail;
use Tests\TestCase;

class Phase14DeveloperPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(DnsResolver::class, new class extends DnsResolver {
            public function resolve(string $host): array { return ['93.184.216.34']; }
        });
    }

    public function test_canonical_user_resolves_one_developer_profile_without_kyc(): void
    {
        $user = User::factory()->create([
            'kyc_level' => 0,
            'kyc_verified_at' => null,
        ]);

        $this->actingAs($user)->postJson('/api/developer/session')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.developer_profile.onboarding_status', 'not_started');

        $this->actingAs($user)->postJson('/api/developer/session')->assertOk();

        $this->assertSame(1, DeveloperProfile::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('developer_audit_logs', [
            'user_id' => $user->id,
            'event_type' => 'developer_profile.created',
        ]);
    }

    public function test_developer_session_requires_canonical_authentication(): void
    {
        $this->postJson('/api/developer/session')->assertUnauthorized();
    }

    public function test_developer_signup_uses_canonical_registration_and_sends_verification(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Developer One',
            'email' => 'developer-one@exaearn.io',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
            'registration_context' => 'developers',
        ]);

        $response->assertCreated()
            ->assertJsonPath('next', 'developer_email_verification')
            ->assertJsonPath('user.email', 'developer-one@exaearn.io');
        $user = User::query()->where('email', 'developer-one@exaearn.io')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertSame(1, User::query()->where('email', $user->email)->count());
    }

    public function test_email_verification_preserves_developer_intent(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($url)
            ->assertRedirect(config('app.developer_portal_url').'/developers/onboarding?verified=1');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verified_developer_onboarding_creates_one_sandbox_project_idempotently(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'kyc_level' => 0, 'kyc_verified_at' => null]);
        $this->actingAs($user)->postJson('/api/developer/session')->assertOk();
        $payload = [
            'developer_type' => 'individual',
            'use_case' => 'trading_bot',
            'project_name' => 'Market Maker Sandbox',
            'terms_accepted' => true,
        ];

        $this->actingAs($user)->postJson('/api/developer/onboarding', $payload)
            ->assertCreated()
            ->assertJsonPath('data.project.environment', 'sandbox')
            ->assertJsonPath('data.profile.onboarding_status', 'completed');
        $this->actingAs($user)->postJson('/api/developer/onboarding', $payload)->assertCreated();

        $this->assertSame(1, DeveloperProject::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, DeveloperProject::query()->where('user_id', $user->id)->where('environment', 'production')->count());
    }

    public function test_unverified_developer_is_blocked_and_company_onboarding_creates_owner_membership(): void
    {
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->postJson('/api/developer/session')->assertOk();
        $this->actingAs($unverified)->postJson('/api/developer/onboarding', [
            'developer_type' => 'individual', 'project_name' => 'Blocked', 'terms_accepted' => true,
        ])->assertForbidden()->assertJsonPath('error.code', 'EMAIL_UNVERIFIED');

        $owner = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($owner)->postJson('/api/developer/session')->assertOk();
        $this->actingAs($owner)->postJson('/api/developer/onboarding', [
            'developer_type' => 'organization', 'use_case' => 'institutional',
            'organization_name' => 'Atlas Labs', 'project_name' => 'Atlas Sandbox', 'terms_accepted' => true,
        ])->assertCreated()->assertJsonPath('data.organization.production_access_status', 'not_activated');

        $organization = DeveloperOrganization::query()->firstOrFail();
        $this->assertDatabaseHas('developer_organization_memberships', [
            'organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => 'owner',
        ]);
        $this->assertSame(1, DeveloperOrganizationMembership::query()->count());
    }

    public function test_public_server_time_contract_is_available_for_signature_clock_sync(): void
    {
        $response = $this->getJson('/api/developer/v1/time');

        $response->assertOk()
            ->assertHeader('X-Exa-Request-Id')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.timezone', 'UTC')
            ->assertJsonStructure(['data' => ['unix_seconds', 'unix_milliseconds', 'iso_8601', 'timezone']]);

        $this->assertLessThanOrEqual(1000, abs((now()->timestamp * 1000) - (int) $response->json('data.unix_milliseconds')));
    }

    public function test_developer_documentation_catalog_uses_real_routes_and_scopes(): void
    {
        $catalog = file_get_contents(base_path('../../apps/developers/src/docsCatalog.ts'));
        $openApi = file_get_contents(base_path('../../openapi/exaearn-developer-v1.yaml'));
        $routes = file_get_contents(base_path('routes/api.php'));

        $this->assertIsString($catalog);
        $this->assertIsString($openApi);
        $this->assertIsString($routes);
        $this->assertStringContainsString('/api/developer/v1/time', $catalog);
        $spec = json_decode($openApi, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('/api/developer/v1/time', $spec['paths']);
        $this->assertSame('3.1.0', $spec['openapi']);
        $operationIds = [];
        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $this->assertContains(strtoupper((string) $operation['x-exaearn-status']), ['STABLE', 'BETA', 'RESTRICTED', 'DEPRECATED']);
                $this->assertNotContains($operation['operationId'], $operationIds, "Duplicate operationId at {$method} {$path}");
                $operationIds[] = $operation['operationId'];
            }
        }
        $this->assertStringContainsString("Route::get('time'", $routes);

        preg_match_all('/scope:\s*"([a-z.]+)"/', $catalog, $scopeMatches);
        foreach (array_unique($scopeMatches[1] ?? []) as $scope) {
            $this->assertContains($scope, (array) config('developer_api.permissions'), "Documented scope {$scope} is not configured.");
        }

        foreach (['AVAILABLE', 'PRIVATE_BETA', 'COMING_SOON'] as $legacyStatus) {
            $this->assertStringNotContainsString('status: "' . $legacyStatus . '"', $catalog);
        }
    }

    public function test_every_developer_write_has_an_explicit_valid_request_contract_or_is_bodyless(): void
    {
        $spec = json_decode(
            file_get_contents(base_path('../../openapi/exaearn-developer-v1.yaml')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $schemas = $spec['components']['schemas'];
        $operationIds = [];

        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $this->assertArrayNotHasKey($operation['operationId'], $operationIds, "Duplicate operationId {$operation['operationId']}");
                $operationIds[$operation['operationId']] = true;
                if (! in_array(strtoupper($method), ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
                    continue;
                }

                $reference = $operation['requestBody']['content']['application/json']['schema']['$ref'] ?? null;
                if ($reference === null) {
                    $this->assertSame('NONE', $operation['x-exaearn-request-body'] ?? null, "Undocumented bodyless write {$method} {$path}");
                    $this->assertNotEmpty($operation['x-exaearn-request-body-reason'] ?? null);
                    continue;
                }

                $schemaName = basename($reference);
                $this->assertNotSame('GenericRequest', $schemaName, "Accidental GenericRequest at {$method} {$path}");
                $this->assertArrayHasKey($schemaName, $schemas, "Broken schema reference {$reference}");
                $schema = $schemas[$schemaName];
                $this->assertArrayHasKey('example', $schema, "Missing sandbox-safe example for {$schemaName}");
                foreach ($schema['required'] ?? [] as $required) {
                    $this->assertArrayHasKey($required, $schema['properties'] ?? [], "Required field {$required} is orphaned in {$schemaName}");
                    $this->assertArrayHasKey($required, $schema['example'], "Example misses required field {$required} in {$schemaName}");
                }
                if (($schema['additionalProperties'] ?? true) === false) {
                    foreach (array_keys($schema['example']) as $field) {
                        $this->assertArrayHasKey($field, $schema['properties'] ?? [], "Example field {$field} is not accepted by {$schemaName}");
                    }
                }
            }
        }

        $generatedExplorerContracts = file_get_contents(base_path('../../apps/developers/src/openapiRequestSchemas.generated.ts'));
        $this->assertIsString($generatedExplorerContracts);
        $this->assertStringNotContainsString('GenericRequest', $generatedExplorerContracts);
        $this->assertStringContainsString('ConvertQuoteRequest', $generatedExplorerContracts);
        $this->assertStringContainsString('ExaAiSessionRequest', $generatedExplorerContracts);
    }

    public function test_public_operational_status_uses_sanitized_phase19_telemetry(): void
    {
        $registry = app(\App\Services\SreServiceRegistry::class);
        $registry->register(['service_id' => 'spot-engine', 'service_name' => 'Spot', 'service_type' => 'TRADING', 'status' => 'HEALTHY']);
        $registry->register(['service_id' => 'market-data', 'service_name' => 'Market Data', 'service_type' => 'MARKET_DATA', 'status' => 'DEGRADED']);
        SreHealthSnapshot::query()->create([
            'snapshot_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'scope' => 'GLOBAL',
            'overall_status' => 'DEGRADED',
            'liveness' => ['api' => 'PASS'],
            'readiness' => ['database' => 'PASS'],
            'dependency_health' => [],
            'business_readiness' => [],
            'reason_codes' => [],
            'captured_at' => now(),
        ]);

        $response = $this->getJson('/api/developer/v1/operational-status');
        $response->assertOk()
            ->assertJsonPath('data.overall_status', 'DEGRADED')
            ->assertJsonPath('data.components.SPOT', 'OPERATIONAL')
            ->assertJsonPath('data.components.MARKET_DATA', 'DEGRADED');

        $this->assertArrayNotHasKey('dependency_health', $response->json('data'));
        $this->assertArrayNotHasKey('reason_codes', $response->json('data'));
    }

    public function test_developer_can_create_project_and_api_key_with_secret_shown_once(): void
    {
        $user = User::factory()->create();

        $projectResponse = $this->actingAs($user)->postJson('/api/developer/projects', [
            'name' => 'Trading Bot Sandbox',
            'environment' => 'sandbox',
        ]);

        $projectResponse->assertCreated()->assertJsonPath('success', true);
        $project = DeveloperProject::query()->firstOrFail();

        $keyResponse = $this->actingAs($user)->withSession(['auth_recent_at' => time()])->postJson("/api/developer/projects/{$project->id}/keys", [
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

        $response = $this->actingAs($user)->withSession(['auth_recent_at' => time()])->postJson("/api/developer/projects/{$project->id}/keys", [
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

    public function test_documentation_explorer_executes_real_signed_sandbox_request_without_exposing_secrets(): void
    {
        $user = User::factory()->create();
        $credentials = $this->createSandboxKey($user, ['account.read']);
        $project = $credentials['project'];
        $key = $credentials['key'];
        app(\App\Services\DeveloperSandboxService::class)->faucet($project, 'USDT', '25');

        $response = $this->actingAs($user)->postJson("/api/developer/projects/{$project->id}/sandbox/explorer", [
            'api_key_id' => $key->id,
            'method' => 'GET',
            'path' => '/api/developer/v1/wallet/balances',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.environment', 'sandbox')
            ->assertJsonPath('data.status', 200)
            ->assertJsonPath('data.request_headers.exa-api-key', '[REDACTED]')
            ->assertJsonPath('data.request_headers.exa-api-signature', '[REDACTED]')
            ->assertJsonPath('data.body.data.0.asset', 'USDT');

        $serialized = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($credentials['api_key'], $serialized);
        $this->assertStringNotContainsString($credentials['api_secret'], $serialized);
    }

    public function test_documentation_explorer_rejects_production_project_server_side(): void
    {
        $user = User::factory()->create();
        $project = app(DeveloperApiKeyService::class)->createProject($user->id, ['name' => 'Production', 'environment' => 'production']);
        $this->assertDatabaseHas('developer_project_environments', [
            'project_id' => $project->id,
            'type' => 'production',
            'status' => 'not_activated',
        ]);
        $credentials = app(DeveloperApiKeyService::class)->createKey($user->id, $project, [
            'name' => 'Sandbox read',
            'environment' => 'sandbox',
            'permissions' => ['account.read'],
        ]);

        $credentials['key']->update(['environment' => 'production']);

        $this->actingAs($user)->postJson("/api/developer/projects/{$project->id}/sandbox/explorer", [
            'api_key_id' => $credentials['key']->id,
            'method' => 'GET',
            'path' => '/api/developer/v1/wallet/balances',
        ])->assertStatus(422)->assertJsonPath('error.code', 'SANDBOX_EXPLORER_REJECTED');
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

        $ok = $this->actingAs($user)->withSession(['auth_recent_at' => time()])->postJson("/api/developer/projects/{$project->id}/webhooks", [
            'url' => 'https://hooks.example.test/ok',
            'events' => ['order.filled'],
        ])->assertCreated()->json('data');

        $down = $this->actingAs($user)->withSession(['auth_recent_at' => time()])->postJson("/api/developer/projects/{$project->id}/webhooks", [
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

        $this->actingAs($user)->withSession(['auth_recent_at' => time()])->postJson('/api/developer/webhooks/' . DeveloperWebhookEndpoint::query()->firstOrFail()->id . '/rotate-secret')
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

        $session = $service->createSession($project, $credentials['key'], ['account.balance', 'market.BTCUSDT.ticker']);
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
