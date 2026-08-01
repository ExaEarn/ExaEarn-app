<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GiftcardOrder;
use App\Services\GiftCard\GiftCardPurchaseService;
use App\Services\GiftCard\GiftCardRateEngine;
use App\Services\GiftcardService;
use App\Services\LedgerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;

class GiftCardController extends Controller
{
    public function __construct(
        private readonly GiftcardService $giftcardService,
        private readonly GiftCardPurchaseService $purchaseService
    ) {}

    public function inventory(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->giftcardService->inventory(
                $request->only(['card_type', 'provider', 'amount']),
                (int) $request->query('per_page', 20)
            ),
        ]);
    }

    public function myOrders(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->giftcardService->myOrders($request->user(), (int) $request->query('per_page', 20)),
        ]);
    }

    public function show(Request $request, int $orderId): JsonResponse
    {
        try {
            $order = $this->giftcardService->orderDetail($request->user(), $orderId);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $order]);
    }

    /**
     * POST /api/giftcard/sell
     * Submit a gift card for selling via production-grade system.
     */
    public function sell(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'brand' => 'required|string|max:50',
            'card_value' => 'required|numeric|gt:0',
            'currency' => ['required', 'string', 'max:8', Rule::in($this->supportedCurrencies())],
            'card_code' => 'required|string|min:5|max:500',
            'card_pin' => 'nullable|string|min:3|max:500',
            // Legacy fields for backward compatibility
            'card_type' => 'nullable|string|max:120',
            'provider' => 'nullable|string|max:120',
            'amount' => 'nullable|numeric|gt:0',
            'source_mode' => 'nullable|string|max:32',
            'payment_method' => 'nullable|string|max:64',
            'device_id' => 'nullable|string|max:255',
            'geo_location' => 'nullable|string|max:32',
            'is_vpn' => 'nullable|boolean',
        ]);

        try {
            // Use legacy gift card system for sell orders
            // Map new fields to legacy expected fields
            $legacyPayload = $payload;
            if (isset($payload['card_value'])) {
                $legacyPayload['amount'] = $payload['card_value'];
            }
            if (isset($payload['brand'])) {
                $legacyPayload['card_type'] = $payload['brand'];
            }

            $order = $this->giftcardService->submitSellOrder($request->user(), $legacyPayload, [
                'ip_address' => $request->ip(),
                'device_id' => $payload['device_id'] ?? null,
                'geo_location' => $payload['geo_location'] ?? 'unknown',
                'is_vpn' => (bool) ($payload['is_vpn'] ?? false),
            ]);

            return response()->json([
                'message' => 'Giftcard sell order submitted for fraud analysis.',
                'data' => $order,
            ], 202);
        } catch (RuntimeException $e) {
            Log::warning('Gift card submission failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/giftcard/submissions
     * Get user's gift card submission history.
     */
    public function submissions(Request $request): JsonResponse
    {
        $submissions = $this->giftCardSellService->getUserSubmissions($request->user()->id);

        return response()->json([
            'data' => $submissions,
            'total' => count($submissions),
        ]);
    }

    /**
     * GET /api/giftcard/submissions/{id}
     * Get details of a specific submission.
     */
    public function submissionDetails(Request $request, int $id): JsonResponse
    {
        try {
            $submission = $this->giftCardSellService->getSubmissionDetails($id, $request->user()->id);

            return response()->json([
                'data' => $submission->makeHidden(['encrypted_card_code', 'encrypted_card_pin']),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Submission not found.'], 404);
        }
    }

    /**
     * GET /api/giftcard/rates
     * Get current gift card rates for all brands.
     */
    public function rates(): JsonResponse
    {
        $rateEngine = app(GiftCardRateEngine::class);
        $rates = $rateEngine->getAllRates();

        return response()->json([
            'data' => $rates,
        ]);
    }

    public function buy(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'brand' => ['required_without:giftcard_id', 'string', 'max:50'],
            'card_value' => ['required_without:giftcard_id', 'numeric', 'gt:0'],
            'quantity' => ['required_without:giftcard_id', 'integer', 'min:1', 'max:100'],
            'currency' => ['required_without:giftcard_id', 'string', 'max:8', Rule::in($this->supportedCurrencies())],
            'payment_method' => ['nullable', 'string', 'max:64'],
            'giftcard_id' => ['nullable', 'integer', 'exists:giftcard_inventory,id'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'geo_location' => ['nullable', 'string', 'max:32'],
            'is_vpn' => ['nullable', 'boolean'],
        ]);

        try {
            $order = $this->giftcardService->submitBuyOrder($request->user(), $payload, [
                'ip_address' => $request->ip(),
                'device_id' => $payload['device_id'] ?? null,
                'geo_location' => $payload['geo_location'] ?? 'unknown',
                'is_vpn' => (bool) ($payload['is_vpn'] ?? false),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Giftcard buy order submitted for fraud analysis.',
            'data' => $order,
        ], 202);
    }

    public function reviewQueue(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->giftcardService->reviewQueue((int) $request->query('per_page', 20)),
        ]);
    }

    public function decide(Request $request, int $orderId): JsonResponse
    {
        $payload = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject,review'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $order = $this->giftcardService->applyAdminDecision(
                $orderId,
                $payload['decision'],
                $request->user()->id,
                $payload['reason'] ?? null
            );

            return response()->json([
                'message' => 'Admin decision applied successfully.',
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/giftcard/admin/submissions/{submissionId}/approve
     * Admin approve a gift card submission.
     */
    public function approveSubmission(Request $request, int $submissionId): JsonResponse
    {
        $payload = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $submission = $this->giftCardSellService->approveSubmission($submissionId, $request->user()->id);

            return response()->json([
                'message' => 'Gift card submission approved and wallet credited.',
                'data' => $submission,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/giftcard/admin/submissions/{submissionId}/reject
     * Admin reject a gift card submission.
     */
    public function rejectSubmission(Request $request, int $submissionId): JsonResponse
    {
        $payload = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $submission = $this->giftCardSellService->rejectSubmission($submissionId, $request->user()->id, $payload['reason']);

            return response()->json([
                'message' => 'Gift card submission rejected.',
                'data' => $submission,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/giftcard/purchase
     * Purchase a gift card with complete fee accounting and ledger tracking.
     *
     * Request:
     * {
     *   "brand": "amazon",
     *   "card_value": 50.00,
     *   "delivery_email": "user@example.com",
     *   "currency": "USD",
     *   "wallet_type": "funding"
     * }
     */
    public function purchase(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'brand' => 'required|string|in:amazon,apple,steam,google_play',
            'card_value' => 'required|numeric|gt:0|max:10000',
            'delivery_email' => 'required|email|max:255',
            'currency' => ['required', 'string', 'max:8', Rule::in($this->supportedCurrencies())],
            'wallet_type' => 'nullable|string|in:funding,spot,savings',
            'metadata' => 'nullable|array',
        ]);

        try {
            $order = $this->purchaseService->purchaseGiftCard(
                $request->user(),
                $payload['brand'],
                (float) $payload['card_value'],
                $payload['delivery_email'],
                $payload['currency'],
                $payload['wallet_type'] ?? 'funding',
                $payload['metadata'] ?? []
            );

            return response()->json([
                'message' => 'Gift card purchased successfully.',
                'data' => [
                    'order_id' => $order->id,
                    'reference' => $order->reference,
                    'status' => $order->status,
                    'amount' => $order->amount,
                    'currency' => $order->currency,
                    'fees' => data_get($order->metadata, 'fee_breakdown', []),
                    'total_cost' => data_get($order->metadata, 'total_cost'),
                    'delivered_at' => $order->delivered_at,
                ],
            ], 201);
        } catch (RuntimeException $e) {
            Log::warning('Gift card purchase failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Gift card purchase error', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Purchase failed. Please try again.'], 500);
        }
    }

    /**
     * POST /api/giftcard/{orderId}/refund
     * Refund a completed purchase.
     */
    public function refundPurchase(Request $request, int $orderId): JsonResponse
    {
        $payload = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $order = $this->purchaseService->refundPurchase(
                $orderId,
                $payload['reason'] ?? 'user_request'
            );

            return response()->json([
                'message' => 'Gift card refunded successfully.',
                'data' => $order,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/giftcard/admin/revenue-summary
     * Admin endpoint for platform revenue tracking.
     */
    public function getRevenueSummary(Request $request): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $payload = $request->validate([
            'asset' => ['nullable', 'string', 'max:8', Rule::in($this->supportedCurrencies())],
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        try {
            $ledgerService = app(LedgerService::class);
            $summary = $ledgerService->getPlatformRevenueSummary(
                $payload['asset'] ?? null,
                isset($payload['from']) && $payload['from'] !== null ? new \DateTime($payload['from']) : null,
                isset($payload['to']) && $payload['to'] !== null ? new \DateTime($payload['to']) : null
            );

            return response()->json([
                'data' => $summary,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/giftcard/admin/fee-report
     * Admin endpoint for detailed fee analysis.
     */
    public function getFeeReport(Request $request): JsonResponse
    {
        $this->authorize('viewAdmin', GiftcardOrder::class);

        $payload = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'brand' => 'nullable|string',
        ]);

        $query = GiftcardOrder::query()
            ->where('type', 'buy')
            ->where('status', 'completed');

        if ($payload['from'] ?? null) {
            $query->whereDate('created_at', '>=', $payload['from']);
        }

        if ($payload['to'] ?? null) {
            $query->whereDate('created_at', '<=', $payload['to']);
        }

        if ($payload['brand'] ?? null) {
            $query->whereJsonContains('metadata->brand', $payload['brand']);
        }

        $orders = $query->get();

        $report = [
            'period' => [
                'from' => $payload['from'] ?? null,
                'to' => $payload['to'] ?? null,
            ],
            'total_transactions' => $orders->count(),
            'total_revenue' => $orders->sum('amount'),
            'total_api_costs' => $orders->sum(fn ($o) => data_get($o->metadata, 'api_fee', 0)),
            'total_profit' => $orders->sum(fn ($o) => data_get($o->metadata, 'platform_profit', 0)),
            'average_order_value' => $orders->avg('amount'),
            'by_brand' => $orders->groupBy(fn ($o) => data_get($o->metadata, 'brand'))
                ->map(fn ($items) => [
                    'count' => $items->count(),
                    'revenue' => $items->sum('amount'),
                    'api_costs' => $items->sum(fn ($o) => data_get($o->metadata, 'api_fee', 0)),
                    'profit' => $items->sum(fn ($o) => data_get($o->metadata, 'platform_profit', 0)),
                ]),
            'by_currency' => $orders->groupBy('currency')
                ->map(fn ($items) => [
                    'count' => $items->count(),
                    'revenue' => $items->sum('amount'),
                    'profit' => $items->sum(fn ($o) => data_get($o->metadata, 'platform_profit', 0)),
                ]),
        ];

        return response()->json([
            'data' => $report,
        ]);
    }

    private function supportedCurrencies(): array
    {
        return array_values(array_unique(array_map(
            static fn (mixed $currency): string => strtoupper((string) $currency),
            (array) config('giftcard.supported_currencies', ['USD', 'EUR', 'GBP', 'NGN', 'ZAR'])
        )));
    }
}
