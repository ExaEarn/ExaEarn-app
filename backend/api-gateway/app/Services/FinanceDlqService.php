<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FinanceDeadLetterEvent;
use Illuminate\Support\Str;

class FinanceDlqService
{
    public function record(string $eventType, string $sourceService, ?string $sourceReference, string $error, array $payload = []): FinanceDeadLetterEvent
    {
        return FinanceDeadLetterEvent::query()->create([
            'dlq_uuid' => (string) Str::uuid(),
            'event_type' => $eventType,
            'source_service' => $sourceService,
            'source_reference' => $sourceReference,
            'status' => 'OPEN',
            'attempts' => 1,
            'error_message' => $error,
            'payload' => $payload,
            'next_retry_at' => now()->addMinutes(5),
        ]);
    }

    public function markRetried(FinanceDeadLetterEvent $event, bool $resolved, ?string $error = null): FinanceDeadLetterEvent
    {
        $event->forceFill([
            'attempts' => (int) $event->attempts + 1,
            'status' => $resolved ? 'RESOLVED' : 'OPEN',
            'error_message' => $error ?? $event->error_message,
            'next_retry_at' => $resolved ? null : now()->addMinutes(min(60, max(1, (int) $event->attempts) * 5)),
            'resolved_at' => $resolved ? now() : null,
        ])->save();

        return $event->fresh();
    }
}
