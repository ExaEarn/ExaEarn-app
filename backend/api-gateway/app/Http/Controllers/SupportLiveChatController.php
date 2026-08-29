<?php

namespace App\Http\Controllers;

use App\Models\SupportChat;
use App\Services\SupportLiveChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SupportLiveChatController extends Controller
{
    public function availability(Request $request, SupportLiveChatService $chat): JsonResponse
    {
        return response()->json(['data' => $chat->availability(strtoupper((string) $request->query('source', 'WEB')), (string) $request->query('queue', ''))]);
    }

    public function start(Request $request, SupportLiveChatService $chat): JsonResponse
    {
        $payload = $request->validate([
            'source' => ['nullable', 'string'],
            'queue' => ['nullable', 'string'],
            'topic' => ['nullable', 'string', 'max:180'],
            'priority' => ['nullable', 'string'],
            'product' => ['nullable', 'string'],
            'related_entity_type' => ['nullable', 'string'],
            'related_entity_id' => ['nullable', 'string'],
        ]);

        try {
            return response()->json(['data' => $chat->start($request->user(), $payload)], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'fallback' => 'ticket'], 422);
        }
    }

    public function messages(Request $request, SupportChat $conversation, SupportLiveChatService $chat): JsonResponse
    {
        $payload = $request->validate(['body' => ['required', 'string', 'max:4000']]);

        return response()->json(['data' => $chat->sendUserMessage($request->user(), $conversation, $payload['body'], $request->header('Idempotency-Key'))], 201);
    }

    public function replay(Request $request, SupportChat $conversation, SupportLiveChatService $chat): JsonResponse
    {
        return response()->json(['data' => $chat->replay($request->user(), $conversation, (int) $request->query('after_sequence', 0))]);
    }

    public function end(Request $request, SupportChat $conversation, SupportLiveChatService $chat): JsonResponse
    {
        abort_unless((int) $conversation->user_id === (int) $request->user()->id, 404);

        return response()->json(['data' => $chat->end($conversation, $request->user())]);
    }
}
