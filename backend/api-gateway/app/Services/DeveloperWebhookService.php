<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeveloperProject;
use App\Models\DeveloperWebhookDelivery;
use App\Models\DeveloperWebhookEndpoint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class DeveloperWebhookService
{
    public function register(DeveloperProject $project, array $payload): array
    {
        $url = (string) $payload['url'];
        if (app()->environment('production') && ! str_starts_with(strtolower($url), 'https://')) {
            throw new RuntimeException('Webhook endpoints must use HTTPS in production.');
        }

        $secret = 'whsec_' . Str::random(64);
        $endpoint = DeveloperWebhookEndpoint::query()->create([
            'endpoint_uuid' => (string) Str::uuid(),
            'user_id' => $project->user_id,
            'project_id' => $project->id,
            'url' => $url,
            'status' => 'active',
            'events' => array_values(array_unique((array) $payload['events'])),
            'encrypted_secret' => Crypt::encryptString($secret),
            'secret_rotated_at' => now(),
        ]);

        return ['endpoint' => $endpoint, 'signing_secret' => $secret];
    }

    public function rotateSecret(DeveloperWebhookEndpoint $endpoint): array
    {
        $secret = 'whsec_' . Str::random(64);
        $endpoint->update([
            'encrypted_secret' => Crypt::encryptString($secret),
            'secret_rotated_at' => now(),
        ]);

        return ['endpoint' => $endpoint->fresh(), 'signing_secret' => $secret];
    }

    public function enqueue(DeveloperProject $project, string $eventType, array $payload, ?string $eventId = null): int
    {
        $eventId ??= (string) Str::uuid();
        $endpoints = DeveloperWebhookEndpoint::query()
            ->where('project_id', $project->id)
            ->where('status', 'active')
            ->get()
            ->filter(fn (DeveloperWebhookEndpoint $endpoint): bool => in_array($eventType, (array) $endpoint->events, true));

        foreach ($endpoints as $endpoint) {
            DeveloperWebhookDelivery::query()->create([
                'delivery_uuid' => (string) Str::uuid(),
                'event_id' => $eventId,
                'endpoint_id' => $endpoint->id,
                'event_type' => $eventType,
                'payload' => $this->safePayload($eventId, $eventType, $payload),
                'attempts' => 0,
                'status' => 'PENDING',
                'next_attempt_at' => now(),
            ]);
        }

        return $endpoints->count();
    }

    public function deliverDue(int $limit = 50): array
    {
        $results = ['delivered' => 0, 'retrying' => 0, 'dead_lettered' => 0, 'failed' => 0];
        $deliveries = DeveloperWebhookDelivery::query()
            ->with('endpoint')
            ->whereIn('status', ['PENDING', 'RETRYING', 'FAILED'])
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        foreach ($deliveries as $delivery) {
            $this->deliver($delivery);
            $status = strtolower((string) $delivery->fresh()->status);
            if (isset($results[$status])) {
                $results[$status]++;
            }
        }

        return $results;
    }

    public function replay(DeveloperWebhookDelivery $delivery): DeveloperWebhookDelivery
    {
        return DB::transaction(function () use ($delivery): DeveloperWebhookDelivery {
            return DeveloperWebhookDelivery::query()->create([
                'delivery_uuid' => (string) Str::uuid(),
                'event_id' => $delivery->event_id,
                'endpoint_id' => $delivery->endpoint_id,
                'event_type' => $delivery->event_type,
                'payload' => $delivery->payload,
                'attempts' => 0,
                'status' => 'PENDING',
                'next_attempt_at' => now(),
            ]);
        });
    }

    public function signature(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    private function deliver(DeveloperWebhookDelivery $delivery): void
    {
        /** @var DeveloperWebhookEndpoint|null $endpoint */
        $endpoint = $delivery->endpoint;
        if (! $endpoint || $endpoint->status !== 'active') {
            $delivery->update(['status' => 'DEAD_LETTERED', 'dead_lettered_at' => now(), 'last_error' => 'Endpoint inactive.']);
            return;
        }

        $delivery->update(['status' => 'DELIVERING']);
        $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $secret = Crypt::decryptString((string) $endpoint->encrypted_secret);

        try {
            $response = Http::timeout((int) config('developer_api.webhooks.timeout_seconds', 5))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-ExaEarn-Event-Id' => (string) $delivery->event_id,
                    'X-ExaEarn-Timestamp' => (string) $timestamp,
                    'X-ExaEarn-Signature' => $this->signature($secret, $timestamp, $body),
                ])
                ->send('POST', $endpoint->url, ['body' => $body]);

            if ($response->successful()) {
                $delivery->update([
                    'status' => 'DELIVERED',
                    'attempts' => $delivery->attempts + 1,
                    'last_status_code' => $response->status(),
                    'delivered_at' => now(),
                    'last_error' => null,
                ]);
                $endpoint->update(['last_delivered_at' => now()]);
                return;
            }

            $this->markRetry($delivery, $response->status(), 'Webhook endpoint returned HTTP ' . $response->status());
        } catch (\Throwable $exception) {
            $this->markRetry($delivery, null, $exception->getMessage());
        }
    }

    private function markRetry(DeveloperWebhookDelivery $delivery, ?int $statusCode, string $error): void
    {
        $attempts = $delivery->attempts + 1;
        if ($attempts >= (int) config('developer_api.webhooks.max_attempts', 8)) {
            $delivery->update([
                'status' => 'DEAD_LETTERED',
                'attempts' => $attempts,
                'last_status_code' => $statusCode,
                'last_error' => $error,
                'dead_lettered_at' => now(),
            ]);
            return;
        }

        $delay = min(3600, 2 ** $attempts * 30);
        $delivery->update([
            'status' => 'RETRYING',
            'attempts' => $attempts,
            'last_status_code' => $statusCode,
            'last_error' => $error,
            'next_attempt_at' => now()->addSeconds($delay),
        ]);
    }

    private function safePayload(string $eventId, string $eventType, array $payload): array
    {
        return [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'created_at' => now()->toISOString(),
            'data' => $payload,
        ];
    }
}
