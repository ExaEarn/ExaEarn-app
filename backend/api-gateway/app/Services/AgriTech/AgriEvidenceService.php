<?php

declare(strict_types=1);

namespace App\Services\AgriTech;

use App\Models\Farmer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AgriEvidenceService
{
    public function submit(User $actor, int $projectId, array $payload): object
    {
        if (empty($payload['storage_reference']) && empty($payload['external_reference'])) {
            throw new RuntimeException('A private storage or verified external reference is required.');
        }

        $id = DB::table('agri_project_evidence')->insertGetId([
            'project_id' => $projectId,
            'farmer_id' => $payload['farmer_id'] ?? null,
            'evidence_type' => strtoupper((string) $payload['evidence_type']),
            'source_type' => strtoupper((string) $payload['source_type']),
            'status' => 'PENDING_REVIEW',
            'storage_reference' => $payload['storage_reference'] ?? null,
            'external_reference' => $payload['external_reference'] ?? null,
            'confidence' => null,
            'metadata' => json_encode($payload['metadata'] ?? [], JSON_THROW_ON_ERROR),
            'submitted_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('agri_project_evidence')->where('id', $id)->first();
    }

    public function review(User $actor, int $evidenceId, string $decision, ?string $reason = null): object
    {
        if ($actor->role !== 'admin') {
            throw new RuntimeException('Authorized reviewer access is required.');
        }
        $decision = strtoupper($decision);
        if (!in_array($decision, ['APPROVED', 'REJECTED', 'REQUEST_INFORMATION', 'FLAGGED'], true)) {
            throw new RuntimeException('Invalid evidence review decision.');
        }

        return DB::transaction(function () use ($actor, $decision, $evidenceId, $reason): object {
            $evidence = DB::table('agri_project_evidence')->where('id', $evidenceId)->lockForUpdate()->first();
            if (!$evidence) {
                throw new RuntimeException('Evidence was not found.');
            }
            DB::table('agri_project_evidence')->where('id', $evidenceId)->update([
                'status' => $decision,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_reason' => $reason,
                'updated_at' => now(),
            ]);
            if ($decision === 'APPROVED' && $evidence->farmer_id) {
                $field = match ($evidence->evidence_type) {
                    'IDENTITY' => 'identity_status',
                    'LAND_TITLE', 'LAND_LEASE', 'FARM_INSPECTION' => 'land_verification_status',
                    default => null,
                };
                if ($field) {
                    Farmer::query()->whereKey($evidence->farmer_id)->update([$field => 'VERIFIED']);
                }
            }

            return DB::table('agri_project_evidence')->where('id', $evidenceId)->first();
        });
    }
}
