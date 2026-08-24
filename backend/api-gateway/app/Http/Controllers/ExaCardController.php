<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CardFundingQuote;
use App\Services\Cards\CardRealtimeService;
use App\Services\Cards\CardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ExaCardController extends Controller
{
    public function __construct(
        private readonly CardService $cards,
        private readonly CardRealtimeService $realtime,
    )
    {
    }

    public function products(): JsonResponse
    {
        return response()->json(['data' => $this->cards->products()]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->cards->list($request->user())]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_code' => ['required', 'string', 'max:80'],
            'nickname' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $card = $this->cards->issue($request->user(), strtoupper($data['product_code']), $this->idempotencyKey($request), $data['nickname'] ?? null);
            return response()->json(['data' => $this->cards->presentCard($card)], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function show(Request $request, string $cardUuid): JsonResponse
    {
        return response()->json(['data' => $this->cards->presentCard($this->cards->userCard($request->user(), $cardUuid))]);
    }

    public function transactions(Request $request, string $cardUuid): JsonResponse
    {
        return response()->json(['data' => $this->cards->transactions($request->user(), $cardUuid)]);
    }

    public function authorizations(Request $request, string $cardUuid): JsonResponse
    {
        return response()->json(['data' => $this->cards->authorizations($request->user(), $cardUuid)]);
    }

    public function quoteFunding(Request $request, string $cardUuid): JsonResponse
    {
        $data = $request->validate([
            'source_asset' => ['required', 'string', 'max:24'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $quote = $this->cards->quoteFunding($request->user(), $cardUuid, $data['source_asset'], (string) $data['amount']);
            return response()->json(['data' => $this->presentQuote($quote)], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function fund(Request $request): JsonResponse
    {
        $data = $request->validate([
            'quote_uuid' => ['required', 'uuid'],
            'test_behavior' => ['nullable', 'in:COMPLETED,PENDING,FAILED,UNKNOWN,TIMEOUT'],
        ]);

        try {
            $context = in_array((string) config('exacard.provider_mode'), ['sandbox', 'fake'], true)
                ? ['test_behavior' => $data['test_behavior'] ?? null]
                : [];
            $funding = $this->cards->fund($request->user(), $data['quote_uuid'], $this->idempotencyKey($request), $context);
            return response()->json(['data' => $funding], $funding->status === 'COMPLETED' ? 201 : 202);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function unload(Request $request, string $cardUuid): JsonResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0']]);

        try {
            $unload = $this->cards->unload($request->user(), $cardUuid, (string) $data['amount'], $this->idempotencyKey($request));
            return response()->json(['data' => $unload], $unload->status === 'COMPLETED' ? 201 : 202);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function freeze(Request $request, string $cardUuid): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:4', 'max:500']]);
        return response()->json(['data' => $this->cards->presentCard($this->cards->updateStatus($request->user(), $cardUuid, 'FREEZE', $data['reason']))]);
    }

    public function unfreeze(Request $request, string $cardUuid): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:4', 'max:500']]);
        return response()->json(['data' => $this->cards->presentCard($this->cards->updateStatus($request->user(), $cardUuid, 'UNFREEZE', $data['reason']))]);
    }

    public function reportLostOrStolen(Request $request, string $cardUuid): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:500']]);
        return response()->json(['data' => $this->cards->presentCard($this->cards->reportLostOrStolen($request->user(), $cardUuid, $data['reason']))]);
    }

    public function terminate(Request $request, string $cardUuid): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:500']]);

        try {
            return response()->json(['data' => $this->cards->presentCard($this->cards->terminate($request->user(), $cardUuid, $data['reason']))]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function controls(Request $request, string $cardUuid): JsonResponse
    {
        $data = $request->validate([
            'online' => ['nullable', 'boolean'],
            'international' => ['nullable', 'boolean'],
            'atm' => ['nullable', 'boolean'],
        ]);
        return response()->json(['data' => $this->cards->presentCard($this->cards->updateControls($request->user(), $cardUuid, $data))]);
    }

    public function limits(Request $request, string $cardUuid): JsonResponse
    {
        $data = $request->validate([
            'daily' => ['nullable', 'numeric', 'gt:0'],
            'monthly' => ['nullable', 'numeric', 'gt:0'],
            'per_transaction' => ['nullable', 'numeric', 'gt:0'],
        ]);
        return response()->json(['data' => $this->cards->presentCard($this->cards->updateLimits($request->user(), $cardUuid, $data))]);
    }

    public function detailsToken(Request $request, string $cardUuid): JsonResponse
    {
        return response()->json(['data' => $this->cards->sensitiveDetailsToken($request->user(), $cardUuid)]);
    }

    public function realtimeReplay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'after_sequence' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $afterSequence = (int) ($data['after_sequence'] ?? 0);
        $events = $this->realtime->replay($request->user()->id, $afterSequence, (int) ($data['limit'] ?? 200));

        return response()->json(['data' => [
            'stream' => CardRealtimeService::STREAM,
            'after_sequence' => $afterSequence,
            'latest_sequence' => $this->realtime->latestSequence($request->user()->id),
            'gap_detected' => $this->realtime->hasGap($events, $afterSequence),
            'events' => $events,
            'reconcile_required' => $this->realtime->hasGap($events, $afterSequence),
        ]]);
    }

    private function idempotencyKey(Request $request): string
    {
        $key = (string) $request->header('Idempotency-Key', '');
        if ($key === '') {
            throw new RuntimeException('Idempotency-Key header is required.');
        }

        return $key;
    }

    private function presentQuote(CardFundingQuote $quote): array
    {
        return [
            'quote_uuid' => $quote->quote_uuid,
            'card_uuid' => $quote->card->card_uuid,
            'source_asset' => $quote->source_asset,
            'card_currency' => $quote->card_currency,
            'source_amount' => (string) $quote->source_amount,
            'card_amount' => (string) $quote->card_amount,
            'fx_rate' => (string) $quote->fx_rate,
            'conversion_fee' => (string) $quote->conversion_fee,
            'card_fee' => (string) $quote->card_fee,
            'provider_fee' => (string) $quote->provider_fee,
            'total_debit' => (string) $quote->total_debit,
            'expires_at' => $quote->expires_at?->toISOString(),
            'pricing_snapshot' => $quote->pricing_snapshot,
        ];
    }
}
