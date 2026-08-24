<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CopyComplaint;
use App\Models\CopyPublicActivationRequest;
use App\Models\CopyTerm;
use App\Models\CopyTermAcceptance;
use App\Models\CopyRelationship;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PublicCopyTradingService
{
    public function __construct(
        private readonly CopyPublicModeService $mode,
        private readonly PublicCopyTradingReadinessService $readiness,
        private readonly CopyRealtimeService $realtime,
    ) {
    }

    public function acceptTerms(int $userId, array $types, ?string $ip = null, ?string $userAgent = null): array
    {
        $accepted = [];
        foreach ($types as $type) {
            $term = CopyTerm::query()->where('type', $type)->where('status', 'ACTIVE')->latest('id')->firstOrFail();
            $accepted[] = CopyTermAcceptance::query()->updateOrCreate(
                ['user_id' => $userId, 'type' => $type, 'version' => $term->version],
                [
                    'accepted_at' => now(),
                    'ip_hash' => $ip ? hash('sha256', $ip) : null,
                    'user_agent_hash' => $userAgent ? hash('sha256', $userAgent) : null,
                    'metadata' => ['source' => 'copy_public_api'],
                ],
            );
        }

        return $accepted;
    }

    public function stopRelationship(int $userId, int $relationshipId, string $action, string $reason): CopyRelationship
    {
        return DB::transaction(function () use ($userId, $relationshipId, $action, $reason): CopyRelationship {
            $relationship = CopyRelationship::query()
                ->where('follower_id', $userId)
                ->lockForUpdate()
                ->findOrFail($relationshipId);

            $relationship->status = match ($action) {
                'STOP_NEW_TRADES' => 'paused',
                'STOP_AND_CLOSE_COPIED_POSITIONS', 'DETACH_POSITION' => 'stopped',
                default => throw new RuntimeException('Unsupported stop action.'),
            };
            $relationship->metadata = array_merge($relationship->metadata ?? [], [
                'public_stop' => ['action' => $action, 'reason' => $reason, 'at' => now()->toISOString()],
            ]);
            $relationship->save();

            $this->realtime->record($userId, 'copy.relationship', [
                'relationship_id' => $relationship->id,
                'status' => $relationship->status,
                'action' => $action,
            ]);

            return $relationship;
        });
    }

    public function complaint(int $userId, array $payload): CopyComplaint
    {
        $complaint = CopyComplaint::query()->create([
            'complaint_id' => (string) Str::uuid(),
            'follower_user_id' => $userId,
            'lead_trader_id' => $payload['lead_trader_id'] ?? null,
            'copy_relationship_id' => $payload['copy_relationship_id'] ?? null,
            'copy_order_id' => $payload['copy_order_id'] ?? null,
            'lead_trade_event_id' => $payload['lead_trade_event_id'] ?? null,
            'category' => $payload['category'],
            'message' => $payload['message'],
            'evidence' => $payload['evidence'] ?? null,
        ]);

        $this->realtime->record($userId, 'copy.alert', [
            'type' => 'complaint_submitted',
            'complaint_id' => $complaint->complaint_id,
        ]);

        return $complaint;
    }

    public function requestEnable(int $adminId, string $mode, string $reason): CopyPublicActivationRequest
    {
        $readiness = $this->readiness->check();

        return CopyPublicActivationRequest::query()->create([
            'request_id' => (string) Str::uuid(),
            'requested_mode' => strtoupper($mode),
            'requested_by' => $adminId,
            'reason' => $reason,
            'readiness_snapshot' => $readiness,
        ]);
    }

    public function approveEnable(int $adminId, int $requestId, string $reason): CopyPublicActivationRequest
    {
        $request = CopyPublicActivationRequest::query()->where('status', 'REQUESTED')->findOrFail($requestId);
        $this->mode->set('COPY_TRADING_MODE', $request->requested_mode, $adminId, ['reason' => $reason, 'activation_request_id' => $request->id]);
        $request->forceFill(['status' => 'APPROVED', 'approved_by' => $adminId, 'approved_at' => now()])->save();

        return $request;
    }

    public function pause(int $adminId, string $reason, string $state = 'COPY_PAUSED'): void
    {
        $this->mode->set('COPY_TRADING_EMERGENCY_STATE', $state, $adminId, ['reason' => $reason]);
    }

    public function resume(int $adminId, string $reason): void
    {
        $this->mode->set('COPY_TRADING_EMERGENCY_STATE', 'NORMAL', $adminId, ['reason' => $reason]);
    }
}
