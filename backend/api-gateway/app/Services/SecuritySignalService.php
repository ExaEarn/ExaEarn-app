<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SecurityEvent;
use App\Models\SecurityRiskSignal;
use Illuminate\Support\Str;

class SecuritySignalService
{
    public function record(
        string $signalType,
        string $source,
        string $subjectType,
        ?int $subjectId,
        string $severity,
        array $metadata = [],
        string $confidence = '1.0000',
        ?int $ttlSeconds = null
    ): SecurityRiskSignal {
        $signal = SecurityRiskSignal::query()->create([
            'signal_uuid' => (string) Str::uuid(),
            'signal_type' => strtoupper($signalType),
            'source' => strtoupper($source),
            'subject_type' => strtoupper($subjectType),
            'subject_id' => $subjectId,
            'severity' => strtoupper($severity),
            'confidence' => $confidence,
            'status' => 'ACTIVE',
            'metadata' => $metadata,
            'detected_at' => now(),
            'expires_at' => $ttlSeconds ? now()->addSeconds($ttlSeconds) : null,
        ]);

        SecurityEvent::query()->create([
            'event_type' => strtoupper($signalType),
            'severity' => strtolower($this->legacySeverity($severity)),
            'user_id' => $subjectType === 'USER' ? $subjectId : null,
            'ip_address' => $metadata['ip_address'] ?? null,
            'endpoint' => $metadata['endpoint'] ?? null,
            'metadata' => array_merge($metadata, ['signal_uuid' => $signal->signal_uuid]),
        ]);

        return $signal;
    }

    public function activeFor(string $subjectType, ?int $subjectId): array
    {
        return SecurityRiskSignal::query()
            ->where('subject_type', strtoupper($subjectType))
            ->where('subject_id', $subjectId)
            ->where('status', 'ACTIVE')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('detected_at')
            ->get()
            ->all();
    }

    private function legacySeverity(string $severity): string
    {
        return match (strtoupper($severity)) {
            'CRITICAL' => 'critical',
            'HIGH' => 'error',
            'MEDIUM' => 'warning',
            default => 'info',
        };
    }
}
