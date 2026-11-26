<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ExecuteListingLaunchEvent;
use App\Models\ListingLaunchEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ListingLaunchDispatcherService
{
    public function dispatchDue(int $limit = 100): array
    {
        $events = DB::transaction(function () use ($limit) {
            ListingLaunchEvent::query()
                ->where('operation_status', ListingLaunchEvent::EXECUTING)
                ->whereNotNull('lease_expires_at')
                ->where('lease_expires_at', '<=', now())
                ->update([
                    'operation_status' => ListingLaunchEvent::PARTIALLY_FAILED,
                    'failure_code' => 'EXECUTION_LEASE_EXPIRED',
                    'failure_reason' => 'Execution ownership expired; manual reconciliation is required before any retry.',
                    'completed_at' => now(),
                    'lease_expires_at' => null,
                ]);

            $lateCutoff = now()->subMinutes(max(1, (int) config('listing.launch.maximum_lateness_minutes', 60)));
            ListingLaunchEvent::query()
                ->whereIn('operation_status', ListingLaunchEvent::DISPATCHABLE_STATES)
                ->whereNotNull('due_at')->where('due_at', '<', $lateCutoff)
                ->where(fn ($query) => $query->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<=', now()))
                ->update([
                    'operation_status' => ListingLaunchEvent::BLOCKED, 'status' => 'BLOCKED',
                    'failure_code' => 'LAUNCH_WINDOW_EXPIRED',
                    'failure_reason' => 'The configured launch execution window expired.',
                    'completed_at' => now(),
                ]);

            return ListingLaunchEvent::query()
                ->whereIn('operation_status', ListingLaunchEvent::DISPATCHABLE_STATES)
                ->whereNotNull('due_at')->where('due_at', '<=', now())
                ->where(fn ($query) => $query->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<=', now()))
                ->orderBy('due_at')->limit(max(1, min($limit, 500)))
                ->lockForUpdate()->get()->map(function (ListingLaunchEvent $event): ListingLaunchEvent {
                    $event->forceFill([
                        'operation_status' => ListingLaunchEvent::DISPATCH_PENDING,
                        'claim_token' => Str::random(40),
                        'claimed_at' => now(),
                        'lease_expires_at' => now()->addSeconds(max(30, (int) config('listing.launch.dispatch_lease_seconds', 120))),
                        'correlation_id' => $event->correlation_id ?: (string) Str::uuid(),
                        'dispatch_attempt_count' => ((int) $event->dispatch_attempt_count) + 1,
                    ])->save();
                    return $event;
                })->all();
        });

        $result = [];
        foreach ($events as $event) {
            try {
                $this->publish($event);
                ListingLaunchEvent::query()->whereKey($event->id)
                    ->where('claim_token', $event->claim_token)
                    ->where('operation_status', ListingLaunchEvent::DISPATCH_PENDING)
                    ->update(['operation_status' => ListingLaunchEvent::DISPATCHED, 'dispatched_at' => now()]);
                $result[] = ['event_id' => $event->event_uuid, 'status' => 'DISPATCHED'];
            } catch (Throwable $exception) {
                ListingLaunchEvent::query()->whereKey($event->id)
                    ->where('claim_token', $event->claim_token)
                    ->where('operation_status', ListingLaunchEvent::DISPATCH_PENDING)
                    ->update([
                        'operation_status' => ListingLaunchEvent::RETRY_PENDING,
                        'failure_code' => 'QUEUE_DISPATCH_FAILED',
                        'failure_reason' => mb_substr($exception->getMessage(), 0, 2000),
                        'lease_expires_at' => null,
                    ]);
                $result[] = ['event_id' => $event->event_uuid, 'status' => 'RETRY_PENDING'];
            }
        }

        return $result;
    }

    protected function publish(ListingLaunchEvent $event): void
    {
        ExecuteListingLaunchEvent::dispatch((int) $event->id, (string) $event->claim_token)->onQueue('listing-launch');
    }
}
