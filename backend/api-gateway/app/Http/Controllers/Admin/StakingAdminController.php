<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Staking\Services\StakingProviderRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StakingAdminController extends Controller
{
    public function __construct(private readonly StakingProviderRegistry $providers) {}

    public function assets(Request $request): JsonResponse
    {
        $query = DB::table('staking_assets')->orderBy('symbol');
        if ($request->query('include_excluded') !== '1') {
            $query->whereNotIn('symbol', config('staking.excluded_native_pos_assets', []));
        }

        return response()->json(['data' => $query->paginate((int) $request->query('per_page', 50))]);
    }

    public function updateAsset(Request $request, int $assetId): JsonResponse
    {
        $payload = $request->validate([
            'readiness_status' => ['nullable', 'string', 'in:development,testnet,integration_testing,internal_testing,limited_release,production,paused,deprecated'],
            'native_staking_enabled' => ['nullable', 'boolean'],
            'testnet_enabled' => ['nullable', 'boolean'],
            'new_positions_enabled' => ['nullable', 'boolean'],
            'unstaking_enabled' => ['nullable', 'boolean'],
            'minimum_stake' => ['nullable', 'numeric', 'gte:0'],
            'maximum_stake' => ['nullable', 'numeric', 'gte:0'],
            'delegation_minimum' => ['nullable', 'numeric', 'gte:0'],
            'platform_commission_rate' => ['nullable', 'numeric', 'gte:0'],
            'displayed_apy' => ['nullable', 'numeric', 'gte:0'],
            'primary_rpc_reference' => ['nullable', 'string', 'max:255'],
            'secondary_rpc_reference' => ['nullable', 'string', 'max:255'],
            'confirmation_requirement' => ['nullable', 'integer', 'min:1'],
            'metadata' => ['nullable', 'array'],
        ]);

        unset($payload['mainnet_enabled']);

        DB::table('staking_assets')->where('id', $assetId)->update(array_merge($payload, ['updated_at' => now()]));
        $asset = DB::table('staking_assets')->where('id', $assetId)->first();
        $this->audit('admin.staking.asset.update', $request, $assetId, $payload);

        return response()->json(['data' => $asset]);
    }

    public function emergencyPause(Request $request, int $assetId): JsonResponse
    {
        $payload = $request->validate([
            'paused' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        DB::table('staking_assets')->where('id', $assetId)->update([
            'emergency_paused' => (bool) $payload['paused'],
            'new_positions_enabled' => (bool) $payload['paused'] ? false : DB::raw('new_positions_enabled'),
            'updated_at' => now(),
        ]);

        $this->audit('admin.staking.asset.emergency_pause', $request, $assetId, $payload);

        return response()->json(['data' => DB::table('staking_assets')->where('id', $assetId)->first()]);
    }

    public function products(Request $request): JsonResponse
    {
        return response()->json([
            'data' => DB::table('staking_products')
                ->join('staking_assets', 'staking_assets.id', '=', 'staking_products.staking_asset_id')
                ->select('staking_products.*', 'staking_assets.symbol', 'staking_assets.network')
                ->orderByDesc('staking_products.created_at')
                ->paginate((int) $request->query('per_page', 50)),
        ]);
    }

    public function upsertProduct(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'id' => ['nullable', 'integer', 'exists:staking_products,id'],
            'staking_asset_id' => ['required', 'integer', 'exists:staking_assets,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'product_type' => ['required', 'string', 'max:32'],
            'status' => ['required', 'string', 'in:draft,disabled,active,paused,closed'],
            'network_environment' => ['required', 'string', 'in:testnet,mainnet,devnet'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'minimum_amount' => ['required', 'numeric', 'gte:0'],
            'maximum_amount' => ['nullable', 'numeric', 'gte:0'],
            'displayed_apy' => ['nullable', 'numeric', 'gte:0'],
            'platform_commission_rate' => ['required', 'numeric', 'gte:0'],
            'reward_schedule' => ['required', 'string', 'max:64'],
            'redemption_type' => ['required', 'string', 'max:32'],
            'unbonding_period_seconds' => ['nullable', 'integer', 'min:0'],
            'early_redemption_allowed' => ['nullable', 'boolean'],
            'early_redemption_penalty_rate' => ['nullable', 'numeric', 'gte:0'],
            'auto_compound_supported' => ['nullable', 'boolean'],
            'capacity' => ['nullable', 'numeric', 'gte:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'terms_version' => ['required', 'string', 'max:32'],
            'metadata' => ['nullable', 'array'],
        ]);

        $asset = DB::table('staking_assets')->where('id', $payload['staking_asset_id'])->first();
        if (in_array(strtoupper((string) $asset->symbol), config('staking.excluded_native_pos_assets', []), true)) {
            return response()->json(['message' => "{$asset->symbol} cannot be used for Native PoS Staking."], 422);
        }

        $data = array_merge($payload, ['updated_at' => now()]);
        unset($data['id']);

        if (isset($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }

        $id = $payload['id'] ?? null;
        if ($id) {
            DB::table('staking_products')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = now();
            $id = DB::table('staking_products')->insertGetId($data);
        }

        $this->audit('admin.staking.product.upsert', $request, (int) $id, $payload);

        return response()->json(['data' => DB::table('staking_products')->where('id', $id)->first()], $payload['id'] ?? false ? 200 : 201);
    }

    public function validators(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('staking_validators')->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function upsertValidator(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'id' => ['nullable', 'integer', 'exists:staking_validators,id'],
            'staking_asset_id' => ['required', 'integer', 'exists:staking_assets,id'],
            'provider_identifier' => ['required', 'string', 'max:255'],
            'validator_name' => ['required', 'string', 'max:255'],
            'validator_address' => ['required', 'string', 'max:255'],
            'secondary_identifier' => ['nullable', 'string', 'max:255'],
            'commission_rate' => ['required', 'numeric', 'gte:0'],
            'status' => ['required', 'string', 'max:32'],
            'preferred' => ['nullable', 'boolean'],
            'allowlisted' => ['nullable', 'boolean'],
            'delegation_capacity' => ['nullable', 'numeric', 'gte:0'],
            'minimum_delegation' => ['nullable', 'numeric', 'gte:0'],
            'metadata' => ['nullable', 'array'],
        ]);

        return $this->dualApprovalRequired($request, 'validator_change', 'staking_validators', (int) ($payload['id'] ?? 0), $payload);
    }

    public function providerHealth(string $symbol): JsonResponse
    {
        try {
            return response()->json(['data' => $this->providers->forSymbol($symbol)->healthCheck()]);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function requestMainnetActivation(Request $request, int $assetId): JsonResponse
    {
        $payload = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'evidence' => ['required', 'array'],
        ]);

        return $this->dualApprovalRequired($request, 'mainnet_activation', 'staking_assets', $assetId, [
            'mainnet_enabled' => true,
            'readiness_status' => 'production',
            'evidence' => $payload['evidence'],
            'reason' => $payload['reason'],
        ]);
    }

    public function approvals(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('staking_admin_approvals')->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function approve(Request $request, string $publicId): JsonResponse
    {
        $payload = $request->validate(['decision' => ['required', 'string', 'in:approve,reject'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $adminId = (int) $request->user()?->id;

        return DB::transaction(function () use ($publicId, $payload, $adminId): JsonResponse {
            $approval = DB::table('staking_admin_approvals')->where('public_id', $publicId)->lockForUpdate()->first();
            if (! $approval || $approval->status !== 'pending') {
                return response()->json(['message' => 'Approval request is not pending.'], 422);
            }
            if ((int) $approval->requested_by_admin_id === $adminId) {
                return response()->json(['message' => 'Dual approval requires a different administrator.'], 422);
            }

            if ($payload['decision'] === 'reject') {
                DB::table('staking_admin_approvals')->where('id', $approval->id)->update([
                    'status' => 'rejected',
                    'approved_by_admin_id' => $adminId,
                    'rejected_at' => now(),
                    'metadata' => json_encode(['rejection_reason' => $payload['reason'] ?? null]),
                    'updated_at' => now(),
                ]);

                return response()->json(['data' => DB::table('staking_admin_approvals')->where('id', $approval->id)->first()]);
            }

            $changes = json_decode((string) $approval->proposed_changes, true, flags: JSON_THROW_ON_ERROR);
            if ($approval->approval_type === 'mainnet_activation') {
                unset($changes['evidence'], $changes['reason']);
                DB::table('staking_assets')->where('id', $approval->subject_id)->update(array_merge($changes, ['updated_at' => now()]));
            }

            if ($approval->approval_type === 'validator_change') {
                $validatorId = (int) ($changes['id'] ?? 0);
                unset($changes['id'], $changes['reason']);
                if (isset($changes['metadata'])) {
                    $changes['metadata'] = json_encode($changes['metadata']);
                }

                if ($validatorId > 0) {
                    DB::table('staking_validators')->where('id', $validatorId)->update(array_merge($changes, ['updated_at' => now()]));
                } else {
                    DB::table('staking_validators')->insert(array_merge($changes, [
                        'delegated_amount' => '0',
                        'jailed_or_delinquent' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }

            DB::table('staking_admin_approvals')->where('id', $approval->id)->update([
                'status' => 'approved',
                'approved_by_admin_id' => $adminId,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['data' => DB::table('staking_admin_approvals')->where('id', $approval->id)->first()]);
        });
    }

    public function wallets(Request $request): JsonResponse
    {
        return response()->json([
            'data' => DB::table('staking_wallets')
                ->join('staking_assets', 'staking_assets.id', '=', 'staking_wallets.staking_asset_id')
                ->select('staking_wallets.*', 'staking_assets.symbol')
                ->latest('staking_wallets.id')
                ->paginate((int) $request->query('per_page', 50)),
        ]);
    }

    public function delegationBatches(Request $request): JsonResponse
    {
        return response()->json([
            'data' => DB::table('staking_delegation_batches')
                ->join('staking_assets', 'staking_assets.id', '=', 'staking_delegation_batches.staking_asset_id')
                ->leftJoin('staking_validators', 'staking_validators.id', '=', 'staking_delegation_batches.validator_id')
                ->select('staking_delegation_batches.*', 'staking_assets.symbol', 'staking_validators.validator_name')
                ->latest('staking_delegation_batches.id')
                ->paginate((int) $request->query('per_page', 50)),
        ]);
    }

    public function rewardBatches(Request $request): JsonResponse
    {
        return response()->json([
            'data' => DB::table('staking_reward_batches')
                ->join('staking_assets', 'staking_assets.id', '=', 'staking_reward_batches.staking_asset_id')
                ->leftJoin('staking_validators', 'staking_validators.id', '=', 'staking_reward_batches.staking_validator_id')
                ->select('staking_reward_batches.*', 'staking_assets.symbol', 'staking_validators.validator_name')
                ->latest('staking_reward_batches.id')
                ->paginate((int) $request->query('per_page', 50)),
        ]);
    }

    public function reconciliationReports(Request $request): JsonResponse
    {
        return response()->json([
            'data' => DB::table('staking_reconciliation_reports')
                ->join('staking_assets', 'staking_assets.id', '=', 'staking_reconciliation_reports.staking_asset_id')
                ->select('staking_reconciliation_reports.*', 'staking_assets.symbol')
                ->latest('staking_reconciliation_reports.id')
                ->paginate((int) $request->query('per_page', 50)),
        ]);
    }

    public function exaTokenCampaigns(Request $request): JsonResponse
    {
        return response()->json([
            'data' => DB::table('exatoken_staking_campaigns')
                ->latest('id')
                ->paginate((int) $request->query('per_page', 50)),
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        return response()->json([
            'data' => DB::table('staking_audit_logs')
                ->latest('id')
                ->paginate((int) $request->query('per_page', 50)),
        ]);
    }

    private function dualApprovalRequired(Request $request, string $type, string $subjectType, int $subjectId, array $changes): JsonResponse
    {
        $id = DB::table('staking_admin_approvals')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'approval_type' => $type,
            'status' => 'pending',
            'requested_by_admin_id' => $request->user()?->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'proposed_changes' => json_encode($changes),
            'reason' => (string) ($changes['reason'] ?? $request->input('reason', 'Dual approval required.')),
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit('admin.staking.approval.requested', $request, $id, ['type' => $type, 'subject_type' => $subjectType, 'subject_id' => $subjectId]);

        return response()->json(['data' => DB::table('staking_admin_approvals')->where('id', $id)->first()], 202);
    }

    private function audit(string $event, Request $request, int $subjectId, array $payload): void
    {
        DB::table('staking_audit_logs')->insert([
            'user_id' => null,
            'subject_type' => $event,
            'subject_id' => $subjectId,
            'status' => 'recorded',
            'reference' => (string) Str::uuid(),
            'metadata' => json_encode([
                'admin_id' => $request->user()?->id,
                'payload' => $payload,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
