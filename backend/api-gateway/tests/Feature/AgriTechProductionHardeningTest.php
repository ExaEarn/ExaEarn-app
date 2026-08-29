<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AgriHarvestSettlement;
use App\Models\AgriProjectMilestone;
use App\Models\ComplianceJurisdiction;
use App\Models\CompliancePolicyRule;
use App\Models\Farmer;
use App\Models\FarmInvestment;
use App\Models\FarmShare;
use App\Models\FarmingProject;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\AgriTech\AgriDisbursementService;
use App\Services\AgriTech\AgriAccountClosureService;
use App\Services\AgriTech\AgriEvidenceService;
use App\Services\AgriTech\AgriProjectStateService;
use App\Services\AgriTech\AgriRefundService;
use App\Services\AgriTech\AgriTechReconciliationService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgriTechProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_investment_fails_closed_by_default(): void
    {
        [$project, $investor] = $this->projectAndInvestor(enablePublic: false);
        $this->actingAs($investor)->withHeader('Idempotency-Key', 'disabled-investment')->postJson("/api/agriculture/projects/{$project->id}/invest", ['shares_owned' => 1])
            ->assertUnprocessable()->assertJsonPath('message', 'Public AgriTech investment is disabled pending approval.');
        $this->assertDatabaseCount('farm_investments', 0);
        $this->assertSame(1, (int) $project->share->shares_available);
    }

    public function test_last_share_cannot_be_oversold_and_every_allocation_has_ledger_backing(): void
    {
        [$project, $first] = $this->projectAndInvestor();
        $second = $this->eligibleInvestor('second@example.test');
        app(LedgerService::class)->credit($second->id, '100', 'USDT', 'agri:test:fund:second');

        $this->actingAs($first)->withHeader('Idempotency-Key', 'last-share-first')->postJson("/api/agriculture/projects/{$project->id}/invest", ['shares_owned' => 1])->assertCreated();
        $this->actingAs($second)->withHeader('Idempotency-Key', 'last-share-second')->postJson("/api/agriculture/projects/{$project->id}/invest", ['shares_owned' => 1])->assertUnprocessable();

        $this->assertSame(1, FarmInvestment::query()->where('financial_status', 'SETTLED_IN_ESCROW')->count());
        $this->assertSame(1, LedgerTransaction::query()->where('transaction_type', 'agritech_investment')->count());
        $share = FarmShare::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertSame(0, (int) $share->shares_available);
        $this->assertSame(0, (int) $share->shares_reserved);
        $this->assertSame(1, (int) $share->shares_allocated);
    }

    public function test_verified_revenue_is_required_and_payout_is_idempotent(): void
    {
        [$project, $investor] = $this->projectAndInvestor();
        $this->actingAs($investor)->withHeader('Idempotency-Key', 'harvest-investment')->postJson("/api/agriculture/projects/{$project->id}/invest", ['shares_owned' => 1])->assertCreated();
        $project->update(['status' => 'HARVEST_PENDING']);
        $evidenceId = DB::table('agri_project_evidence')->insertGetId([
            'project_id' => $project->id, 'evidence_type' => 'HARVEST_REVENUE', 'source_type' => 'MARKET_RECEIPT',
            'status' => 'APPROVED', 'external_reference' => 'receipt-verified-1', 'metadata' => json_encode([]),
            'submitted_by' => $investor->id, 'reviewed_by' => $project->created_by, 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $admin = User::query()->findOrFail($project->created_by);
        $payload = [
            'gross_revenue' => '120', 'verified_costs' => '20', 'period_key' => '2026-H1',
            'revenue_source_type' => 'PRODUCE_BUYER', 'revenue_reference' => 'buyer-settlement-1', 'evidence_id' => $evidenceId, 'asset' => 'USDT',
        ];
        $this->actingAs($admin)->withHeader('Idempotency-Key', 'harvest-settlement-1')->postJson("/api/agriculture/projects/{$project->id}/settlements", $payload)->assertCreated();
        $this->actingAs($admin)->withHeader('Idempotency-Key', 'harvest-settlement-1')->postJson("/api/agriculture/projects/{$project->id}/settlements", $payload)->assertCreated();

        $this->assertSame(1, AgriHarvestSettlement::query()->count());
        $this->assertSame(1, LedgerTransaction::query()->where('transaction_type', 'agritech_investor_payout')->count());
        $this->assertSame('PASS', app(AgriTechReconciliationService::class)->reconcile($project->id)['status']);
    }

    public function test_maker_checker_disbursement_uses_escrow_and_different_approvers(): void
    {
        [$project, $investor] = $this->projectAndInvestor();
        $this->actingAs($investor)->withHeader('Idempotency-Key', 'disbursement-investment')->postJson("/api/agriculture/projects/{$project->id}/invest", ['shares_owned' => 1])->assertCreated();
        $farmerUser = User::factory()->create(['role' => 'farmer']);
        $farmer = Farmer::query()->create(['user_id' => $farmerUser->id, 'name' => 'Verified Farmer', 'location' => 'NG', 'experience_years' => 5, 'verification_status' => 'approved', 'state' => 'APPROVED', 'identity_status' => 'VERIFIED', 'land_verification_status' => 'VERIFIED']);
        $milestone = AgriProjectMilestone::query()->create(['project_id' => $project->id, 'title' => 'Planting', 'release_amount' => '40', 'asset' => 'USDT', 'status' => 'APPROVED', 'evidence_required' => false]);
        $maker = User::query()->findOrFail($project->created_by);
        $checker = User::factory()->create(['role' => 'admin']);
        $service = app(AgriDisbursementService::class);
        $row = $service->request($maker, $milestone->id, $farmer->id, '40', 'disbursement-1');
        $row = $service->approve($maker, $row->id);
        try {
            $service->checkAndSettle($maker, $row->id);
            $this->fail('Maker must not check their own disbursement.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('different authorized approver', $exception->getMessage());
        }
        $settled = $service->checkAndSettle($checker, $row->id);
        $this->assertSame('SETTLED', $settled->status);
        $this->assertSame(1, LedgerTransaction::query()->where('transaction_type', 'agritech_disbursement')->count());
    }

    public function test_evidence_submission_never_auto_verifies_farmer(): void
    {
        [$project, $submitter] = $this->projectAndInvestor();
        $farmerUser = User::factory()->create(['role' => 'farmer']);
        $farmer = Farmer::query()->create([
            'user_id' => $farmerUser->id, 'name' => 'Pending Farmer', 'location' => 'NG',
            'verification_status' => 'pending', 'state' => 'UNDER_REVIEW',
            'identity_status' => 'PENDING', 'land_verification_status' => 'PENDING',
        ]);
        $service = app(AgriEvidenceService::class);
        $evidence = $service->submit($submitter, $project->id, [
            'farmer_id' => $farmer->id, 'evidence_type' => 'LAND_TITLE',
            'source_type' => 'PRIVATE_DOCUMENT', 'storage_reference' => 'private/agri/evidence-1',
        ]);
        $this->assertSame('PENDING_REVIEW', $evidence->status);
        $this->assertSame('PENDING', $farmer->fresh()->land_verification_status);

        $service->review(User::query()->findOrFail($project->created_by), $evidence->id, 'APPROVED', 'Reviewed against source record.');
        $this->assertSame('VERIFIED', $farmer->fresh()->land_verification_status);
    }

    public function test_project_state_machine_fails_closed_without_verification_or_legal_approval(): void
    {
        [$project] = $this->projectAndInvestor();
        $admin = User::query()->findOrFail($project->created_by);
        $project->update(['status' => 'UNDER_REVIEW', 'verification_status' => 'PENDING']);
        $this->expectExceptionMessage('Verified farm, land and project evidence is required.');
        app(AgriProjectStateService::class)->transition($admin, $project->id, 'APPROVED', 'Attempted approval');
    }

    public function test_refund_is_idempotent_and_account_closure_is_blocked_until_resolved(): void
    {
        [$project, $investor] = $this->projectAndInvestor();
        $this->actingAs($investor)->withHeader('Idempotency-Key', 'refund-investment')->postJson("/api/agriculture/projects/{$project->id}/invest", ['shares_owned' => 1])->assertCreated();
        $this->assertNotEmpty(app(AgriAccountClosureService::class)->blockers($investor->id));
        $project->update(['status' => 'REFUNDING']);
        $investment = FarmInvestment::query()->where('user_id', $investor->id)->firstOrFail();
        $admin = User::query()->findOrFail($project->created_by);
        app(AgriRefundService::class)->refund($admin, $investment->id, 'Project cancelled');
        app(AgriRefundService::class)->refund($admin, $investment->id, 'Retry');

        $this->assertSame(1, LedgerTransaction::query()->where('transaction_type', 'agritech_refund')->count());
        $this->assertSame([], app(AgriAccountClosureService::class)->blockers($investor->id));
        $this->assertSame(1, (int) FarmShare::query()->where('project_id', $project->id)->value('shares_available'));
    }

    private function projectAndInvestor(bool $enablePublic = true): array
    {
        config()->set('agriculture.public_investment_enabled', $enablePublic);
        $admin = User::factory()->create(['role' => 'admin']);
        $investor = $this->eligibleInvestor('investor-' . Str::random(6) . '@example.test');
        $project = FarmingProject::query()->create([
            'created_by' => $admin->id, 'project_name' => 'Verified Farm', 'location' => 'Nigeria', 'crop_type' => 'Rice',
            'farm_size' => 10, 'investment_target' => 100, 'duration' => 6, 'expected_yield' => 10, 'status' => 'OPEN',
            'economic_type' => 'INVESTMENT', 'legal_status' => 'APPROVED', 'verification_status' => 'VERIFIED',
            'public_funding_enabled' => true, 'currency' => 'USDT',
        ]);
        FarmShare::query()->create(['project_id' => $project->id, 'total_shares' => 1, 'price_per_share' => 100, 'shares_available' => 1]);
        app(LedgerService::class)->credit($investor->id, '500', 'USDT', 'agri:test:fund:' . $investor->id);
        return [$project->fresh('share'), $investor];
    }

    private function eligibleInvestor(string $email): User
    {
        ComplianceJurisdiction::query()->firstOrCreate(['country_code' => 'NG'], ['country_name' => 'Nigeria', 'status' => 'SUPPORTED', 'risk_level' => 'STANDARD', 'policy_version' => 'test']);
        CompliancePolicyRule::query()->firstOrCreate(['product_code' => 'AGRITECH_INVESTMENT', 'jurisdiction' => 'NG'], [
            'rule_uuid' => (string) Str::uuid(), 'decision' => 'ALLOW', 'reason_code' => 'TEST_APPROVED', 'status' => 'ACTIVE',
            'precedence' => 1000, 'policy_version' => 'test', 'effective_at' => now()->subMinute(),
        ]);
        return User::factory()->create(['email' => $email, 'role' => 'investor', 'kyc_verified_at' => now(), 'kyc_level' => 2, 'verified_country' => 'NG']);
    }
}
