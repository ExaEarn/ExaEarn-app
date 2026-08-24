<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Cards\CardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ExaCardWebhookController extends Controller
{
    public function __construct(private readonly CardService $cards)
    {
    }

    public function handle(Request $request, string $provider): JsonResponse
    {
        try {
            $event = $this->cards->handleWebhook(strtolower($provider), $request->getContent(), $request->headers->all());
            return response()->json(['data' => ['event_uuid' => $event->event_uuid, 'status' => $event->status]]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }
    }
}
