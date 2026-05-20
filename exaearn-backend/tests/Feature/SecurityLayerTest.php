<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\AdminActionAuditMiddleware;
use App\Http\Middleware\AdminSecurityLayer;
use App\Http\Middleware\SecurityMiddleware;
use App\Models\AuditLog;
use App\Models\FraudLog;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\TransactionGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rate_limiting_blocks_after_max_attempts(): void
    {
        User::factory()->create([
            'email' => 'security@example.com',
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'security@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/login', [
            'email' => 'security@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_registration_enforces_strong_password_policy(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Weak Password User',
            'email' => 'weak@example.com',
            'password' => 'weakpass',
            'password_confirmation' => 'weakpass',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_failed_login_creates_fraud_and_audit_logs(): void
    {
        User::factory()->create([
            'email' => 'audit@example.com',
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'audit@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);

        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseCount('fraud_logs', 1);
        $this->assertDatabaseCount('suspicious_users', 1);

        $this->assertSame('auth_login_failed', (string) AuditLog::query()->first()?->action);
        $this->assertSame('MEDIUM', (string) FraudLog::query()->first()?->risk_level);
    }

    public function test_admin_security_layer_rejects_admin_without_2fa(): void
    {
        config()->set('security.admin.require_2fa', true);

        $admin = User::factory()->create([
            'role' => 'admin',
            'two_factor_enabled' => false,
        ]);

        $request = Request::create('/api/admin/treasury/wallets', 'GET');
        $request->setUserResolver(fn () => $admin);

        $middleware = app(AdminSecurityLayer::class);
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_transaction_guard_blocks_high_risk_withdrawal(): void
    {
        config()->set('security.transactions.withdrawal_daily_limit', '999999999');

        $user = User::factory()->create();

        Withdrawal::query()->create([
            'user_id' => $user->id,
            'transaction_id' => null,
            'currency' => 'USD',
            'amount' => '10',
            'fee' => '0',
            'address' => 'addr-1',
            'network' => 'TRC20',
            'status' => 'pending',
            'metadata' => [],
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);

        Withdrawal::query()->create([
            'user_id' => $user->id,
            'transaction_id' => null,
            'currency' => 'USD',
            'amount' => '11',
            'fee' => '0',
            'address' => 'addr-2',
            'network' => 'TRC20',
            'status' => 'pending',
            'metadata' => [],
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ]);

        Withdrawal::query()->create([
            'user_id' => $user->id,
            'transaction_id' => null,
            'currency' => 'USD',
            'amount' => '12',
            'fee' => '0',
            'address' => 'addr-3',
            'network' => 'TRC20',
            'status' => 'pending',
            'metadata' => [],
            'created_at' => now()->subMinutes(4),
            'updated_at' => now()->subMinutes(4),
        ]);

        /** @var TransactionGuardService $guard */
        $guard = app(TransactionGuardService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Withdrawal blocked for security review.');

        $guard->guardWithdrawal($user, '1000000');
    }

    public function test_admin_action_audit_middleware_logs_admin_actions(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'two_factor_enabled' => true,
        ]);

        $request = Request::create('/api/admin/treasury/wallets', 'GET');
        $request->setUserResolver(fn () => $admin);

        $middleware = app(AdminActionAuditMiddleware::class);
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true], 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'admin.action',
        ]);
    }

    public function test_security_middleware_rejects_replayed_nonce_for_sensitive_path(): void
    {
        config()->set('security.api.signature_required', false);

        $middleware = app(SecurityMiddleware::class);

        $requestA = Request::create('/api/admin/treasury/wallets', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $requestA->headers->set('X-EXA-NONCE', 'nonce-123');

        $first = $middleware->handle($requestA, fn () => response()->json(['ok' => true], 200));
        $this->assertSame(200, $first->getStatusCode());

        $requestB = Request::create('/api/admin/treasury/wallets', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $requestB->headers->set('X-EXA-NONCE', 'nonce-123');

        try {
            $middleware->handle($requestB, fn () => response()->json(['ok' => true], 200));
            $this->fail('Expected replay request to be rejected.');
        } catch (HttpResponseException $e) {
            $this->assertSame(409, $e->getResponse()->getStatusCode());
        }
    }

    public function test_security_middleware_enforces_sensitive_api_rate_limit(): void
    {
        config()->set('security.api.signature_required', false);
        config()->set('security.api.rate_limit_per_minute', 1);

        $middleware = app(SecurityMiddleware::class);

        $requestA = Request::create('/api/admin/treasury/wallets', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.2',
        ]);
        $requestA->headers->set('X-EXA-NONCE', 'nonce-a');

        $first = $middleware->handle($requestA, fn () => response()->json(['ok' => true], 200));
        $this->assertSame(200, $first->getStatusCode());

        $requestB = Request::create('/api/admin/treasury/wallets', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.2',
        ]);
        $requestB->headers->set('X-EXA-NONCE', 'nonce-b');

        try {
            $middleware->handle($requestB, fn () => response()->json(['ok' => true], 200));
            $this->fail('Expected API rate limit rejection.');
        } catch (HttpResponseException $e) {
            $this->assertSame(429, $e->getResponse()->getStatusCode());
        }
    }

    public function test_transaction_guard_blocks_when_daily_limit_is_exceeded(): void
    {
        config()->set('security.transactions.withdrawal_daily_limit', '100');

        $user = User::factory()->create();

        Withdrawal::query()->create([
            'user_id' => $user->id,
            'transaction_id' => null,
            'currency' => 'USD',
            'amount' => '90',
            'fee' => '0',
            'address' => 'addr-daily',
            'network' => 'TRC20',
            'status' => 'pending',
            'metadata' => [],
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        /** @var TransactionGuardService $guard */
        $guard = app(TransactionGuardService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Withdrawal exceeds daily security limit.');

        $guard->guardWithdrawal($user, '20');
    }
}

