<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateClawback;
use App\Models\AffiliateCommissionEvent;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReconciliationIncident;
use App\Models\AffiliateTier;
use App\Models\Referral;
use App\Services\AffiliateCommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateOperationsController extends Controller
{
    public function __construct(private readonly AffiliateCommissionService $affiliates)
    {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['data' => [
            'affiliates' => Referral::query()->distinct('referrer_user_id')->count('referrer_user_id'),
            'referrals' => Referral::query()->count(),
            'commissions' => AffiliateCommissionEvent::query()->count(),
            'pending' => AffiliateCommissionEvent::query()->where('status', 'PENDING')->sum('commission_amount'),
            'held' => AffiliateCommissionEvent::query()->where('status', 'HELD')->sum('commission_amount'),
            'available' => AffiliateCommissionEvent::query()->where('status', 'AVAILABLE')->sum('commission_amount'),
            'paid' => AffiliateCommissionEvent::query()->where('status', 'PAID')->sum('commission_amount'),
            'clawbacks' => AffiliateClawback::query()->whereIn('status', ['PENDING', 'PARTIALLY_APPLIED'])->sum('amount'),
            'exatoken_distribution' => 'DISABLED',
        ]]);
    }

    public function commissions(Request $request): JsonResponse
    {
        return response()->json(['data' => AffiliateCommissionEvent::query()
            ->when($request->query('status'), fn ($q, string $status) => $q->where('status', strtoupper($status)))
            ->latest()
            ->paginate((int) $request->query('per_page', 50))]);
    }

    public function payouts(Request $request): JsonResponse
    {
        return response()->json(['data' => AffiliatePayout::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function clawbacks(Request $request): JsonResponse
    {
        return response()->json(['data' => AffiliateClawback::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function tiers(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'code' => ['required', 'string', 'max:40'],
                'name' => ['required', 'string', 'max:120'],
                'commission_rate_bps' => ['required', 'numeric', 'gte:0'],
                'monthly_cap' => ['nullable', 'numeric', 'gte:0'],
                'minimum_payout' => ['nullable', 'numeric', 'gte:0'],
                'payout_frequency' => ['nullable', 'string', 'max:40'],
                'eligible_products' => ['nullable', 'array'],
                'qualification_rules' => ['nullable', 'array'],
                'status' => ['nullable', 'string', 'max:40'],
            ]);

            $tier = AffiliateTier::query()->updateOrCreate(
                ['code' => strtoupper($data['code'])],
                array_merge($data, [
                    'code' => strtoupper($data['code']),
                    'status' => strtoupper($data['status'] ?? 'ACTIVE'),
                ]),
            );

            return response()->json(['data' => $tier], 201);
        }

        return response()->json(['data' => AffiliateTier::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function reconcile(): JsonResponse
    {
        return response()->json(['data' => $this->affiliates->reconcile()]);
    }

    public function incidents(Request $request): JsonResponse
    {
        return response()->json(['data' => AffiliateReconciliationIncident::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }
}
