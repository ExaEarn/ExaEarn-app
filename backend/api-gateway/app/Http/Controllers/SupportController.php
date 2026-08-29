<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Services\SupportLiveChatService;
use App\Services\SupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SupportController extends Controller
{
    public function meta(SupportService $support, SupportLiveChatService $chat): JsonResponse
    {
        return response()->json(['data' => [
            'categories' => $support->categories(),
            'live_chat' => $chat->availability('WEB'),
            'attachments' => ['max_size_mb' => 10, 'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'text/plain']],
        ]]);
    }

    public function store(Request $request, SupportService $support): JsonResponse
    {
        $payload = $request->validate([
            'category' => ['required', 'string', 'max:80'],
            'subcategory' => ['nullable', 'string', 'max:120'],
            'priority_hint' => ['nullable', 'string', 'max:20'],
            'subject' => ['required', 'string', 'min:4', 'max:180'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'source' => ['nullable', 'string', 'max:40'],
            'product' => ['nullable', 'string', 'max:80'],
            'related_entity_type' => ['nullable', 'string', 'max:120'],
            'related_entity_id' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');
        $existing = $idempotencyKey
            ? SupportTicket::query()->where('user_id', $request->user()->id)->where('idempotency_key', $idempotencyKey)->exists()
            : false;
        $ticket = $support->createTicket($request->user(), $payload, $idempotencyKey);

        return response()->json(['data' => $ticket], $existing ? 200 : 201);
    }

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->latest('last_activity_at')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json(['data' => $tickets]);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless((int) $ticket->user_id === (int) $request->user()->id, 404);

        return response()->json(['data' => $ticket->load([
            'messages' => fn ($q) => $q->where('visibility', 'PUBLIC')->oldest(),
            'attachments',
        ])]);
    }

    public function message(Request $request, SupportTicket $ticket, SupportService $support): JsonResponse
    {
        $payload = $request->validate(['body' => ['required', 'string', 'min:1', 'max:5000']]);

        try {
            $message = $support->addUserMessage($request->user(), $ticket, $payload['body'], $request->header('Idempotency-Key'));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $message], 201);
    }

    public function attach(Request $request, SupportTicket $ticket, SupportService $support): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:10240']]);

        try {
            $attachment = $support->attach($request->user(), $ticket, $request->file('file'));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $attachment], 201);
    }

    public function close(Request $request, SupportTicket $ticket, SupportService $support): JsonResponse
    {
        abort_unless((int) $ticket->user_id === (int) $request->user()->id, 404);

        try {
            return response()->json(['data' => $support->transition($ticket, 'CLOSED', 'USER', $request->user()->id)]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function reopen(Request $request, SupportTicket $ticket, SupportService $support): JsonResponse
    {
        abort_unless((int) $ticket->user_id === (int) $request->user()->id, 404);

        try {
            return response()->json(['data' => $support->transition($ticket, 'REOPENED', 'USER', $request->user()->id)]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function kb(Request $request, SupportService $support): JsonResponse
    {
        return response()->json(['data' => $support->searchKnowledgeBase((string) $request->query('q', ''), (string) $request->query('locale', 'en'))]);
    }
}
