<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Models\User;
use App\Models\Notification;
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
    public function test_rejects_unknown_or_more_than_three_interests(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->putJson('/api/preferences/dashboard', ['mode' => 'personalized', 'primary_interest' => 'fake', 'selected_interests' => ['crypto_exchange', 'earn', 'games', 'fake']])->assertUnprocessable();
    }
    public function test_critical_account_alerts_are_returned_independently_of_personalization(): void
    {
        $user = User::factory()->create(['preferences' => ['dashboard' => ['mode' => 'personalized', 'primary_interest' => 'nft_marketplace', 'selected_interests' => ['nft_marketplace']]]]);
        Notification::query()->create(['user_id' => $user->id, 'type' => 'security', 'title' => 'Secure your account', 'message' => 'Review a new login.', 'status' => 'sent', 'channel' => 'in_app']);
        Sanctum::actingAs($user);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.preferences.primary_interest', 'nft_marketplace')->assertJsonPath('data.critical_alerts.0.kind', 'security')->assertJsonPath('data.critical_alerts.0.title', 'Secure your account');
    }
}
