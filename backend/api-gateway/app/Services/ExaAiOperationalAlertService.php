<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiOperationalAlert;
use Illuminate\Support\Str;

class ExaAiOperationalAlertService
{
    public function trigger(string $severity, string $component, string $condition, string $message, array $context = []): ExaAiOperationalAlert
    {
        $dedupeKey = strtoupper($component . ':' . $condition);

        return ExaAiOperationalAlert::query()->updateOrCreate([
            'dedupe_key' => $dedupeKey,
            'status' => 'OPEN',
        ], [
            'alert_uuid' => (string) (ExaAiOperationalAlert::query()->where('dedupe_key', $dedupeKey)->where('status', 'OPEN')->value('alert_uuid') ?: Str::uuid()),
            'severity' => strtoupper($severity),
            'component' => $component,
            'condition' => $condition,
            'message' => $message,
            'context' => $context,
            'last_triggered_at' => now(),
        ]);
    }

    public function resolve(string $component, string $condition): void
    {
        ExaAiOperationalAlert::query()
            ->where('dedupe_key', strtoupper($component . ':' . $condition))
            ->where('status', 'OPEN')
            ->update(['status' => 'RESOLVED', 'resolved_at' => now()]);
    }
}
