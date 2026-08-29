<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CalculateHarvestReturnsJob;
use App\Jobs\DistributeInvestorRewardsJob;
use App\Jobs\VerifyFarmReportsJob;
use App\Models\Farmer;
use App\Models\FarmInvestment;
use App\Models\FarmShare;
use App\Models\FarmingProject;
use App\Models\User;
use App\Models\ComplianceJurisdiction;
use App\Models\CompliancePolicyRule;
use App\Models\LedgerTransaction;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgriTechFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_tokenized_farm_project(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->postJson('/api/agriculture/projects', [
            'project_name' => 'ExaRice Growth Pool - Kebbi',
            'location' => 'Kebbi, Nigeria',
            'crop_type' => 'Rice',
            'farm_size' => 125,
            'investment_target' => 100000,
            'duration' => 12,
            'expected_yield' => 240,
            'status' => 'open',
            'total_shares' => 10000,
            'price_per_share' => 10,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('farming_projects', [
            'project_name' => 'ExaRice Growth Pool - Kebbi',
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('farm_shares', [
            'total_shares' => 10000,
            'shares_available' => 10000,
        ]);
    }

    public function test_investor_purchase_uses_canonical_escrow_and_is_idempotent(): void
    {
        config()->set('agriculture.public_investment_enabled', true);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $investor = User::factory()->create(['role' => 'investor', 'email_verified_at' => now(), 'kyc_verified_at' => now(), 'kyc_level' => 2, 'verified_country' => 'NG']);
        ComplianceJurisdiction::query()->create(['country_code' => 'NG', 'country_name' => 'Nigeria', 'status' => 'SUPPORTED', 'risk_level' => 'STANDARD', 'policy_version' => 'test']);
        CompliancePolicyRule::query()->create([
            'rule_uuid' => (string) Str::uuid(), 'jurisdiction' => 'NG', 'product_code' => 'AGRITECH_INVESTMENT',
            'decision' => 'ALLOW', 'reason_code' => 'TEST_APPROVED', 'status' => 'ACTIVE', 'precedence' => 1000,
            'policy_version' => 'test', 'effective_at' => now()->subMinute(),
        ]);

        $project = FarmingProject::query()->create([
            'created_by' => $admin->id,
            'project_name' => 'ExaCassava Cooperative - Oyo',
            'location' => 'Oyo, Nigeria',
            'crop_type' => 'Cassava',
            'farm_size' => 85,
            'investment_target' => 45000,
            'duration' => 6,
            'expected_yield' => 180,
            'status' => 'open',
            'economic_type' => 'INVESTMENT',
            'legal_status' => 'APPROVED',
            'verification_status' => 'VERIFIED',
            'public_funding_enabled' => true,
            'currency' => 'USDT',
        ]);

        FarmShare::query()->create([
            'project_id' => $project->id,
            'total_shares' => 900,
            'price_per_share' => 50,
            'shares_available' => 900,
        ]);

        app(LedgerService::class)->credit($investor->id, '1000', 'USDT', 'test:agri:funding');
        $response = $this->actingAs($investor)->withHeader('Idempotency-Key', 'agri-test-purchase-1')->postJson("/api/agriculture/projects/{$project->id}/invest", [
            'shares_owned' => 10,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('farm_investments', [
            'user_id' => $investor->id,
            'project_id' => $project->id,
            'shares_owned' => 10,
            'status' => 'locked',
            'financial_status' => 'SETTLED_IN_ESCROW',
        ]);
        $this->assertDatabaseHas('farm_shares', [
            'project_id' => $project->id,
            'shares_available' => 890,
            'shares_allocated' => 10,
        ]);
        $this->assertDatabaseMissing('agri_rewards', ['user_id' => $investor->id, 'project_id' => $project->id, 'activity_type' => 'investment_funding']);
        $this->assertSame(1, LedgerTransaction::query()->where('reference', 'agri:investment:agri-test-purchase-1')->count());
        $this->actingAs($investor)->withHeader('Idempotency-Key', 'agri-test-purchase-1')->postJson("/api/agriculture/projects/{$project->id}/invest", ['shares_owned' => 10])->assertCreated();
        $this->assertSame(1, FarmInvestment::query()->where('idempotency_key', 'agri-test-purchase-1')->count());
    }

    public function test_farmer_can_apply_and_admin_can_approve(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $farmerUser = User::factory()->create(['role' => 'farmer', 'email_verified_at' => now()]);

        $response = $this->actingAs($farmerUser)->postJson('/api/agriculture/farmers/apply', [
            'location' => 'Kaduna, Nigeria',
            'experience_years' => 8,
            'equipment_details' => ['tractor' => true],
        ]);

        $response->assertCreated();
        $farmerId = Farmer::query()->where('user_id', $farmerUser->id)->value('id');
        Farmer::query()->whereKey($farmerId)->update(['identity_status' => 'VERIFIED', 'land_verification_status' => 'VERIFIED']);

        $reviewResponse = $this->actingAs($admin)->patchJson("/api/agriculture/farmers/{$farmerId}/review", [
            'verification_status' => 'APPROVED',
        ]);

        $reviewResponse->assertOk();
        $this->assertDatabaseHas('farmers', [
            'id' => $farmerId,
            'verification_status' => 'approved',
            'state' => 'APPROVED',
        ]);
    }

    public function test_farmer_progress_is_queued_but_unverified_harvest_cannot_settle(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $investor = User::factory()->create(['role' => 'investor', 'email_verified_at' => now()]);
        $farmerUser = User::factory()->create(['role' => 'farmer', 'email_verified_at' => now()]);

        $project = FarmingProject::query()->create([
            'created_by' => $admin->id,
            'project_name' => 'ExaMaize Cluster - Kaduna',
            'location' => 'Kaduna, Nigeria',
            'crop_type' => 'Maize',
            'farm_size' => 60,
            'investment_target' => 40000,
            'duration' => 6,
            'expected_yield' => 120,
            'status' => 'active',
            'verification_status' => 'VERIFIED',
        ]);

        FarmShare::query()->create([
            'project_id' => $project->id,
            'total_shares' => 1000,
            'price_per_share' => 40,
            'shares_available' => 980,
        ]);

        $investment = FarmInvestment::query()->create([
            'user_id' => $investor->id,
            'project_id' => $project->id,
            'shares_owned' => 20,
            'investment_amount' => 800,
            'status' => 'locked',
        ]);

        $farmer = Farmer::query()->create([
            'user_id' => $farmerUser->id,
            'name' => 'Adebayo Musa',
            'location' => 'Kaduna, Nigeria',
            'experience_years' => 10,
            'verification_status' => 'approved',
            'state' => 'APPROVED',
            'identity_status' => 'VERIFIED',
            'land_verification_status' => 'VERIFIED',
        ]);

        $this->actingAs($admin)->postJson("/api/agriculture/projects/{$project->id}/leases", [
            'farmer_id' => $farmer->id,
            'investment_id' => $investment->id,
            'lease_terms' => 'Farmer operates production and reports weekly.',
            'profit_share' => 30,
            'status' => 'active',
        ])->assertCreated();

        $this->actingAs($farmerUser)->postJson("/api/agriculture/projects/{$project->id}/produce-updates", [
            'growth_stage' => 'growing',
            'update_description' => 'Crop growth is on schedule.',
            'images' => ['https://example.com/report.jpg'],
        ])->assertCreated();

        Queue::assertPushed(VerifyFarmReportsJob::class);

        $this->actingAs($admin)->withHeader('Idempotency-Key', 'unverified-harvest')->postJson("/api/agriculture/projects/{$project->id}/settlements", [
            'gross_revenue' => 5000,
            'verified_costs' => 750,
            'period_key' => '2026-H1',
            'revenue_source_type' => 'PRODUCE_BUYER',
            'revenue_reference' => 'buyer-payment-1',
            'evidence_id' => 999999,
        ])->assertUnprocessable();

        Queue::assertNotPushed(CalculateHarvestReturnsJob::class);
        Queue::assertNotPushed(DistributeInvestorRewardsJob::class);
    }
}
