<?php

namespace App\Http\Controllers;

use App\Services\GiftCard\ArbitrageDetectionService;
use App\Services\GiftCard\RateEngineService;
use App\Services\GiftCard\RateLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GiftCardPricingController extends Controller
{
    public function __construct(
        private readonly RateEngineService $rateEngine,
        private readonly RateLockService $rateLock,
        private readonly ArbitrageDetectionService $arbitrage
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'brand' => ['required', 'string', 'max:80'],
            'value' => ['required', 'numeric', 'min:1', 'max:100000'],
        ]);

        $rates = $this->rateEngine->getRates($validated['brand'], (float) $validated['value']);

        return response()->json([
            'status' => 'success',
            'data' => $rates + [
                'market_feedback' => $this->marketFeedback($rates),
            ],
        ]);
    }

    public function lock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'brand' => ['required', 'string', 'max:80'],
            'value' => ['required', 'numeric', 'min:1', 'max:100000'],
            'transaction_type' => ['required', Rule::in(['buy', 'sell'])],
        ]);

        $userId = (string) optional($request->user())->getAuthIdentifier();
        if ($userId === '') {
            $userId = (string) $request->ip();
        }

        $lock = $this->rateLock->lockRates(
            $validated['brand'],
            (float) $validated['value'],
            $userId,
            $validated['transaction_type']
        );

        return response()->json([
            'status' => 'success',
            'data' => $lock,
        ], 201);
    }

    public function lockStatus(string $lockId): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->rateLock->getLockStatus($lockId),
        ]);
    }

    public function adminIndex(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'rates' => $this->rateEngine->getAllRates(),
                'arbitrage' => $this->arbitrage->getAllArbitrageOpportunities(),
            ],
        ]);
    }

    public function adminUpdate(Request $request, string $brand): JsonResponse
    {
        $validated = $request->validate([
            'market_buy_rate' => ['sometimes', 'numeric', 'min:1'],
            'market_sell_rate' => ['sometimes', 'numeric', 'min:1'],
            'external_buy_rate' => ['sometimes', 'numeric', 'min:1'],
            'external_sell_rate' => ['sometimes', 'numeric', 'min:1'],
            'min_profit_margin' => ['sometimes', 'numeric', 'min:0.01', 'max:0.5'],
            'inventory_level' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'demand_level' => ['sometimes', 'numeric', 'min:0', 'max:1'],
        ]);

        if (array_key_exists('inventory_level', $validated)) {
            $this->rateEngine->updateInventoryLevel($brand, (float) $validated['inventory_level']);
        }
        if (array_key_exists('demand_level', $validated)) {
            $this->rateEngine->updateDemandLevel($brand, (float) $validated['demand_level']);
        }

        $rates = $this->rateEngine->overrideRates($brand, $validated);

        return response()->json([
            'status' => 'success',
            'data' => $rates,
        ]);
    }

    private function marketFeedback(array $rates): string
    {
        if ($rates['demand_level'] === 'High') {
            return 'High demand - rates optimized';
        }
        if ($rates['inventory_status'] === 'Limited') {
            return 'Limited inventory - live rate protected';
        }

        return 'Live market rate available';
    }
}
