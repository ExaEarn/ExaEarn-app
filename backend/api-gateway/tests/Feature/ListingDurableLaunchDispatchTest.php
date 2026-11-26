<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ExecuteListingLaunchEvent;
use App\Models\Admin;
use App\Models\ListingApplication;
use App\Models\ListingLaunchEvent;
use App\Models\ListingOrganization;
use App\Models\Role;
use App\Models\User;
use App\Services\ListingLaunchDispatcherService;
use App\Services\ListingLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ListingDurableLaunchDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['listing.require_approved_kyb_for_listing_submission' => false]);
        Queue::fake();
    }

    public function test_duplicate_dispatchers_create_one_effective_queue_delivery(): void
    {
        $event = $this->event();
        $dispatcher = app(ListingLaunchDispatcherService::class);

        $dispatcher->dispatchDue();
        $dispatcher->dispatchDue();

        Queue::assertPushed(ExecuteListingLaunchEvent::class, 1);
        $this->assertSame('DISPATCHED', $event->fresh()->operation_status);
        $this->assertNotNull($event->fresh()->claim_token);
        $this->assertSame(1, $event->fresh()->dispatch_attempt_count);
    }

    public function test_corrective_migration_preserves_terminal_history_and_quarantines_ambiguous_rows(): void
    {
        $success = $this->event(['status' => 'EXECUTED', 'operation_status' => 'DISPATCHED']);
        $failure = $this->event(['status' => 'FAILED', 'operation_status' => 'SCHEDULED']);
        $cancelled = $this->event(['status' => 'CANCELLED', 'operation_status' => 'SCHEDULED']);
        $ambiguous = $this->event([
            'status' => 'PENDING', 'operation_status' => 'DISPATCHED',
            'configuration_fingerprint' => null, 'correlation_id' => null, 'launch_scope' => null,
        ]);
        $legitimate = $this->event(['status' => 'PENDING', 'operation_status' => 'SCHEDULED']);

        $migration = require database_path('migrations/2026_09_10_000001_correct_listing_launch_operation_states.php');
        $migration->repairExistingRows();
        $migration->repairExistingRows();

        $this->assertSame('SUCCEEDED', $success->fresh()->operation_status);
        $this->assertSame('FAILED', $failure->fresh()->operation_status);
        $this->assertSame('CANCELLED', $cancelled->fresh()->operation_status);
        $this->assertSame('LEGACY_UNVERIFIED', $ambiguous->fresh()->operation_status);
        $this->assertSame('LEGACY_STATE_UNVERIFIED', $ambiguous->fresh()->failure_code);
        $this->assertSame('SCHEDULED', $legitimate->fresh()->operation_status);

        app(ListingLaunchDispatcherService::class)->dispatchDue();
        Queue::assertPushed(ExecuteListingLaunchEvent::class, 1);
    }

    public function test_expired_dispatched_lease_is_redispatched_with_a_new_fencing_token(): void
    {
        $event = $this->event();
        $dispatcher = app(ListingLaunchDispatcherService::class);
        $dispatcher->dispatchDue();
        $oldJob = Queue::pushed(ExecuteListingLaunchEvent::class)->first();
        $oldToken = $event->fresh()->claim_token;

        $event->forceFill(['lease_expires_at' => now()->subSecond()])->save();
        $dispatcher->dispatchDue();
        $jobs = Queue::pushed(ExecuteListingLaunchEvent::class)->values();

        $this->assertCount(2, $jobs);
        $this->assertNotSame($oldToken, $event->fresh()->claim_token);
        $this->assertSame(2, $event->fresh()->dispatch_attempt_count);

        $oldJob->handle(app(ListingLifecycleService::class));
        $this->assertSame('DISPATCHED', $event->fresh()->operation_status);
        $jobs->last()->handle(app(ListingLifecycleService::class));
        $this->assertSame('BLOCKED', $event->fresh()->operation_status);
        $this->assertSame(1, $event->fresh()->attempt_count);
    }

    public function test_queue_publish_failure_remains_automatically_recoverable(): void
    {
        $event = $this->event();
        $dispatcher = new class extends ListingLaunchDispatcherService {
            protected function publish(ListingLaunchEvent $event): void
            {
                throw new RuntimeException('Injected queue outage.');
            }
        };

        $result = $dispatcher->dispatchDue();

        $this->assertSame('RETRY_PENDING', $event->fresh()->operation_status);
        $this->assertSame('QUEUE_DISPATCH_FAILED', $event->fresh()->failure_code);
        $this->assertNull($event->fresh()->lease_expires_at);
        $this->assertSame('RETRY_PENDING', $result[0]['status']);

        app(ListingLaunchDispatcherService::class)->dispatchDue();
        Queue::assertPushed(ExecuteListingLaunchEvent::class, 1);
        $this->assertSame('DISPATCHED', $event->fresh()->operation_status);
    }

    public function test_ambiguous_publish_redelivery_is_fenced_to_one_execution_claim(): void
    {
        $event = $this->event();
        $dispatcher = new class extends ListingLaunchDispatcherService {
            protected function publish(ListingLaunchEvent $event): void
            {
                parent::publish($event);
                throw new RuntimeException('Publisher timed out after queue acceptance.');
            }
        };

        $dispatcher->dispatchDue();
        $oldJob = Queue::pushed(ExecuteListingLaunchEvent::class)->first();
        app(ListingLaunchDispatcherService::class)->dispatchDue();
        $jobs = Queue::pushed(ExecuteListingLaunchEvent::class)->values();

        $this->assertCount(2, $jobs);
        $oldJob->handle(app(ListingLifecycleService::class));
        $jobs->last()->handle(app(ListingLifecycleService::class));

        $this->assertSame(1, $event->fresh()->attempt_count);
        $this->assertSame('BLOCKED', $event->fresh()->operation_status);
    }

    public function test_stale_execution_is_failed_closed_instead_of_redispatched(): void
    {
        $event = $this->event([
            'operation_status' => 'EXECUTING',
            'claim_token' => Str::random(40),
            'lease_expires_at' => now()->subSecond(),
        ]);

        app(ListingLaunchDispatcherService::class)->dispatchDue();

        Queue::assertNothingPushed();
        $this->assertSame('PARTIALLY_FAILED', $event->fresh()->operation_status);
        $this->assertSame('EXECUTION_LEASE_EXPIRED', $event->fresh()->failure_code);
    }

    public function test_cancelled_operation_ignores_an_already_dispatched_job(): void
    {
        $event = $this->event();
        app(ListingLaunchDispatcherService::class)->dispatchDue();
        $job = Queue::pushed(ExecuteListingLaunchEvent::class)->first();

        app(ListingLifecycleService::class)->cancelLaunchEvent($this->admin(), $event->fresh(), 'Security hold.');
        $job->handle(app(ListingLifecycleService::class));

        $this->assertSame('CANCELLED', $event->fresh()->operation_status);
        $this->assertSame('CANCELLED', $event->fresh()->status);
        $this->assertSame('SCHEDULED', $event->application->fresh()->integration_status);
    }

    public function test_scheduled_cancellation_prevents_dispatch(): void
    {
        $event = $this->event();
        app(ListingLifecycleService::class)->cancelLaunchEvent($this->admin(), $event, 'Pre-launch hold.');

        app(ListingLaunchDispatcherService::class)->dispatchDue();

        Queue::assertNothingPushed();
        $this->assertSame('CANCELLED', $event->fresh()->operation_status);
    }

    public function test_execution_claim_is_the_too_late_to_cancel_boundary(): void
    {
        $event = $this->event([
            'operation_status' => 'EXECUTING',
            'claim_token' => Str::random(40),
            'lease_expires_at' => now()->addMinute(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('EXECUTION_IN_PROGRESS');

        app(ListingLifecycleService::class)->cancelLaunchEvent($this->admin(), $event, 'Too late.');
    }

    public function test_old_failed_job_cannot_poison_a_redispatched_claim(): void
    {
        $event = $this->event([
            'operation_status' => 'DISPATCHED',
            'claim_token' => 'replacement-token',
            'lease_expires_at' => now()->addMinute(),
        ]);

        (new ExecuteListingLaunchEvent($event->id, 'obsolete-token'))->failed(new RuntimeException('Old job failed.'));

        $this->assertSame('DISPATCHED', $event->fresh()->operation_status);
        $this->assertNull($event->fresh()->failure_code);
    }

    public function test_changed_configuration_blocks_execution_before_side_effects(): void
    {
        $event = $this->event(['configuration_fingerprint' => str_repeat('0', 64)]);
        app(ListingLaunchDispatcherService::class)->dispatchDue();
        $job = Queue::pushed(ExecuteListingLaunchEvent::class)->first();

        $job->handle(app(ListingLifecycleService::class));

        $this->assertSame('BLOCKED', $event->fresh()->operation_status);
        $this->assertSame('CONFIGURATION_FINGERPRINT_MISMATCH', $event->fresh()->failure_code);
        $this->assertSame('SCHEDULED', ListingApplication::query()->findOrFail($event->application_id)->integration_status);
    }

    public function test_failed_job_records_terminal_retry_exhaustion(): void
    {
        $event = $this->event(['operation_status' => 'EXECUTING', 'claim_token' => 'active-token']);
        (new ExecuteListingLaunchEvent($event->id, (string) $event->claim_token))->failed(new RuntimeException('Injected worker failure.'));

        $this->assertSame('FAILED', $event->fresh()->operation_status);
        $this->assertSame('RETRY_EXHAUSTED', $event->fresh()->failure_code);
        $this->assertNotNull($event->fresh()->completed_at);
    }

    private function event(array $overrides = []): ListingLaunchEvent
    {
        $user = User::factory()->create();
        $organization = ListingOrganization::query()->create([
            'organization_uuid' => (string) Str::uuid(), 'owner_user_id' => $user->id,
            'legal_name' => 'Durable Launch Ltd', 'project_name' => 'Durable',
            'jurisdiction' => 'NG', 'status' => 'ACTIVE',
        ]);
        $application = ListingApplication::query()->create([
            'application_uuid' => (string) Str::uuid(), 'reference' => 'LIST-DURABLE-'.Str::random(8),
            'organization_id' => $organization->id, 'submitted_by' => $user->id,
            'application_type' => 'TOKEN', 'application_status' => 'APPROVED', 'integration_status' => 'SCHEDULED',
        ]);

        return ListingLaunchEvent::query()->create(array_merge([
            'event_uuid' => (string) Str::uuid(), 'application_id' => $application->id,
            'event_type' => 'TRADING_OPEN', 'status' => 'PENDING', 'operation_status' => 'SCHEDULED',
            'due_at' => now()->subMinute(), 'idempotency_key' => $application->reference.':TRADING_OPEN',
            'configuration_fingerprint' => str_repeat('a', 64), 'launch_scope' => ['TRADING_OPEN'],
            'correlation_id' => (string) Str::uuid(), 'claim_token' => null, 'result' => [],
        ], $overrides));
    }

    private function admin(): Admin
    {
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);
        return Admin::query()->create([
            'name' => 'Launch Operator', 'email' => 'launch-'.Str::random(8).'@example.com',
            'password' => Hash::make('password'), 'role_id' => $role->id, 'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }
}
