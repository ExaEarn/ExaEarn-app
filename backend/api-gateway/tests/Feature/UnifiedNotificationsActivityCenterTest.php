<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Cards\CardNotificationService;
use App\Services\ActivityAuditService;
use App\Services\NotificationService;
use App\Services\NotificationTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class UnifiedNotificationsActivityCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_events_are_idempotent_and_logged(): void
    {
        $user = User::factory()->create(['email' => 'notify@example.com']);
        $service = app(NotificationService::class);

        $first = $service->emit($user, 'deposit.completed', [
            'title' => 'Deposit completed',
            'message' => 'Your USDT deposit is available.',
            'amount' => '500.00',
            'asset' => 'USDT',
            'deep_link' => '/assets',
        ], 'deposit-001', ['in_app']);

        $second = $service->emit($user, 'deposit.completed', [
            'title' => 'Deposit completed',
            'message' => 'Your USDT deposit is available.',
            'amount' => '500.00',
            'asset' => 'USDT',
            'deep_link' => '/assets',
        ], 'deposit-001', ['in_app']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Notification::query()->where('event_key', 'deposit.completed')->count());
        $this->assertDatabaseHas('notifications', [
            'id' => $first->id,
            'product' => 'WALLET',
            'category' => 'TRANSACTIONAL',
            'mandatory' => true,
        ]);
        $this->assertGreaterThanOrEqual(2, NotificationLog::query()->where('notification_id', $first->id)->count());
        $this->assertDatabaseHas('notification_logs', [
            'notification_id' => $first->id,
            'event' => 'deduplicated',
            'status' => 'SUPPRESSED',
        ]);
    }

    public function test_preferences_preserve_mandatory_financial_and_security_events(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/notifications/preferences', [
            'preferences' => [
                [
                    'scope' => 'security',
                    'in_app_enabled' => false,
                    'email_enabled' => false,
                    'push_enabled' => false,
                ],
                [
                    'scope' => 'marketing',
                    'in_app_enabled' => false,
                    'email_enabled' => false,
                    'push_enabled' => false,
                    'marketing_consent' => false,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('success', true);

        $response = $this->actingAs($user)->getJson('/api/notifications/preferences');
        $response->assertOk();

        $security = collect($response->json('data'))->firstWhere('scope', 'security');
        $marketing = collect($response->json('data'))->firstWhere('scope', 'marketing');

        $this->assertTrue($security['mandatory']);
        $this->assertTrue($security['in_app_enabled']);
        $this->assertFalse($marketing['marketing_consent']);
    }

    public function test_optional_notifications_can_be_suppressed_without_affecting_mandatory_delivery(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/notifications/preferences', [
            'preferences' => [[
                'scope' => 'marketing',
                'in_app_enabled' => false,
                'email_enabled' => false,
                'push_enabled' => false,
                'marketing_consent' => false,
            ]],
        ])->assertOk();

        $marketing = app(NotificationService::class)->emit(
            $user,
            'marketing.product_update',
            ['title' => 'Product update', 'message' => 'A new feature is available.'],
            'marketing-001',
        );

        $security = app(NotificationService::class)->emit(
            $user,
            'security.new_device',
            ['title' => 'New device', 'message' => 'A new device signed in.'],
            'security-001',
            ['in_app'],
        );

        $this->assertSame('suppressed', $marketing->status);
        $this->assertSame('sent', $security->status);
    }

    public function test_delete_archives_notification_and_keeps_activity_feed(): void
    {
        $user = User::factory()->create();
        app(ActivityAuditService::class)->logWallet($user->id, 'deposit', [
            'amount' => '500.00',
            'asset' => 'USDT',
            'reference' => 'deposit-archive',
        ]);
        $notification = app(NotificationService::class)->emit(
            $user,
            'deposit.completed',
            ['title' => 'Deposit completed', 'message' => 'Funds are available.', 'asset' => 'USDT'],
            'deposit-archive',
            ['in_app'],
        );

        $this->actingAs($user)->deleteJson("/api/notifications/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Notification archived');

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'status' => 'archived',
        ]);

        $center = $this->actingAs($user)->getJson('/api/activity-center');
        $center->assertOk();
        $this->assertSame(0, $center->json('data.notifications.pagination.total'));
        $this->assertSame(1, $center->json('data.activity.pagination.total'));
    }

    public function test_activity_center_filters_authoritative_activity_categories(): void
    {
        $user = User::factory()->create();
        app(ActivityAuditService::class)->logTrade($user->id, 'order_filled', ['symbol' => 'BTC/USDT']);
        app(ActivityAuditService::class)->logWallet($user->id, 'withdrawal', ['asset' => 'USDT']);

        $response = $this->actingAs($user)->getJson('/api/activity-center/activity?category=trading');

        $response->assertOk()
            ->assertJsonPath('data.items.0.category', 'trading')
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_external_deep_links_are_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        app(NotificationService::class)->emit(
            User::factory()->create(),
            'security.new_device',
            ['title' => 'Unsafe', 'message' => 'Bad link.', 'deep_link' => 'https://evil.example'],
            'unsafe-link',
        );
    }

    public function test_template_center_uses_user_locale_with_english_fallback(): void
    {
        $user = User::factory()->create([
            'preferences' => ['language_region' => ['locale' => 'fr']],
        ]);

        $notification = app(NotificationService::class)->emit(
            $user,
            'deposit.completed',
            ['asset' => 'USDT', 'amount' => '25.00', 'deep_link' => '/assets'],
            'deposit-template',
            ['in_app'],
        );

        $this->assertSame('Deposit completed', $notification->title);
        $this->assertSame('Your USDT deposit is now available.', $notification->message);
        $this->assertDatabaseHas('notification_templates', [
            'template_key' => 'deposit.completed',
            'channel' => 'in_app',
            'locale' => 'en',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_template_preview_validates_required_and_unexpected_variables(): void
    {
        $preview = app(NotificationTemplateService::class)->preview(
            'exacard.funding.completed',
            'in_app',
            'en',
            ['amount' => '10.00', 'currency' => 'USD'],
        );

        $this->assertSame('ExaCard funding completed', $preview['title']);
        $this->assertSame('Your ExaCard funding of USD 10.00 was completed.', $preview['body']);

        $this->expectException(RuntimeException::class);
        app(NotificationTemplateService::class)->preview(
            'exacard.funding.completed',
            'in_app',
            'en',
            ['amount' => '10.00', 'currency' => 'USD', 'pan' => '4111111111111111'],
        );
    }

    public function test_exacard_notification_wrapper_uses_registered_event_path(): void
    {
        $user = User::factory()->create();

        app(CardNotificationService::class)->fundingCompleted($user, 'funding-123', '50.00', 'USD');
        app(CardNotificationService::class)->fundingCompleted($user, 'funding-123', '50.00', 'USD');

        $this->assertSame(1, Notification::query()->where('event_key', 'exacard.funding.completed')->count());
        $this->assertDatabaseHas('notifications', [
            'event_key' => 'exacard.funding.completed',
            'product' => 'EXACARD',
            'category' => 'TRANSACTIONAL',
            'mandatory' => true,
        ]);
    }
}
