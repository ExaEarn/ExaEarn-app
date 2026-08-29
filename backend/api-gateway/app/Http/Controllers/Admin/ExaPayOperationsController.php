<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Services\Fiat\ExaPayMerchantService;
use App\Services\Fiat\PaymentProviderHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExaPayOperationsController extends Controller
{
    public function overview(PaymentProviderHealthService $health): JsonResponse
    {
        return response()->json([
            'data' => [
                'merchants' => [
                    'active' => Merchant::query()->where('status', 'ACTIVE')->count(),
                    'under_review' => Merchant::query()->whereIn('status', ['APPLIED', 'UNDER_REVIEW', 'NEEDS_INFORMATION'])->count(),
                    'restricted' => Merchant::query()->whereIn('status', ['RESTRICTED', 'SUSPENDED'])->count(),
                ],
                'payments' => [
                    'captured' => DB::table('exaearn_pay_intents')->where('status', 'CAPTURED')->count(),
                    'failed' => DB::table('exaearn_pay_intents')->where('status', 'FAILED')->count(),
                    'pending' => DB::table('exaearn_pay_intents')->whereIn('status', ['CREATED', 'PROCESSING', 'AUTHORIZED'])->count(),
                ],
                'settlements' => DB::table('merchant_settlements')->latest('id')->limit(25)->get(),
                'provider_health' => $health->refreshAll(),
            ],
        ]);
    }

    public function merchants(): JsonResponse
    {
        return response()->json(['data' => Merchant::query()->with('teamMembers')->latest('id')->limit(100)->get()]);
    }

    public function approve(Request $request, string $merchantId, ExaPayMerchantService $service): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $merchant = Merchant::query()->where('merchant_id', $merchantId)->orWhere('id', $merchantId)->firstOrFail();

        return response()->json(['data' => $service->approve($merchant, (int) $request->user()?->id, $payload['reason'])]);
    }

    public function restrict(Request $request, string $merchantId, ExaPayMerchantService $service): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'in:NEEDS_INFORMATION,RESTRICTED,SUSPENDED,REJECTED,CLOSED'],
            'reason' => ['required', 'string', 'max:200'],
        ]);
        $merchant = Merchant::query()->where('merchant_id', $merchantId)->orWhere('id', $merchantId)->firstOrFail();

        return response()->json(['data' => $service->restrict($merchant, $payload['status'], $payload['reason'])]);
    }

    public function reconciliation(Request $request, ExaPayMerchantService $service): JsonResponse
    {
        $merchant = $request->filled('merchant_id')
            ? Merchant::query()->where('merchant_id', $request->string('merchant_id'))->orWhere('id', $request->string('merchant_id'))->firstOrFail()
            : null;

        return response()->json(['data' => $service->reconcile($merchant)]);
    }

    public function reports(): JsonResponse
    {
        return response()->json([
            'data' => [
                'payments_by_status' => DB::table('exaearn_pay_intents')->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as amount'))->groupBy('status')->get(),
                'refunds' => DB::table('payment_refunds')->latest('id')->limit(50)->get(),
                'disputes' => DB::table('payment_disputes')->latest('id')->limit(50)->get(),
                'risk_signals' => DB::table('merchant_risk_signals')->latest('id')->limit(50)->get(),
                'webhook_events' => DB::table('merchant_webhook_events')->latest('id')->limit(50)->get(),
            ],
        ]);
    }
}
