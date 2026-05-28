<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCardInventory;
use App\Models\GiftcardOrder;
use App\Services\GiftCard\GiftCardBuyService;
use App\Services\GiftCard\GiftCardInventoryService;
use App\Services\GiftCard\GiftCardPricingEngine;
use App\Services\GiftCard\GiftCardValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin Gift Card Buy Controller
 *
 * Admin endpoints for managing gift card inventory and purchases.
 */
class AdminGiftCardBuyController extends Controller
{
    public function __construct(
        private readonly GiftCardBuyService $buyService,
        private readonly GiftCardInventoryService $inventoryService,
        private readonly GiftCardPricingEngine $pricingEngine,
        private readonly GiftCardValidationService $validationService,
    ) {
    }

    /**
     * GET /api/admin/giftcard/inventory
     *
     * Get inventory summary by brand.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getInventory(Request $request): JsonResponse
    {
        $brand = $request->query('brand');

        if ($brand) {
            $summary = $this->inventoryService->getInventorySummary($brand);

            return response()->json([
                'brand' => $brand,
                'inventory' => $summary,
            ]);
        }

        // Get all brands with inventory
        $brands = GiftCardInventory::query()
            ->distinct()
            ->pluck('brand');

        $allInventory = [];
        foreach ($brands as $b) {
            $allInventory[$b] = $this->inventoryService->getInventorySummary($b);
        }

        return response()->json($allInventory);
    }

    /**
     * POST /api/admin/giftcard/inventory/bulk-upload
     *
     * Upload bulk gift cards to inventory.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadInventory(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'brand' => ['required', 'string', 'lowercase'],
                'cards' => ['required', 'array', 'min:1'],
                'cards.*.card_value' => ['required', 'numeric', 'min:1'],
                'cards.*.card_code' => ['required', 'string'],
                'cards.*.card_pin' => ['nullable', 'string'],
            ]);

            $uploaded = 0;
            $errors = [];

            foreach ($validated['cards'] as $index => $cardData) {
                try {
                    $encryptedCode = $this->validationService->encryptCardData(
                        $cardData['card_code'],
                        $cardData['card_pin'] ?? null
                    );

                    $card = $this->inventoryService->addToInventory(
                        brand: $validated['brand'],
                        cardValue: (float) $cardData['card_value'],
                        encryptedCode: $encryptedCode['encrypted_code'],
                        encryptedPin: $encryptedCode['encrypted_pin'] ?? null,
                        submissionId: null,
                        metadata: [
                            'uploaded_by' => auth()->id(),
                            'uploaded_at' => now()->toIso8601String(),
                            'source' => 'bulk_upload',
                        ]
                    );

                    $uploaded++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'card_value' => $cardData['card_value'],
                        'error' => $e->getMessage(),
                    ];
                }
            }

            Log::info('Bulk inventory upload', [
                'brand' => $validated['brand'],
                'uploaded' => $uploaded,
                'errors' => count($errors),
                'uploaded_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'uploaded' => $uploaded,
                'failed' => count($errors),
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk inventory upload failed', [
                'error' => $e->getMessage(),
                'uploaded_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/admin/giftcard/buy-orders
     *
     * Get all purchase orders with optional filters.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPurchaseOrders(Request $request): JsonResponse
    {
        $query = GiftcardOrder::query()
            ->where('type', 'buy')
            ->with(['user:id,email,name'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filter by risk level
        if ($request->has('risk_level')) {
            $query->where('risk_level', $request->query('risk_level'));
        }

        // Filter by requiring review
        if ($request->query('requires_review') === 'true') {
            $query->where('requires_admin_review', true);
        }

        $orders = $query->paginate($request->query('per_page', 20));

        $orders->transform(function ($order) {
            return [
                'id' => $order->id,
                'user' => $order->user,
                'brand' => $order->metadata['brand'] ?? null,
                'quantity' => $order->metadata['quantity'] ?? null,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'status' => $order->status,
                'risk_level' => $order->risk_level,
                'risk_score' => $order->risk_score,
                'requires_review' => $order->requires_admin_review,
                'created_at' => $order->created_at,
                'processed_at' => $order->processed_at,
                'delivered_at' => $order->delivered_at,
            ];
        });

        return response()->json([
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * POST /api/admin/giftcard/buy-orders/:id/approve
     *
     * Approve a purchase order.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function approvePurchase(int $id): JsonResponse
    {
        try {
            $result = $this->buyService->approvePurchase($id, auth()->id());

            Log::info('Purchase order approved', [
                'order_id' => $id,
                'approved_by' => auth()->id(),
            ]);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to approve purchase', [
                'order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/admin/giftcard/buy-orders/:id/reject
     *
     * Reject a purchase order.
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function rejectPurchase(int $id, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => ['required', 'string', 'min:10', 'max:500'],
            ]);

            $result = $this->buyService->rejectPurchase(
                $id,
                auth()->id(),
                $validated['reason']
            );

            Log::info('Purchase order rejected', [
                'order_id' => $id,
                'rejected_by' => auth()->id(),
                'reason' => $validated['reason'],
            ]);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to reject purchase', [
                'order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/admin/giftcard/pricing-rates
     *
     * Get all gift card pricing rates.
     *
     * @return JsonResponse
     */
    public function getPricingRates(): JsonResponse
    {
        $rates = \App\Models\GiftCardRate::query()
            ->active()
            ->get()
            ->map(fn ($rate) => [
                'brand' => $rate->brand,
                'currency' => $rate->currency,
                'sell_rate' => (float) $rate->rate,
                'min_value' => $rate->min_value,
                'max_value' => $rate->max_value,
            ]);

        return response()->json($rates);
    }

    /**
     * PUT /api/admin/giftcard/pricing-rates/:id
     *
     * Update pricing rate.
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePricingRate(int $id, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'rate' => ['required', 'numeric', 'min:0.01', 'max:1'],
                'min_value' => ['nullable', 'numeric', 'min:1'],
                'max_value' => ['nullable', 'numeric', 'min:1'],
                'active' => ['nullable', 'boolean'],
            ]);

            $rate = \App\Models\GiftCardRate::findOrFail($id);
            $rate->update($validated);

            Log::info('Pricing rate updated', [
                'rate_id' => $id,
                'updated_by' => auth()->id(),
                'data' => $validated,
            ]);

            return response()->json([
                'success' => true,
                'rate' => $rate,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/admin/giftcard/statistics
     *
     * Get gift card buy system statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $period = $request->query('period', '7'); // days

        $query = GiftcardOrder::query()
            ->where('type', 'buy')
            ->where('created_at', '>=', now()->subDays((int) $period));

        return response()->json([
            'period_days' => (int) $period,
            'total_orders' => $query->count(),
            'total_volume' => $query->sum('amount'),
            'average_order' => $query->avg('amount'),
            'by_status' => $query->groupBy('status')->selectRaw('status, COUNT(*) as count')->get(),
            'by_brand' => $query->whereNotNull('metadata->brand')
                ->groupBy('metadata->brand')
                ->selectRaw("json_extract(metadata, '$.brand') as brand, COUNT(*) as count")
                ->get(),
            'auto_approved' => $query->where('status', 'delivered')->where('requires_admin_review', false)->count(),
            'pending_review' => $query->where('status', 'pending_review')->count(),
        ]);
    }
}
