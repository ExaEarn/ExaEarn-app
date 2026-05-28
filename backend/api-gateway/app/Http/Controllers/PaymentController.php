<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentGatewayService $paymentGatewayService)
    {
    }

    public function initiate(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'provider' => ['nullable', 'string', 'in:flutterwave,nomba'],
            'country' => ['nullable', 'string', 'size:2'],
            'currency' => ['required', 'string', 'in:NGN,ZAR,USD'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $country = strtoupper((string) ($payload['country'] ?? 'NG'));
        $provider = $this->paymentGatewayService->resolveProvider(
            $country,
            $payload['provider'] ?? null
        );

        $intent = $this->paymentGatewayService->createIntent(
            $request->user()->id,
            $provider,
            $payload['currency'],
            (string) $payload['amount']
        );

        return response()->json([
            'message' => 'Payment intent created.',
            'data' => $intent,
            'routing' => [
                'country' => $country,
                'provider' => $provider,
            ],
        ], 201);
    }

    public function webhook(Request $request): JsonResponse
    {
        $provider = strtolower((string) ($request->route('provider') ?? $request->input('provider', '')));
        if (!in_array($provider, ['flutterwave', 'nomba'], true)) {
            return response()->json(['message' => 'Unsupported provider.'], 422);
        }

        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[strtolower($key)] = is_array($values) ? (string) ($values[0] ?? '') : (string) $values;
        }

        try {
            $intent = $this->paymentGatewayService->processWebhook(
                $provider,
                $request->all(),
                $request->getContent(),
                $headers
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Payment processed successfully.',
            'intent_id' => $intent->intent_id,
            'status' => $intent->status,
        ]);
    }
}
