<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Swap;
use App\Models\Wallet;
use App\Services\SwapEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SwapController extends Controller
{
    public function __construct(private readonly SwapEngineService $swapEngineService)
    {
    }

    public function meta(Request $request): JsonResponse
    {
        $supportedFiat = collect((array) config('swap.supported_fiat', []))
            ->map(fn (string $code): array => [
                'code' => strtoupper($code),
                'type' => 'fiat',
                'network' => 'fiat',
                'decimals' => 2,
                'convert_enabled' => true,
                'status' => 'available',
            ]);

        $supportedCrypto = collect((array) config('swap.supported_crypto', []))
            ->map(function (string $code): array {
                $asset = (array) (config('wallet.assets.' . strtoupper($code)) ?? []);
                return [
                    'code' => strtoupper($code),
                    'type' => 'crypto',
                    'network' => (string) ($asset['network'] ?? 'crypto'),
                    'decimals' => (int) ($asset['decimals'] ?? 8),
                    'convert_enabled' => true,
                    'status' => 'available',
                ];
            });

        $userId = (int) $request->user()->id;
        $wallets = Wallet::query()
            ->where('user_id', $userId)
            ->get()
            ->keyBy(fn (Wallet $wallet): string => strtoupper((string) $wallet->currency));

        $assets = $supportedCrypto
            ->concat($supportedFiat)
            ->map(function (array $asset) use ($wallets): array {
                /** @var Wallet|null $wallet */
                $wallet = $wallets->get($asset['code']);

                return array_merge($asset, [
                    'available_balance' => (string) ($wallet?->available_balance ?? '0'),
                    'locked_balance' => (string) ($wallet?->locked_balance ?? '0'),
                    'total_balance' => (string) ($wallet?->total_balance ?? '0'),
                ]);
            })
            ->values()
            ->all();

        $recent = Swap::query()
            ->where('user_id', $userId)
            ->latest()
            ->limit(10)
            ->get([
                'swap_id',
                'from_currency',
                'to_currency',
                'amount_sent',
                'amount_received',
                'rate',
                'fee',
                'status',
                'failure_reason',
                'created_at',
            ]);

        return response()->json([
            'data' => [
                'assets' => $assets,
                'quote_ttl_seconds' => (int) config('swap.quote_ttl_seconds', 20),
                'fee_percent' => (string) config('swap.fee_percent', '0.5'),
                'defaults' => [
                    'from_currency' => collect($assets)->firstWhere('code', 'USDT')['code'] ?? collect($assets)->first()['code'] ?? 'USDT',
                    'to_currency' => collect($assets)->firstWhere('code', 'NGN')['code'] ?? collect($assets)->skip(1)->first()['code'] ?? 'USD',
                ],
                'recent_swaps' => $recent,
            ],
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'from_currency' => ['required', 'string', 'max:16'],
            'to_currency' => ['required', 'string', 'max:16'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $quote = $this->swapEngineService->createQuote(
                (int) $request->user()->id,
                (string) $payload['from_currency'],
                (string) $payload['to_currency'],
                (string) $payload['amount']
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'quote_id' => $quote->quote_id,
                'from_currency' => $quote->from_currency,
                'to_currency' => $quote->to_currency,
                'amount' => $quote->amount_sent,
                'receive_amount' => $quote->amount_received,
                'rate' => $quote->rate,
                'fee' => $quote->fee,
                'route' => $quote->route,
                'expires_in' => max(0, now()->diffInSeconds($quote->expires_at, false)),
                'expires_at' => $quote->expires_at?->toISOString(),
                'metadata' => $quote->metadata,
            ],
        ], 201);
    }

    public function execute(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'quote_id' => ['required', 'uuid'],
        ]);

        try {
            $swap = $this->swapEngineService->execute(
                (int) $request->user()->id,
                $payload['quote_id'],
                $request->header('X-Idempotency-Key')
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Swap queued for execution.',
            'data' => $swap,
        ], 202);
    }

    public function history(Request $request): JsonResponse
    {
        $swaps = Swap::query()
            ->where('user_id', (int) $request->user()->id)
            ->latest()
            ->paginate((int) $request->query('per_page', 20));

        return response()->json(['data' => $swaps]);
    }

    public function show(Request $request, string $swapId): JsonResponse
    {
        $swap = Swap::query()
            ->where('swap_id', $swapId)
            ->where('user_id', (int) $request->user()->id)
            ->first();

        if (!$swap) {
            return response()->json(['message' => 'Swap not found.'], 404);
        }

        return response()->json(['data' => $swap]);
    }
}
