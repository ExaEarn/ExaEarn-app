<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventStreamController extends Controller
{
    public function subscribe(Request $request): StreamedResponse
    {
        $channelMap = [
            'users' => 'user.created',
            (string) config('streaming.price_channel', 'price_updates') => 'price:update',
            (string) config('streaming.portfolio_channel', 'portfolio_updates') => 'portfolio:update',
            (string) config('trading.stream.channel', 'exaearn.market.stream') => 'market:stream',
            (string) config('games.flight.stream_channel', 'exaearn.game.flight') => 'game.flight',
        ];

        return $this->streamChannels($channelMap);
    }

    public function subscribeCampaigns(Request $request): StreamedResponse
    {
        $channel = (string) config('campaign.stream.channel', 'campaign_updates');

        return $this->streamChannels([$channel => 'campaign.generated']);
    }

    private function streamChannels(array $channelMap): StreamedResponse
    {
        return response()->stream(function () use ($channelMap) {
            try {
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
                    @ob_flush();
                    flush();
                });
            } catch (\Throwable $exception) {
                Log::warning('SSE realtime stream degraded', [
                    'error' => $exception->getMessage(),
                    'channels' => array_keys($channelMap),
                ]);

                if (!headers_sent()) {
                    header('Content-Type: text/event-stream');
                    header('Cache-Control: no-cache');
                    header('Connection: keep-alive');
                }

                echo "event: stream.status\n";
                echo 'data: ' . json_encode([
                    'status' => 'degraded',
                    'message' => 'Realtime stream fallback active.',
                ], JSON_THROW_ON_ERROR) . "\n\n";
                @ob_flush();
                flush();

                while (!connection_aborted()) {
                    echo ": keepalive\n\n";
                    @ob_flush();
                    flush();
                    sleep(15);
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }
}
