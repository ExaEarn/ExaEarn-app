<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiRealtimeEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExaAiRealtimeService
{
    public function publish(?int $userId, string $stream, string $eventType, array $payload): ExaAiRealtimeEvent
    {
        return DB::transaction(function () use ($eventType, $payload, $stream, $userId): ExaAiRealtimeEvent {
            if ($userId !== null) {
                User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            } elseif (DB::connection()->getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtext(?), hashtext(?))', ['exaai-realtime', $stream]);
            }

            $last = ExaAiRealtimeEvent::query()
                ->where('user_id', $userId)
                ->where('stream', $stream)
                ->max('sequence');

            return ExaAiRealtimeEvent::query()->create([
                'user_id' => $userId,
                'stream' => $stream,
                'sequence' => ((int) $last) + 1,
                'event_type' => $eventType,
                'payload' => $payload,
                'published_at' => now(),
            ]);
        });
    }

    public function replay(int $userId, string $stream, int $afterSequence = 0, int $limit = 100): Collection
    {
        return ExaAiRealtimeEvent::query()
            ->where('user_id', $userId)
            ->where('stream', $stream)
            ->where('sequence', '>', $afterSequence)
            ->orderBy('sequence')
            ->limit(min(max($limit, 1), 500))
            ->get();
    }
}
