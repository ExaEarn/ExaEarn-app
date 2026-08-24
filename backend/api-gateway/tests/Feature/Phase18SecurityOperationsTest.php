<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeveloperApiKey;
use App\Models\DeveloperProject;
use App\Models\FinanceReconciliationBreak;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecurityCase;
use App\Models\SecurityEmergencyControl;
use App\Models\SecurityIncident;
use App\Models\SecurityRelatedAccount;
use App\Models\SecurityRiskDecision;
use App\Models\SecurityRiskSignal;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\ApiSecurityService;
use App\Services\MarketSurveillanceService;
use App\Services\PermissionService;
use App\Services\SecurityCaseService;
use App\Services\SecurityEmergencyControlService;
use App\Services\SecurityIncidentService;
use App\Services\SecurityReadinessService;
use App\Services\SecurityRiskEngine;
use App\Services\SecurityRuleService;
use App\Services\SecuritySignalService;
use App\Services\SessionSecurityService;
use App\Services\WithdrawalSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase18SecurityOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security-ratelimit.enabled' => false, 'security.transactions.large_withdrawal_threshold' => '1000']);
    }

    public function test_account_takeover_device_impossible_travel_cooldown_and_withdrawal_security_are_composed(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['withdrawal_locked_until' => now()->addHour()])->save();

        app(SessionSecurityService::class)->markDevice($user, 'phone-a', 'NEW', ['ip_address' => '102.89.1.1', 'device_name' => 'Mobile Safari']);
        app(SecuritySignalService::class)->record('NEW_COUNTRY', 'SESSION', 'USER', $user->id, 'MEDIUM', ['country' => 'NG'], '0.8000', 3600);
        app(SecuritySignalService::class)->record('IMPOSSIBLE_TRAVEL', 'SESSION', 'USER', $user->id, 'HIGH', ['from' => 'NG', 'to' => 'CA', 'minutes' => 10], '0.9700', 3600);
        app(SecuritySignalService::class)->record('MFA_RESET_RECENT', 'ACCOUNT_RECOVERY', 'USER', $user->id, 'HIGH', [], '0.9500', 86400);

        $decision = app(WithdrawalSecurityService::class)->evaluate($user, 'USDT', '2500', 'TRC20', 'TAddressPhase18');

        $this->assertContains($decision['decision'], ['BLOCK', 'EMERGENCY_LOCK']);
        $this->assertContains('IMPOSSIBLE_TRAVEL', $decision['reason_codes']);
        $this->assertContains('WITHDRAWAL_ADDRESS_NEW', $decision['reason_codes']);
        $this->assertDatabaseHas('security_withdrawal_addresses', ['user_id' => $user->id, 'asset' => 'USDT']);
        $this->assertGreaterThanOrEqual(5, SecurityRiskSignal::query()->where('subject_id', $user->id)->count());
    }

    public function test_withdrawal_velocity_finance_break_and_fail_closed_sensitive_action(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            Withdrawal::query()->create([
                'user_id' => $user->id,
                'transaction_id' => null,
                'currency' => 'USDT',
                'amount' => '10',
                'fee' => '0',
                'address' => 'addr-'.$i,
                'network' => 'TRC20',
                'status' => 'pending',
                'metadata' => [],
            ]);
        }
        FinanceReconciliationBreak::query()->create([
            'break_uuid' => (string) Str::uuid(),
            'scope' => 'BACKING',
            'code' => 'BACKING_CRITICAL',
            'severity' => 'CRITICAL',
            'subject_type' => 'ASSET',
            'subject_reference' => 'USDT',
            'status' => 'OPEN',
            'message' => 'Backing deficit detected.',
            'evidence' => ['deficit' => '100'],
        ]);

        $decision = app(WithdrawalSecurityService::class)->evaluate($user, 'USDT', '100', 'TRC20', 'TVelocity');
        $failClosed = app(SecurityRiskEngine::class)->evaluate('USER', $user->id, 'WITHDRAWAL', ['engine_available' => false]);

        $this->assertContains('WITHDRAWAL_VELOCITY', $decision['reason_codes']);
        $this->assertContains('FINANCE_RECONCILIATION_CRITICAL', $decision['reason_codes']);
        $this->assertSame('BLOCK', $failClosed['decision']);
        $this->assertContains('SECURITY_ENGINE_UNAVAILABLE_FAIL_CLOSED', $failClosed['reason_codes']);
    }

    public function test_api_compromise_related_accounts_market_surveillance_and_abuse_cases(): void
    {
        $owner = User::factory()->create();
        $related = User::factory()->create();
        $project = DeveloperProject::query()->create([
            'project_uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'name' => 'Phase 18 Project',
            'environment' => 'production',
            'status' => 'active',
        ]);
        $key = DeveloperApiKey::query()->create([
            'key_uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'project_id' => $project->id,
            'name' => 'Prod key',
            'environment' => 'production',
            'key_prefix' => 'exa_live_test',
            'key_hash' => hash('sha256', 'key'),
            'encrypted_secret' => 'encrypted',
            'secret_hash' => hash('sha256', 'secret'),
            'status' => 'active',
        ]);

        app(ApiSecurityService::class)->recordApiAbuse($owner, 'API_REPLAY_ATTEMPT', ['nonce' => 'n1']);
        $response = app(ApiSecurityService::class)->respondToCompromisedKey($key, 'Replay and region anomaly.');
        $link = app(MarketSurveillanceService::class)->linkAccounts($owner, $related, 'SHARED_DEVICE', ['device' => 'hash-a']);
        $selfTrade = app(MarketSurveillanceService::class)->detectSelfTrade($owner, $related, 'BTCUSDT', ['price' => '100', 'quantity' => '1']);
        $wash = app(MarketSurveillanceService::class)->detectPattern('WASH_TRADING', $owner, ['round_trips' => 8]);
        $quoteStuffing = app(MarketSurveillanceService::class)->detectPattern('QUOTE_STUFFING', $owner, ['cancel_rate' => '98%']);
        $spoofing = app(MarketSurveillanceService::class)->detectPattern('SPOOFING_SIGNAL', $owner, ['large_orders_cancelled' => 20]);

        $this->assertSame('revoked', $response['key_status']);
        $this->assertSame('ACTIVE', $link->status);
        $this->assertSame('CASE_CREATED', $selfTrade['status']);
        $this->assertSame('CASE_CREATED', $wash['status']);
        $this->assertSame('CASE_CREATED', $quoteStuffing['status']);
        $this->assertSame('CASE_CREATED', $spoofing['status']);
        $this->assertGreaterThanOrEqual(5, SecurityCase::query()->count());
        $this->assertTrue(SecurityRelatedAccount::query()->where('relationship_type', 'SHARED_DEVICE')->exists());
    }

    public function test_case_incident_emergency_rule_shadow_admin_routes_and_restart_persistence(): void
    {
        $admin = $this->admin('security-admin@example.com');
        $user = User::factory()->create();
        $case = app(SecurityCaseService::class)->create('ACCOUNT_TAKEOVER', 'HIGH', 'USER', $user->id, ['signals' => ['NEW_DEVICE']]);
        $case = app(SecurityCaseService::class)->transition($case, 'INVESTIGATING', $admin);
        $incident = app(SecurityIncidentService::class)->create('WITHDRAWAL_ATTACK', 'SEV2', 'USER', (string) $user->id);
        $incident = app(SecurityIncidentService::class)->transition($incident, 'CONTAINING', $admin, 'Paused withdrawals.');
        $control = app(SecurityEmergencyControlService::class)->activate($admin, 'PAUSE_WITHDRAWALS', 'USER', (string) $user->id, 'Active withdrawal attack.');
        $rule = app(SecurityRuleService::class)->change($admin, 'withdrawal_velocity_v1', ['threshold' => 3, 'action' => 'TEMPORARY_HOLD'], 'Shadow new velocity rule.', 'SHADOW');
        $decision = app(SecurityRiskEngine::class)->evaluate('USER', $user->id, 'WITHDRAWAL');

        $this->assertSame('INVESTIGATING', $case->status);
        $this->assertSame('CONTAINING', $incident->status);
        $this->assertSame('ACTIVE', $control->status);
        $this->assertSame('SHADOW', $rule->mode);
        $this->assertSame('EMERGENCY_LOCK', $decision['decision']);
        $this->assertSame('READY', app(SecurityReadinessService::class)->evaluate()['status']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/v1/security-operations/overview')->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/v1/security-operations/evaluate', [
            'subject_type' => 'USER',
            'subject_id' => $user->id,
            'action' => 'WITHDRAWAL',
        ])->assertOk()->assertJsonPath('data.decision', 'EMERGENCY_LOCK');
        $this->assertDatabaseHas('security_cases', ['case_uuid' => $case->case_uuid, 'status' => 'INVESTIGATING']);
        $this->assertDatabaseHas('security_incidents', ['incident_uuid' => $incident->incident_uuid, 'status' => 'CONTAINING']);
        $this->assertDatabaseHas('security_emergency_controls', ['control_uuid' => $control->control_uuid, 'status' => 'ACTIVE']);
    }

    public function test_security_invariants_rate_limit_pii_audit_secret_custody_compliance_and_failure_categories_are_represented(): void
    {
        $user = User::factory()->create();
        $signals = app(SecuritySignalService::class);
        foreach ([
            'P2P_HIGH_RISK_PATTERN',
            'COPY_TRADING_MANIPULATION',
            'EXAAI_ABNORMAL_ACTIVITY',
            'MM_ABUSE',
            'OTC_ANOMALY',
            'FIAT_FRAUD_SIGNAL',
            'CRYPTO_DEPOSIT_RISK',
            'ADMIN_PRIVILEGE_ANOMALY',
            'CUSTODY_SECURITY_BOUNDARY',
            'COMPLIANCE_ESCALATION',
            'PII_EXPORT_ATTEMPT',
            'SECRET_ACCESS_ATTEMPT',
            'RATE_LIMIT_ABUSE',
            'BOT_AUTOMATION_ABUSE',
        ] as $type) {
            $signals->record($type, 'PHASE18_TEST', 'USER', $user->id, 'MEDIUM', ['redacted' => true], '0.7500', 3600);
        }
        $decision = app(SecurityRiskEngine::class)->evaluate('USER', $user->id, 'ADMIN_PRIVILEGED_ACTION');

        $this->assertContains($decision['decision'], ['BLOCK', 'EMERGENCY_LOCK']);
        $this->assertTrue(SecurityRiskSignal::query()->where('signal_type', 'PII_EXPORT_ATTEMPT')->exists());
        $this->assertTrue(SecurityRiskSignal::query()->where('signal_type', 'SECRET_ACCESS_ATTEMPT')->exists());
        $this->assertGreaterThanOrEqual(14, count($decision['reason_codes']));
    }

    private function admin(string $email): Admin
    {
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);
        $permissions = ['security.operations'];
        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission]);
        }
        app(PermissionService::class)->syncRolePermissions($role, $permissions);

        return Admin::query()->create([
            'name' => 'Security Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }
}
