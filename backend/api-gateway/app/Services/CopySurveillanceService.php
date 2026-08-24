<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CopyRelationship;
use App\Models\CopySurveillanceCase;
use App\Models\CopySurveillanceEvent;
use Illuminate\Support\Str;

class CopySurveillanceService
{
    public function evaluateRelationship(CopyRelationship $relationship): array
    {
        $cases = [];

        if ((int) $relationship->follower_id === (int) $relationship->trader?->user_id) {
            $cases[] = $this->openCase($relationship, 'SELF_COPY', 'critical', [
                'reason' => 'Follower user is the lead trader user.',
            ]);
        }

        $leadEmail = strtolower((string) $relationship->trader?->user?->email);
        $followerEmail = strtolower((string) $relationship->follower?->email);
        $leadDomain = str_contains($leadEmail, '@') ? explode('@', $leadEmail, 2)[1] : '';
        $followerDomain = str_contains($followerEmail, '@') ? explode('@', $followerEmail, 2)[1] : '';
        if ($leadDomain !== '' && $leadDomain === $followerDomain && $leadEmail !== $followerEmail) {
            $cases[] = $this->openCase($relationship, 'RELATED_ACCOUNT_SIGNAL', 'medium', [
                'signal' => 'shared_email_domain',
                'domain_hash' => hash('sha256', $leadDomain),
            ]);
        }

        return $cases;
    }

    public function openCase(CopyRelationship $relationship, string $signalType, string $severity, array $evidence): CopySurveillanceCase
    {
        CopySurveillanceEvent::query()->create([
            'surveillance_event_id' => (string) Str::uuid(),
            'lead_trader_id' => $relationship->trader_id,
            'copy_relationship_id' => $relationship->id,
            'event_type' => $signalType,
            'severity' => $severity,
            'signals' => $evidence,
            'metadata' => ['source' => 'copy_surveillance_service'],
        ]);

        return CopySurveillanceCase::query()->firstOrCreate([
            'lead_trader_id' => $relationship->trader_id,
            'copy_relationship_id' => $relationship->id,
            'signal_type' => $signalType,
            'status' => 'OPEN',
        ], [
            'case_id' => (string) Str::uuid(),
            'severity' => $severity,
            'evidence' => $evidence,
            'related_accounts' => [$relationship->follower_id, $relationship->trader?->user_id],
            'markets' => [],
            'orders' => [],
            'trades' => [],
            'copy_orders' => [],
        ]);
    }
}
