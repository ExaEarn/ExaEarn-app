<?php

declare(strict_types=1);

namespace App\Domain\P2P\Services;

use App\Models\P2PTrade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class P2POrderEventService
{
    public function record(P2PTrade $trade, string $eventType, ?int $actorUserId = null, array $payload = [], ?string $correlationId = null): void
    {
        DB::table('p2p_order_events')->insert([
            'trade_id' => $trade->id,
            'event_id' => (string) Str::uuid(),
            'event_type' => $eventType,
            'actor_user_id' => $actorUserId,
            'correlation_id' => $correlationId,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
