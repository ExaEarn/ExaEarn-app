<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\P2P\Services\P2POperationalReadinessService;
use App\Domain\P2P\Services\P2PReconciliationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class P2POperationsController extends Controller
{
    public function overview(P2POperationalReadinessService $readiness): JsonResponse
    {
        return response()->json([
            'data' => [
                'readiness' => $readiness->check(),
                'orders' => [
                    'open' => DB::table('p2p_trades')->whereIn('status', ['pending', 'payment_sent', 'disputed'])->count(),
                    'completed_24h' => DB::table('p2p_trades')->where('status', 'released')->where('completed_at', '>=', now()->subDay())->count(),
                    'disputed' => DB::table('p2p_trades')->where('status', 'disputed')->count(),
                ],
                'ads' => [
                    'active' => DB::table('p2p_ads')->where('status', 'active')->count(),
                    'paused' => DB::table('p2p_ads')->where('status', 'paused')->count(),
                ],
                'merchants' => [
                    'active' => DB::table('p2p_merchant_profiles')->whereIn('state', ['approved', 'active'])->count(),
                    'under_review' => DB::table('p2p_merchant_profiles')->whereIn('state', ['applied', 'under_review'])->count(),
                ],
            ],
        ]);
    }

    public function orders(): JsonResponse
    {
        return response()->json([
            'data' => DB::table('p2p_trades')->latest('id')->limit(100)->get(),
        ]);
    }

    public function ads(): JsonResponse
    {
        return response()->json([
            'data' => DB::table('p2p_ads')->latest('id')->limit(100)->get(),
        ]);
    }

    public function merchants(): JsonResponse
    {
        return response()->json([
            'data' => DB::table('p2p_merchant_profiles')->latest('id')->limit(100)->get(),
        ]);
    }

    public function disputes(): JsonResponse
    {
        return response()->json([
            'data' => DB::table('p2p_disputes')->latest('id')->limit(100)->get(),
        ]);
    }

    public function risk(): JsonResponse
    {
        return response()->json([
            'data' => DB::table('p2p_risk_events')->latest('id')->limit(100)->get(),
        ]);
    }

    public function reconciliation(P2PReconciliationService $reconciliation): JsonResponse
    {
        return response()->json(['data' => $reconciliation->run()]);
    }

    public function paymentMethods(): JsonResponse
    {
        return response()->json([
            'data' => DB::table('p2p_payment_methods')->latest('id')->limit(100)->get(),
        ]);
    }

    public function escrow(): JsonResponse
    {
        return response()->json([
            'data' => DB::table('p2p_escrows')->latest('id')->limit(100)->get(),
        ]);
    }
}
