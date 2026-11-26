<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Internal\DeveloperRealtimeAuthorityController;
use App\Models\User;
use App\Services\DeveloperApiKeyService;
use App\Services\DeveloperRealtimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DeveloperRealtimeRouteBootTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['wallet.node.secret' => 'node-route-boot-secret']);
    }

    public function test_route_collection_boots_and_internal_authority_route_resolves_to_non_framework_action(): void
    {
        $route = Route::getRoutes()->match(Request::create('/api/internal/developer/realtime/authorize', 'POST'));

        $this->assertSame(
            DeveloperRealtimeAuthorityController::class.'@authorizeRealtimeSession',
            $route->getActionName()
        );
        $this->assertContains('node.webhook', $route->middleware());
        $this->assertTrue(method_exists(DeveloperRealtimeAuthorityController::class, 'authorizeRealtimeSession'));
    }

    public function test_internal_realtime_authority_preserves_secret_session_environment_and_scope_policy(): void
    {
        $credentials = $this->createSandboxRealtimeKey(['account.read']);
        $session = app(DeveloperRealtimeService::class)->createSession($credentials['project'], $credentials['key'], ['account.balance']);

        $this->postJson('/api/internal/developer/realtime/authorize', [
            'session_id' => $session['session_id'],
            'environment' => 'sandbox',
        ])->assertUnauthorized();

        $this->withHeaders(['X-Webhook-Secret' => 'node-route-boot-secret'])
            ->postJson('/api/internal/developer/realtime/authorize', [
                'session_id' => $session['session_id'],
                'environment' => 'production',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'REALTIME_AUTH_DENIED');

        $this->withHeaders(['X-Webhook-Secret' => 'node-route-boot-secret'])
            ->postJson('/api/internal/developer/realtime/authorize', [
                'session_id' => $session['session_id'],
                'environment' => 'sandbox',
            ])
            ->assertOk()
            ->assertJsonPath('data.environment', 'sandbox')
            ->assertJsonPath('data.topics.0', 'account.balance');

        $credentials['key']->permissions()->delete();
        $this->withHeaders(['X-Webhook-Secret' => 'node-route-boot-secret'])
            ->postJson('/api/internal/developer/realtime/authorize', [
                'session_id' => $session['session_id'],
                'environment' => 'sandbox',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'REALTIME_AUTH_DENIED');
    }

    public function test_internal_realtime_authority_rejects_revoked_credentials(): void
    {
        $credentials = $this->createSandboxRealtimeKey(['account.read']);
        $session = app(DeveloperRealtimeService::class)->createSession($credentials['project'], $credentials['key'], ['account.balance']);

        $credentials['key']->forceFill(['status' => 'revoked', 'revoked_at' => now()])->save();

        $this->withHeaders(['X-Webhook-Secret' => 'node-route-boot-secret'])
            ->postJson('/api/internal/developer/realtime/authorize', [
                'session_id' => $session['session_id'],
                'environment' => 'sandbox',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'REALTIME_AUTH_DENIED');
    }

    private function createSandboxRealtimeKey(array $permissions): array
    {
        $user = User::factory()->create(['account_status' => 'FULLY_ACTIVE']);
        $project = app(DeveloperApiKeyService::class)->createProject($user->id, [
            'name' => 'Realtime Authority Test',
            'environment' => 'sandbox',
        ]);

        return app(DeveloperApiKeyService::class)->createKey($user->id, $project, [
            'name' => 'Realtime key',
            'permissions' => $permissions,
        ]) + ['project' => $project];
    }
}
