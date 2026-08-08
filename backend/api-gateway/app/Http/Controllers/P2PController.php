<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\P2PPaymentMethod;
use App\Services\P2PService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class P2PController extends Controller
{
    public function __construct(private readonly P2PService $p2pService)
    {
    }

    public function meta(Request $request): JsonResponse
    {
        $paymentMethods = P2PPaymentMethod::query()
            ->where('is_enabled', true)
            ->select('method_type', 'fiat_currency')
            ->distinct()
            ->orderBy('fiat_currency')
            ->orderBy('method_type')
            ->get()
            ->map(fn (P2PPaymentMethod $method) => [
                'method_type' => $method->method_type,
                'fiat_currency' => $method->fiat_currency,
            ])
            ->values();

        $configuredAssets = collect((array) config('p2p.supported_assets', []))
            ->map(fn ($symbol) => strtoupper(trim((string) $symbol)))
            ->filter()
            ->values();

        $walletAssets = collect((array) config('wallet.assets', []))
            ->filter(fn (array $asset): bool => ($asset['type'] ?? 'crypto') !== 'fiat')
            ->map(fn (array $asset): array => [
                'symbol' => strtoupper((string) ($asset['symbol'] ?? $asset['currency'] ?? '')),
                'name' => (string) ($asset['name'] ?? $asset['symbol'] ?? ''),
                'icon_url' => $asset['icon_url'] ?? $asset['icon'] ?? null,
            ])
            ->filter(fn (array $asset): bool => $asset['symbol'] !== '')
            ->keyBy('symbol');

        $assets = $configuredAssets
            ->map(fn (string $symbol): array => array_merge([
                'symbol' => $symbol,
                'name' => $symbol,
                'icon_url' => null,
            ], $walletAssets->get($symbol, [])))
            ->values();

        $fiatCurrencies = collect((array) config('p2p.supported_fiat', []))
            ->map(fn ($currency) => strtoupper(trim((string) $currency)))
            ->filter()
            ->unique()
            ->values();

        $configuredMethods = collect((array) config('p2p.supported_payment_methods', []))
            ->map(fn ($method) => trim((string) $method))
            ->filter()
            ->unique()
            ->values();

        return response()->json([
            'data' => [
                'assets' => $assets,
                'fiat_currencies' => $fiatCurrencies,
                'payment_method_types' => $configuredMethods,
                'payment_methods' => $paymentMethods,
            ],
        ]);
    }

    public function ads(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->p2pService->listAds($request->only([
                'type',
                'asset',
                'fiat_currency',
                'region',
                'payment_method',
                'price_min',
                'price_max',
                'per_page',
            ])),
        ]);
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        $methods = P2PPaymentMethod::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderBy('display_name')
            ->get()
            ->map(fn (P2PPaymentMethod $method) => $this->transformPaymentMethod($method))
            ->values();

        return response()->json(['data' => $methods]);
    }

    public function createPaymentMethod(Request $request): JsonResponse
    {
        $payload = $this->validatePaymentMethodPayload($request);
        $normalized = $this->normalizePaymentMethodPayload($payload);

        $method = DB::transaction(function () use ($request, $normalized): P2PPaymentMethod {
            if (($normalized['is_default'] ?? false) === true) {
                P2PPaymentMethod::query()
                    ->where('user_id', $request->user()->id)
                    ->where('method_type', $normalized['method_type'])
                    ->where('fiat_currency', $normalized['fiat_currency'])
                    ->update(['is_default' => false]);
            }

            /** @var P2PPaymentMethod $method */
            $method = P2PPaymentMethod::query()->create([
                ...$normalized,
                'user_id' => $request->user()->id,
                'is_enabled' => true,
            ]);

            return $method->fresh();
        });

        return response()->json(['data' => $this->transformPaymentMethod($method)], 201);
    }

    public function updatePaymentMethod(Request $request, int $paymentMethodId): JsonResponse
    {
        /** @var P2PPaymentMethod|null $method */
        $method = P2PPaymentMethod::query()
            ->where('id', $paymentMethodId)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($method === null) {
            return response()->json(['message' => 'Payment method not found.'], 404);
        }

        $payload = $this->validatePaymentMethodPayload($request, true);
        $normalized = $this->normalizePaymentMethodPayload($payload);

        DB::transaction(function () use ($request, $method, $normalized): void {
            $nextMethodType = $normalized['method_type'] ?? $method->method_type;
            $nextFiatCurrency = $normalized['fiat_currency'] ?? $method->fiat_currency;

            if (($normalized['is_default'] ?? false) === true) {
                P2PPaymentMethod::query()
                    ->where('user_id', $request->user()->id)
                    ->where('method_type', $nextMethodType)
                    ->where('fiat_currency', $nextFiatCurrency)
                    ->where('id', '!=', $method->id)
                    ->update(['is_default' => false]);
            }

            $method->fill($normalized);
            $method->save();
        });

        return response()->json(['data' => $this->transformPaymentMethod($method->fresh())]);
    }

    public function deletePaymentMethod(Request $request, int $paymentMethodId): JsonResponse
    {
        /** @var P2PPaymentMethod|null $method */
        $method = P2PPaymentMethod::query()
            ->where('id', $paymentMethodId)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($method === null) {
            return response()->json(['message' => 'Payment method not found.'], 404);
        }

        $method->delete();

        return response()->json(['message' => 'Payment method removed successfully.']);
    }

    public function myAds(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->p2pService->myAds($request->user())]);
    }

    public function createAd(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'type' => ['required', 'string', 'in:buy,sell'],
            'asset' => ['required', 'string', 'max:16'],
            'fiat_currency' => ['required', 'string', 'max:16'],
            'price' => ['required', 'numeric', 'gt:0'],
            'min_limit' => ['required', 'numeric', 'gt:0'],
            'max_limit' => ['required', 'numeric', 'gt:0', 'gte:min_limit'],
            'available_amount' => ['required', 'numeric', 'gt:0'],
            'payment_methods' => ['required', 'array', 'min:1'],
            'payment_methods.*' => ['required', 'string', 'max:64'],
            'region' => ['nullable', 'string', 'max:32'],
            'payment_time_limit_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'terms_of_trade' => ['nullable', 'string', 'max:2000'],
            'requires_kyc' => ['nullable', 'boolean'],
        ]);

        try {
            $ad = $this->p2pService->createAd($request->user(), $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $ad], 201);
    }

    public function updateAdStatus(Request $request, int $adId): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'string', 'in:active,paused,closed'],
        ]);

        try {
            $ad = $this->p2pService->updateAdStatus($request->user(), $adId, $payload['status']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $ad]);
    }

    public function openTrade(Request $request, int $adId): JsonResponse
    {
        $payload = $request->validate([
            'fiat_amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'string', 'max:64'],
        ]);

        try {
            $trade = $this->p2pService->openTrade($request->user(), $adId, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $trade], 201);
    }

    public function myTrades(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->p2pService->myTrades($request->user(), $request->query('status')),
        ]);
    }

    public function showTrade(Request $request, string $tradeUuid): JsonResponse
    {
        try {
            $trade = $this->p2pService->showTradeForUser($request->user(), $tradeUuid);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['data' => $trade]);
    }

    public function uploadPaymentProof(Request $request, string $tradeUuid): JsonResponse
    {
        $payload = $request->validate([
            'proof' => ['required', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
        ]);

        try {
            $trade = $this->p2pService->uploadPaymentProof($request->user(), $tradeUuid, $payload['proof']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->p2pService->showTradeForUser($request->user(), $trade->trade_uuid)]);
    }

    public function paymentProof(Request $request, string $tradeUuid)
    {
        try {
            return $this->p2pService->paymentProofResponse($request->user(), $tradeUuid);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }
    }
    public function markPaymentSent(Request $request, string $tradeUuid): JsonResponse
    {
        $payload = $request->validate([
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'attachment' => ['nullable', 'string', 'max:2048'],
        ]);

        try {
            $trade = $this->p2pService->markPaymentSent($request->user(), $tradeUuid, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $trade]);
    }

    public function release(Request $request, string $tradeUuid): JsonResponse
    {
        try {
            $trade = $this->p2pService->releaseTrade($request->user(), $tradeUuid);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $trade]);
    }

    public function cancel(Request $request, string $tradeUuid): JsonResponse
    {
        try {
            $trade = $this->p2pService->cancelTrade($request->user(), $tradeUuid);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $trade]);
    }

    public function messages(Request $request, string $tradeUuid): JsonResponse
    {
        try {
            $messages = $this->p2pService->messages($request->user(), $tradeUuid);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['data' => $messages]);
    }

    public function sendMessage(Request $request, string $tradeUuid): JsonResponse
    {
        $payload = $request->validate([
            'message' => ['nullable', 'string', 'max:4000'],
            'attachment' => ['nullable', 'string', 'max:2048'],
        ]);

        if (($payload['message'] ?? null) === null && ($payload['attachment'] ?? null) === null) {
            return response()->json(['message' => 'A message body or attachment is required.'], 422);
        }

        try {
            $message = $this->p2pService->sendMessage($request->user(), $tradeUuid, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $message], 201);
    }

    public function openDispute(Request $request, string $tradeUuid): JsonResponse
    {
        $payload = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'evidence' => ['nullable', 'array'],
        ]);

        try {
            $dispute = $this->p2pService->openDispute($request->user(), $tradeUuid, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $dispute], 201);
    }

    public function reviewQueue(): JsonResponse
    {
        return response()->json(['data' => $this->p2pService->reviewQueue()]);
    }

    public function resolveDispute(Request $request, int $disputeId): JsonResponse
    {
        $payload = $request->validate([
            'action' => ['required', 'string', 'in:release_buyer,return_seller,request_more_info'],
            'resolution' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $dispute = $this->p2pService->resolveDispute($request->user(), $disputeId, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $dispute]);
    }

    public function rateTrade(Request $request, string $tradeUuid): JsonResponse
    {
        $payload = $request->validate([
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:280'],
        ]);

        try {
            $rating = $this->p2pService->rateTrade($request->user(), $tradeUuid, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $rating], 201);
    }

    private function validatePaymentMethodPayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return Validator::make($request->all(), [
            'method_type' => [$required, 'string', 'max:64'],
            'fiat_currency' => [$required, 'string', 'max:16'],
            'display_name' => [$required, 'string', 'max:120'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_code' => ['nullable', 'string', 'max:64'],
            'account_name' => [$required, 'string', 'max:120'],
            'account_number' => [$required, 'string', 'max:64'],
            'payment_note' => ['nullable', 'string', 'max:280'],
            'is_default' => ['nullable', 'boolean'],
            'is_enabled' => ['nullable', 'boolean'],
        ])->validate();
    }

    private function normalizePaymentMethodPayload(array $payload): array
    {
        $normalized = [];

        foreach (['method_type', 'display_name', 'bank_name', 'bank_code', 'account_name', 'account_number', 'payment_note'] as $field) {
            if (array_key_exists($field, $payload)) {
                $normalized[$field] = trim((string) $payload[$field]);
            }
        }

        if (array_key_exists('fiat_currency', $payload)) {
            $normalized['fiat_currency'] = strtoupper(trim((string) $payload['fiat_currency']));
        }

        if (array_key_exists('is_default', $payload)) {
            $normalized['is_default'] = (bool) $payload['is_default'];
        }

        if (array_key_exists('is_enabled', $payload)) {
            $normalized['is_enabled'] = (bool) $payload['is_enabled'];
        }

        return $normalized;
    }

    private function transformPaymentMethod(P2PPaymentMethod $method): array
    {
        $accountNumber = (string) ($method->account_number ?? '');
        $masked = strlen($accountNumber) <= 4
            ? str_repeat('*', strlen($accountNumber))
            : str_repeat('*', max(strlen($accountNumber) - 4, 0)) . substr($accountNumber, -4);

        return [
            'id' => $method->id,
            'method_type' => $method->method_type,
            'display_name' => $method->display_name,
            'fiat_currency' => $method->fiat_currency,
            'bank_name' => $method->bank_name,
            'bank_code' => $method->bank_code,
            'account_name' => $method->account_name,
            'account_number' => null,
            'masked_account_number' => $masked,
            'payment_note' => $method->payment_note,
            'is_default' => (bool) $method->is_default,
            'status' => (bool) $method->is_enabled ? 'active' : 'disabled',
        ];
    }
}
