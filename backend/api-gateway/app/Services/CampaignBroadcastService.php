<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CampaignBroadcastService
{
    public function publish(array $campaign, array $source): void
    {
        $channel = (string) config('campaign.stream.channel', 'campaign_updates');

        $payload = [
            'type' => 'campaign.generated',
            'timestamp' => now()->toISOString(),
            'source' => $source,
            'campaign' => $campaign,
        ];

        try {
            Redis::publish($channel, json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (\Throwable $exception) {
            Log::warning('Failed to publish campaign payload.', [
                'channel' => $channel,
                'error' => $exception->getMessage(),
                'source_type' => $source['type'] ?? null,
            ]);
        }
    }
}
