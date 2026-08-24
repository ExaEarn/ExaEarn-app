<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeveloperRealtimeEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstitutionalRealtimeService
{
    public function publish(int $userId, string $stream, string $eventType, array $payload): DeveloperRealtimeEvent
    {
        return DB::transaction(function () use ($eventType, $payload, $stream, $userId): DeveloperRealtimeEvent {
            $last = DeveloperRealtimeEvent::query()
                ->whereNull('project_id')
                ->where('user_id', $userId)
                ->where('stream', $stream)
                ->lockForUpdate()
                ->max('sequence');

            return DeveloperRealtimeEvent::query()->create([
                'event_id' => (string) Str::uuid(),
                'user_id' => $userId,
                'project_id' => null,
                'environment' => app()->environment('production') ? 'production' : 'sandbox',
                'stream' => $stream,
                'sequence' => ((int) $last) + 1,
                'event_type' => $eventType,
                'payload' => $payload,
                'created_at' => now(),
            ]);
        });
    }

    public function replay(int $userId, string $stream, int $afterSequence, int $limit = 200): array
    {
        $limit = min(max($limit, 1), 500);

        return DeveloperRealtimeEvent::query()
            ->whereNull('project_id')
            ->where('user_id', $userId)
            ->where('stream', $stream)
            ->where('sequence', '>', $afterSequence)
            ->orderBy('sequence')
            ->limit($limit)
            ->get()
            ->map(fn (DeveloperRealtimeEvent $event): array => [
                'event_id' => $event->event_id,
                'stream' => $event->stream,
                'sequence' => $event->sequence,
                'event_type' => $event->event_type,
                'payload' => $event->payload,
                'timestamp' => $event->created_at?->toISOString(),
            ])
            ->all();
    }
}
