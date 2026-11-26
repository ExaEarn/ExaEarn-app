<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Serializes durable realtime sequence allocation using a dedicated row lock.
 * Aggregate queries are intentionally never combined with FOR UPDATE (invalid on PostgreSQL).
 */
final class RealtimeSequenceService
{
    public function next(string $streamKey, Closure $observedMaximum): int
    {
        $digest = hash('sha256', $streamKey);

        return DB::transaction(function () use ($digest, $observedMaximum): int {
            DB::table('realtime_sequence_counters')->insertOrIgnore([
                'stream_digest' => $digest, 'last_sequence' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $counter = DB::table('realtime_sequence_counters')->where('stream_digest', $digest)->lockForUpdate()->first();
            if (!$counter) throw new \RuntimeException('Realtime sequence counter could not be locked.');

            $next = max((int) $counter->last_sequence, (int) $observedMaximum()) + 1;
            DB::table('realtime_sequence_counters')->where('id', $counter->id)->update([
                'last_sequence' => $next, 'updated_at' => now(),
            ]);

            return $next;
        }, 3);
    }
}
