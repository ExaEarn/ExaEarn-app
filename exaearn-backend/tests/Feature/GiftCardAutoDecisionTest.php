<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\GiftCardSubmission;
use App\Services\GiftCard\GiftCardFraudDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GiftCardAutoDecisionTest extends TestCase
{
    use RefreshDatabase;

    protected GiftCardFraudDetectionService $fraudService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fraudService = app(GiftCardFraudDetectionService::class);
    }

    /** @test */
    public function it_auto_approves_low_risk_submissions()
    {
        $user = User::factory()->create();

        $analysis = $this->fraudService->analyzeRisk($user, 'amazon', '50');

        $this->assertEquals('approve', $analysis['auto_decision']);
        $this->assertLessThanOrEqual(0.1, $analysis['risk_score']);
        $this->assertFalse($analysis['requires_review']);
    }

    /** @test */
    public function it_auto_rejects_high_risk_submissions()
    {
        $user = User::factory()->create();

        // Create multiple submissions to trigger high frequency flag
        for ($i = 0; $i < 15; $i++) {
            GiftCardSubmission::factory()->create([
                'user_id' => $user->id,
                'created_at' => now()->subHours($i),
            ]);
        }

        $analysis = $this->fraudService->analyzeRisk($user, 'amazon', '50');

        $this->assertEquals('reject', $analysis['auto_decision']);
        $this->assertGreaterThanOrEqual(0.8, $analysis['risk_score']);
        $this->assertTrue($analysis['requires_review']);
    }

    /** @test */
    public function it_sends_medium_risk_to_review()
    {
        $user = User::factory()->create();

        // Create some submissions to increase risk slightly
        for ($i = 0; $i < 3; $i++) {
            GiftCardSubmission::factory()->create([
                'user_id' => $user->id,
                'created_at' => now()->subHours($i * 2),
            ]);
        }

        $analysis = $this->fraudService->analyzeRisk($user, 'amazon', '50');

        $this->assertEquals('review', $analysis['auto_decision']);
        $this->assertGreaterThan(0.1, $analysis['risk_score']);
        $this->assertLessThan(0.8, $analysis['risk_score']);
        $this->assertTrue($analysis['requires_review']);
    }

    /** @test */
    public function it_handles_critical_flags_properly()
    {
        $user = User::factory()->create();

        // This test would need to be expanded to trigger critical flags
        // For now, just ensure the method returns expected structure
        $analysis = $this->fraudService->analyzeRisk($user, 'amazon', '50');

        $this->assertArrayHasKey('auto_decision', $analysis);
        $this->assertArrayHasKey('risk_score', $analysis);
        $this->assertArrayHasKey('risk_level', $analysis);
        $this->assertArrayHasKey('flags', $analysis);
        $this->assertArrayHasKey('requires_review', $analysis);
    }
}