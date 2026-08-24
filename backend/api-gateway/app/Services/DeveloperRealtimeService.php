<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeveloperProject;
use App\Models\DeveloperRealtimeEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeveloperRealtimeService
{
    public function createSession(DeveloperProject $project, array $topics): array
    {
        $normalized = $this->validateTopics($topics);

        return [
            'session_id' => 'devws_' . Str::lower(Str::random(24)),
            'environment' => $project->environment,
            'heartbeat_seconds' => (int) config('developer_api.websocket.heartbeat_seconds', 30),
            'max_subscriptions' => (int) config('developer_api.websocket.max_subscriptions', 100),
            'queue_limit' => (int) config('developer_api.websocket.queue_limit', 1000),
            'topics' => $normalized,
            'replay_url' => '/api/developer/v1/realtime/replay',
            'expires_at' => now()->addMinutes(10)->toISOString(),
        ];
    }

    public function publish(DeveloperProject $project, string $stream, string $eventType, array $payload): DeveloperRealtimeEvent
    {
        return DB::transaction(function () use ($eventType, $payload, $project, $stream): DeveloperRealtimeEvent {
            $last = DeveloperRealtimeEvent::query()
                ->where('project_id', $project->id)
                ->where('stream', $stream)
                ->lockForUpdate()
                ->max('sequence');

            return DeveloperRealtimeEvent::query()->create([
                'event_id' => (string) Str::uuid(),
                'user_id' => $project->user_id,
                'project_id' => $project->id,
                'environment' => $project->environment,
                'stream' => $stream,
                'sequence' => ((int) $last) + 1,
                'event_type' => $eventType,
                'payload' => $payload,
                'created_at' => now(),
            ]);
        });
    }

    public function replay(DeveloperProject $project, string $stream, int $afterSequence, int $limit = 500): array
    {
        $limit = min(max($limit, 1), (int) config('developer_api.websocket.replay_limit', 500));

        return DeveloperRealtimeEvent::query()
            ->where('project_id', $project->id)
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

    public function validateTopics(array $topics): array
    {
        $max = (int) config('developer_api.websocket.max_subscriptions', 100);
        if (count($topics) > $max) {
            throw new \RuntimeException('Too many websocket subscriptions requested.');
        }

        $allowed = (array) config('developer_api.websocket.allowed_topics', []);
        $normalized = [];
        foreach ($topics as $topic) {
            $topic = trim((string) $topic);
            if ($topic === '' || ! preg_match('/^[a-z0-9_.:-]+$/i', $topic)) {
                throw new \RuntimeException('Invalid websocket topic.');
            }
            $matched = false;
            foreach ($allowed as $pattern) {
                if (fnmatch((string) $pattern, $topic)) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                throw new \RuntimeException("Unsupported websocket topic: {$topic}");
            }
            $normalized[] = $topic;
        }

        return array_values(array_unique($normalized));
    }
}
