<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Models\DeveloperRealtimeEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class CardRealtimeService
{
    public const STREAM = 'exacard.private';
    public const ADMIN_STREAM = 'exacard.operations';

    public function publishUser(int $userId, string $eventType, array $payload, ?string $entityId = null): DeveloperRealtimeEvent
    {
        return $this->record($userId, self::STREAM, $eventType, $payload, $entityId);
    }

    public function publishOperations(string $eventType, array $payload, ?string $entityId = null): DeveloperRealtimeEvent
    {
        return $this->record(null, self::ADMIN_STREAM, $eventType, $payload, $entityId);
    }

    public function replay(int $userId, int $afterSequence = 0, int $limit = 200): array
    {
        $limit = min(max($limit, 1), 500);

        return DeveloperRealtimeEvent::query()
            ->whereNull('project_id')
            ->where('user_id', $userId)
            ->where('stream', self::STREAM)
            ->where('sequence', '>', $afterSequence)
            ->orderBy('sequence')
            ->limit($limit)
            ->get()
            ->map(fn (DeveloperRealtimeEvent $event): array => $this->present($event))
            ->all();
    }

    public function latestSequence(int $userId): int
    {
        return (int) DeveloperRealtimeEvent::query()
            ->whereNull('project_id')
            ->where('user_id', $userId)
            ->where('stream', self::STREAM)
            ->max('sequence');
    }

    public function hasGap(array $events, int $afterSequence): bool
    {
        $expected = $afterSequence + 1;
        foreach ($events as $event) {
            if ((int) $event['sequence'] !== $expected) {
                return true;
            }
            $expected++;
        }

        return false;
    }

    private function record(?int $userId, string $stream, string $eventType, array $payload, ?string $entityId): DeveloperRealtimeEvent
    {
        $event = DB::transaction(function () use ($entityId, $eventType, $payload, $stream, $userId): DeveloperRealtimeEvent {
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
                'payload' => [
                    'version' => 1,
                    'entity_id' => $entityId,
                    ...$payload,
                ],
                'created_at' => now(),
            ]);
        });

        DB::afterCommit(function () use ($event, $stream, $userId): void {
            try {
                Redis::publish('private.'.$stream.'.'.($userId ?: 'operations'), json_encode($this->present($event), JSON_THROW_ON_ERROR));
            } catch (\Throwable) {
                // Durable replay is the source for recovery; Redis fanout is best-effort.
            }
        });

        return $event;
    }

    private function present(DeveloperRealtimeEvent $event): array
    {
        return [
            'event_id' => $event->event_id,
            'stream' => $event->stream,
            'sequence' => (int) $event->sequence,
            'event_type' => $event->event_type,
            'payload' => $event->payload,
            'timestamp' => $event->created_at?->toISOString(),
        ];
    }
}
