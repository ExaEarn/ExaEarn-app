<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;

class EventStreamController extends Controller
{
    public function subscribe(Request $request): Response
    {
        $channelMap = [
            'users' => 'user.created',
            (string) config('streaming.price_channel', 'price_updates') => 'price:update',
            (string) config('streaming.portfolio_channel', 'portfolio_updates') => 'portfolio:update',
        ];

        return $this->streamChannels($channelMap);
    }

    public function subscribeCampaigns(Request $request): Response
    {
        $channel = (string) config('campaign.stream.channel', 'campaign_updates');
        return $this->streamChannels([$channel => 'campaign.generated']);
    }

    private function streamChannels(array $channelMap): Response
    {
        return response()->stream(function () use ($channelMap) {
            $connection = Redis::connection();

            $connection->subscribe(array_keys($channelMap), function ($message, $channel) use ($channelMap) {
                if (!headers_sent()) {
                    header('Content-Type: text/event-stream');
                    header('Cache-Control: no-cache');
                    header('Connection: keep-alive');
                }

                $eventName = $channelMap[$channel] ?? 'message';

                echo "event: {$eventName}\n";
                echo 'data: ' . $message . "\n\n";
                ob_flush();
                flush();
            });
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }
}
