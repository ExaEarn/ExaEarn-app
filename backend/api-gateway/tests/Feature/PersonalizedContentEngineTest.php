<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\PersonalizedContent;
use App\Models\Role;
use App\Models\User;
use App\Services\PersonalizedContent\ProductEventContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PersonalizedContentEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_schedule_and_publish_safe_content(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin)->postJson('/api/admin/personalized-content', [
            'type' => 'PROMOTION', 'title' => 'Zero-Fee BTC Trading', 'body' => 'Eligible BTC markets have a temporary fee promotion.',
            'cta_label' => 'Trade Now', 'cta_route' => 'trade', 'related_product' => 'SPOT', 'related_asset' => 'BTC',
            'target_interests' => ['trade_invest'], 'target_experience_modes' => ['LITE', 'PRO'], 'publish_at' => now()->addHour()->toISOString(), 'status' => 'SCHEDULED',
        ])->assertCreated()->assertJsonPath('data.status', 'SCHEDULED');
        $id = (int) $response->json('data.id');
        $this->actingAs($admin)->postJson("/api/admin/personalized-content/{$id}/publish")->assertOk()->assertJsonPath('data.status', 'PUBLISHED');
        $this->assertDatabaseHas('audit_logs', ['action' => 'personalized_content.publish']);
    }

    public function test_admin_content_rejects_unsafe_cta_and_unauthorized_admin(): void
    {
        $restricted = Admin::query()->create(['name' => 'Restricted', 'email' => 'restricted-content@example.com', 'password' => 'secret-password', 'role_id' => Role::query()->create(['name' => 'support'])->id, 'status' => 'active', 'two_factor_enabled' => true]);
        $this->actingAs($restricted)->postJson('/api/admin/personalized-content', ['type' => 'CAMPAIGN', 'title' => 'Unsafe', 'cta_route' => 'https://evil.example'])->assertForbidden();
        $this->actingAs($this->admin())->postJson('/api/admin/personalized-content', ['type' => 'CAMPAIGN', 'title' => 'Unsafe', 'cta_route' => 'https://evil.example'])->assertUnprocessable();
    }

    public function test_product_event_generation_is_allowlisted_and_idempotent(): void
    {
        $service = app(ProductEventContentService::class);
        $first = $service->ingest('earn.product.activated', 'earn-event-1', ['title' => 'New SOL Earn Opportunity', 'body' => 'Explore the active SOL Earn product.', 'asset' => 'SOL']);
        $second = $service->ingest('earn.product.activated', 'earn-event-1', ['title' => 'Ignored retry title', 'body' => 'Retry']);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PersonalizedContent::query()->count());
        $this->expectException(\InvalidArgumentException::class);
        $service->ingest('deposit.completed', 'deposit-1', ['title' => 'Deposit', 'body' => 'Not discovery content']);
    }

    public function test_personalization_ranks_primary_interest_and_filters_region_and_expiry(): void
    {
        $user = User::factory()->create(['verified_country' => 'NG', 'preferences' => ['dashboard' => ['mode' => 'personalized', 'primary_interest' => 'earn_grow', 'selected_interests' => ['earn_grow', 'trade_invest']], 'experience_mode' => 'pro']]);
        $earn = $this->content(['title' => 'Earn First', 'target_interests' => ['earn_grow'], 'related_product' => 'EARN']);
        $this->content(['title' => 'Trading Second', 'target_interests' => ['trade_invest'], 'related_product' => 'SPOT']);
        $this->content(['title' => 'Wrong country', 'target_countries' => ['US']]);
        $this->content(['title' => 'Expired', 'expires_at' => now()->subMinute()]);
        $this->content(['title' => 'Future schedule', 'status' => 'SCHEDULED', 'publish_at' => now()->addHour()]);
        Sanctum::actingAs($user);
        $this->getJson('/api/personalized-content/dashboard')->assertOk()->assertJsonPath('data.0.id', $earn->id)->assertJsonMissing(['title' => 'Wrong country'])->assertJsonMissing(['title' => 'Expired'])->assertJsonMissing(['title' => 'Future schedule']);
    }

    public function test_dismissal_and_frequency_cap_remove_content_from_delivery(): void
    {
        $user = User::factory()->create(); Sanctum::actingAs($user);
        $dismissed = $this->content(['title' => 'Dismiss me']);
        $this->postJson("/api/personalized-content/{$dismissed->id}/dismiss", ['event_uuid' => (string) Str::uuid()])->assertCreated();
        $this->getJson('/api/personalized-content/dashboard')->assertJsonMissing(['title' => 'Dismiss me']);
        $capped = $this->content(['title' => 'Frequency capped', 'frequency_cap' => 1]);
        $this->postJson("/api/personalized-content/{$capped->id}/impression", ['event_uuid' => (string) Str::uuid()])->assertCreated();
        $this->getJson('/api/personalized-content/dashboard')->assertJsonMissing(['title' => 'Frequency capped']);
    }

    public function test_feed_is_paginated_and_interaction_retry_is_idempotent(): void
    {
        $user = User::factory()->create(); Sanctum::actingAs($user); $content = $this->content();
        $event = (string) Str::uuid();
        $this->postJson("/api/personalized-content/{$content->id}/click", ['event_uuid' => $event])->assertCreated();
        $this->postJson("/api/personalized-content/{$content->id}/click", ['event_uuid' => $event])->assertCreated();
        $this->assertDatabaseCount('personalized_content_interactions', 1);
        $this->getJson('/api/personalized-content/feed?page=1')->assertOk()->assertJsonPath('data.current_page', 1)->assertJsonPath('data.data.0.id', $content->id);
    }

    private function content(array $overrides = []): PersonalizedContent
    {
        return PersonalizedContent::query()->create(array_merge(['content_uuid' => (string) Str::uuid(), 'type' => 'CAMPAIGN', 'source_type' => 'ADMIN', 'title' => 'General campaign', 'status' => 'PUBLISHED', 'priority' => 50, 'personalization_weight' => 50, 'frequency_cap' => 5, 'publish_at' => now()->subMinute()], $overrides));
    }

    private function admin(): Admin
    {
        return Admin::query()->create(['name' => 'Content Admin', 'email' => Str::uuid().'@example.com', 'password' => 'secret-password', 'role_id' => Role::query()->firstOrCreate(['name' => 'super_admin'])->id, 'status' => 'active', 'two_factor_enabled' => true]);
    }
}
