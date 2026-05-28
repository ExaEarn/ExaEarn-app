<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CampaignEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_nft_campaign(): void
    {
        Redis::shouldReceive('publish')->once();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/campaigns/generate', [
            'type' => 'NFT_MINTED',
            'count' => 120,
            'timeframe' => '24h',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.campaign_title', 'NFT Earning Surge - Activate Now');
        $this->assertStringContainsString('120+', (string) $response->json('data.campaign_message'));
    }

    public function test_generates_user_behavior_campaign(): void
    {
        Redis::shouldReceive('publish')->once();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/campaigns/generate', [
            'type' => 'USER_BEHAVIOR',
            'action' => 'staking',
            'trend' => 'increasing',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('staking', strtolower((string) $response->json('data.user_action_required')));
    }

    public function test_generates_partnership_campaign_without_fabrication(): void
    {
        Redis::shouldReceive('publish')->once();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/campaigns/generate', [
            'type' => 'PARTNERSHIP',
            'status' => 'ANNOUNCED',
            'partner_name' => 'XYZ Protocol',
            'impact' => 'faster fiat withdrawals',
            'benefit_to_users' => 'reduced withdrawal time',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('XYZ Protocol', (string) $response->json('data.campaign_message'));
    }

    public function test_rejects_non_announced_partnership_campaign(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/campaigns/generate', [
            'type' => 'PARTNERSHIP',
            'status' => 'DRAFT',
            'impact' => 'faster withdrawals',
            'benefit_to_users' => 'reduced processing time',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Only announced strategic updates are allowed for campaigns.']);
    }
}
