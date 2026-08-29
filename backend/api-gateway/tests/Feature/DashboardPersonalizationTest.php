<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Models\User;
use App\Models\Notification;
use App\Services\ExperienceRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
final class DashboardPersonalizationTest extends TestCase
{
    use RefreshDatabase;
    public function test_missing_preferences_returns_all_exaearn_default(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.preferences.mode', 'all')->assertJsonPath('data.preferences.selected_interests', []);
    }
    public function test_user_can_save_and_reset_own_dashboard_preferences(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->putJson('/api/preferences/dashboard', ['mode' => 'personalized', 'primary_interest' => 'exaskills', 'selected_interests' => ['exaskills', 'earn'], 'onboarding_completed' => true])->assertOk()->assertJsonPath('data.primary_interest', 'exaskills');
        $this->assertSame(['exaskills', 'earn'], $user->fresh()->preferences['dashboard']['selected_interests']);
        $this->deleteJson('/api/preferences/dashboard')->assertOk()->assertJsonPath('data.mode', 'all');
        $this->assertArrayNotHasKey('dashboard', $user->fresh()->preferences ?? []);
    }
    public function test_accepts_six_intents_and_rejects_unknown_interests(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $intents = ['trade_invest', 'earn_grow', 'pay_spend', 'learn_build', 'explore_opportunities', 'play_earn'];
        $this->putJson('/api/preferences/dashboard', ['mode' => 'personalized', 'primary_interest' => 'trade_invest', 'selected_interests' => $intents])->assertOk();
        $this->assertSame($intents, $user->fresh()->preferences['dashboard']['selected_interests']);
        $this->putJson('/api/preferences/dashboard', ['mode' => 'personalized', 'primary_interest' => 'fake', 'selected_interests' => ['crypto_exchange', 'earn', 'games', 'fake']])->assertUnprocessable();
    }
    public function test_critical_account_alerts_are_returned_independently_of_personalization(): void
    {
        $user = User::factory()->create(['preferences' => ['dashboard' => ['mode' => 'personalized', 'primary_interest' => 'nft_marketplace', 'selected_interests' => ['nft_marketplace']]]]);
        Notification::query()->create(['user_id' => $user->id, 'type' => 'security', 'title' => 'Secure your account', 'message' => 'Review a new login.', 'status' => 'sent', 'channel' => 'in_app']);
        Sanctum::actingAs($user);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.preferences.primary_interest', 'nft_marketplace')->assertJsonPath('data.critical_alerts.0.kind', 'security')->assertJsonPath('data.critical_alerts.0.title', 'Secure your account');
    }

    public function test_experience_recommendations_are_deterministic(): void
    {
        $service = app(ExperienceRecommendationService::class);
        $this->assertSame('lite', $service->recommend('experienced', 'send_pay', ['payments', 'exacard'])['recommended_mode']);
        $this->assertSame('pro', $service->recommend('intermediate', 'trade_smarter', ['trading', 'exaai', 'copy_trading'])['recommended_mode']);
        $this->assertSame(['earn'], $service->inferInterests('grow_assets'));
    }

    public function test_user_override_is_preserved_while_recommendation_remains_authoritative(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->putJson('/api/preferences/dashboard', [
            'mode' => 'personalized', 'primary_interest' => 'trade_invest', 'selected_interests' => ['trade_invest'],
            'experience_level' => 'intermediate', 'primary_goal' => 'trade_smarter',
            'interests' => ['trading', 'exaai'], 'selected_mode' => 'lite', 'onboarding_completed' => true,
        ])->assertOk()->assertJsonPath('data.recommended_mode', 'pro')->assertJsonPath('data.selected_mode', 'lite');
    }

    public function test_new_interest_selection_is_bounded_and_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $base = ['mode' => 'personalized', 'primary_interest' => 'trade_invest', 'selected_interests' => ['trade_invest'], 'experience_level' => 'new', 'primary_goal' => 'buy_trade'];
        $this->putJson('/api/preferences/dashboard', $base + ['interests' => ['trading', 'earn', 'payments', 'p2p']])->assertUnprocessable();
        $this->putJson('/api/preferences/dashboard', $base + ['interests' => ['not_real']])->assertUnprocessable();
    }

    public function test_registration_persists_server_normalized_four_step_preferences(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Onboarding User', 'email' => 'onboarding@exaearn.io',
            'password' => 'StrongPass1!', 'password_confirmation' => 'StrongPass1!',
            'dashboard_preferences' => [
                'mode' => 'personalized', 'primary_interest' => 'trade_invest', 'selected_interests' => ['trade_invest'],
                'experience_level' => 'intermediate', 'primary_goal' => 'trade_smarter',
                'interests' => ['trading', 'exaai'], 'selected_mode' => 'lite',
            ],
        ])->assertCreated();
        $dashboard = User::query()->where('email', 'onboarding@exaearn.io')->firstOrFail()->preferences['dashboard'];
        $this->assertSame('pro', $dashboard['recommended_mode']);
        $this->assertSame('lite', $dashboard['selected_mode']);
        $this->assertTrue($dashboard['onboarding_completed']);
        $this->assertSame(4, $dashboard['onboarding_version']);
    }
}
