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
        $this->putJson('/api/preferences/dashboard', ['mode' => 'personalized', 'primary_interest' => 'exaskills', 'selected_interests' => ['exaskills', 'earn'], 'quick_access_services' => ['exapay', 'p2p', 'exaskills'], 'onboarding_completed' => true])->assertOk()->assertJsonPath('data.primary_interest', 'exaskills')->assertJsonPath('data.quick_access_services', ['exapay', 'p2p', 'exaskills']);
        $this->assertSame(['exaskills', 'earn'], $user->fresh()->preferences['dashboard']['selected_interests']);
        $this->assertSame(['exapay', 'p2p', 'exaskills'], $user->fresh()->preferences['dashboard']['quick_access_services']);
        $this->assertArrayNotHasKey('quick_access', $user->fresh()->preferences['dashboard']);
        $this->deleteJson('/api/preferences/dashboard')->assertOk()->assertJsonPath('data.mode', 'all');
        $this->assertArrayNotHasKey('dashboard', $user->fresh()->preferences ?? []);
    }
    public function test_quick_access_is_limited_to_four_known_services(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/preferences/dashboard')->assertOk()->assertJsonPath('data.quick_access_services', ['exapay', 'p2p', 'rewards']);
        $this->putJson('/api/preferences/dashboard', ['mode' => 'all', 'quick_access_services' => ['exapay', 'p2p', 'rewards', 'exaskills', 'nft']])->assertUnprocessable();
        $this->putJson('/api/preferences/dashboard', ['mode' => 'all', 'quick_access_services' => ['exapay', 'unknown-service']])->assertUnprocessable();
        $this->putJson('/api/preferences/dashboard', ['mode' => 'all', 'quick_access_services' => ['exapay', 'exapay']])->assertUnprocessable();
    }
    public function test_unavailable_saved_shortcut_is_not_mutated_by_unrelated_dashboard_update(): void
    {
        config(['exacard.production_issuance_enabled' => false]);
        $user = User::factory()->create([
            'preferences' => ['dashboard' => ['mode' => 'all', 'quick_access_services' => ['exacard', 'p2p']]],
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/preferences/dashboard')
            ->assertOk()
            ->assertJsonPath('data.quick_access_services', ['p2p'])
            ->assertJsonPath('data.quick_access', ['p2p']);

        $this->putJson('/api/preferences/dashboard', ['mode' => 'all', 'onboarding_completed' => true])
            ->assertOk()
            ->assertJsonPath('data.quick_access_services', ['p2p']);

        $this->assertSame(
            ['exacard', 'p2p'],
            $user->fresh()->preferences['dashboard']['quick_access_services'],
        );
    }
    public function test_dashboard_update_cannot_change_another_users_preferences(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create([
            'preferences' => ['dashboard' => ['mode' => 'all', 'quick_access_services' => ['rewards']]],
        ]);

        Sanctum::actingAs($first);
        $this->putJson('/api/preferences/dashboard', [
            'mode' => 'all',
            'quick_access_services' => ['exapay', 'p2p'],
        ])->assertOk();

        $this->assertSame(
            ['rewards'],
            $second->fresh()->preferences['dashboard']['quick_access_services'],
        );
        $this->assertSame(
            ['exapay', 'p2p'],
            $first->fresh()->preferences['dashboard']['quick_access_services'],
        );
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
