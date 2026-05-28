<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\P2PService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class P2PController extends Controller
{
    public function __construct(private readonly P2PService $p2pService)
    {
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
            'max_limit' => ['required', 'numeric', 'gt:0'],
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
}
