<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GiftCardInventory;
use App\Models\GiftcardOrder;
use App\Services\GiftCard\GiftCardBuyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gift Card Buy Controller
 *
 * API endpoints for purchasing gift cards.
 */
class GiftCardBuyController extends Controller
{
    public function __construct(
        private readonly GiftCardBuyService $buyService,
    ) {}

    /**
     * POST /api/giftcard/buy
     *
     * Purchase gift cards.
     */
    public function buy(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'brand' => ['required', 'string', 'lowercase'],
                'card_value' => ['required', 'numeric', 'min:1', 'max:100000'],
                'quantity' => ['required', 'integer', 'min:1', 'max:100'],
                'currency' => ['required', 'string', 'uppercase', 'max:8', Rule::in($this->supportedCurrencies())],
                'payment_wallet_currency' => ['nullable', 'string', 'uppercase', 'max:8', Rule::in($this->supportedCurrencies())],
            ]);

            $user = auth()->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Use specified payment wallet or default to currency
            $paymentWalletCurrency = $validated['payment_wallet_currency'] ?? $validated['currency'];

            // Execute purchase
            $result = $this->buyService->purchaseCards(
                user: $user,
                brand: $validated['brand'],
                cardValue: (float) $validated['card_value'],
                quantity: (int) $validated['quantity'],
                currency: $validated['currency'],
                paymentWalletCurrency: $paymentWalletCurrency
            );

            Log::info('Gift card purchase attempted', [
                'user_id' => $user->id,
                'brand' => $validated['brand'],
                'quantity' => $validated['quantity'],
                'success' => $result['success'],
            ]);

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Gift card purchase error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/giftcard/orders
     *
     * Get user's purchase orders.
     */
    public function getOrders(Request $request): JsonResponse
    {
        $user = auth()->user();

        $orders = GiftcardOrder::query()
            ->where('user_id', $user->id)
            ->where('type', 'buy')
            ->orderBy('created_at', 'desc')
            ->paginate($request->query('per_page', 20));

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
     * GET /api/giftcard/orders/:id
     *
     * Get specific order details.
     */
    public function getOrder(int $id): JsonResponse
    {
        $user = auth()->user();

        $order = GiftcardOrder::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->where('type', 'buy')
            ->firstOrFail();

        return response()->json([
            'id' => $order->id,
            'brand' => $order->metadata['brand'] ?? null,
            'quantity' => $order->metadata['quantity'] ?? null,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'status' => $order->status,
            'risk_level' => $order->risk_level,
            'created_at' => $order->created_at,
            'delivered_at' => $order->delivered_at,
            'metadata' => $order->metadata,
        ]);
    }

    /**
     * GET /api/giftcard/orders/:id/cards
     *
     * Get delivered cards from order (masked view).
     */
    public function getOrderCards(int $id): JsonResponse
    {
        $user = auth()->user();

        $order = GiftcardOrder::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->where('type', 'buy')
            ->where('status', 'delivered')
            ->firstOrFail();

        $cardIds = $order->metadata['card_ids_delivered'] ?? [];

        $cards = GiftCardInventory::query()
            ->whereIn('id', $cardIds)
            ->where('sold_to_user_id', $user->id)
            ->get()
            ->map(fn ($card) => [
                'id' => $card->id,
                'brand' => $card->brand,
                'card_value' => $card->card_value,
                'currency' => $card->currency,
                'sold_at' => $card->sold_at,
            ]);

        return response()->json([
            'order_id' => $order->id,
            'card_count' => count($cardIds),
            'cards' => $cards,
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
