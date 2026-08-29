<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\SupportAgentProfile;
use App\Models\SupportCannedResponse;
use App\Models\SupportChat;
use App\Models\SupportQueue;
use App\Services\AdminAuditService;
use App\Services\SupportLiveChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SupportLiveChatOperationsController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit)
    {
    }

    public function settings(Request $request, SupportLiveChatService $chat): JsonResponse
    {
        if ($request->isMethod('put')) {
            $payload = $request->validate([
                'live_chat_enabled' => ['nullable', 'boolean'],
                'public_chat_enabled' => ['nullable', 'boolean'],
                'mobile_chat_enabled' => ['nullable', 'boolean'],
                'web_chat_enabled' => ['nullable', 'boolean'],
                'operating_hours_enabled' => ['nullable', 'boolean'],
                'timezone' => ['nullable', 'string'],
                'operating_hours' => ['nullable', 'array'],
                'holiday_dates' => ['nullable', 'array'],
                'default_queue' => ['nullable', 'string'],
                'max_waiting_conversations' => ['nullable', 'integer', 'min:1', 'max:1000'],
                'max_concurrent_chats_per_agent' => ['nullable', 'integer', 'min:1', 'max:25'],
                'offline_ticket_fallback' => ['nullable', 'boolean'],
                'auto_assignment_enabled' => ['nullable', 'boolean'],
                'user_wait_timeout' => ['nullable', 'integer', 'min:60'],
                'chat_inactivity_timeout' => ['nullable', 'integer', 'min:60'],
                'maintenance_mode' => ['nullable', 'boolean'],
                'maintenance_message' => ['nullable', 'string'],
            ]);
            $admin = $request->user() instanceof Admin ? $request->user() : null;
            $settings = $chat->updateSettings($payload, $admin);
            if ($admin) {
                $this->audit->log($admin, 'support.live_chat.settings.update', ['keys' => array_keys($payload)], $request);
            }
            return response()->json(['data' => $settings]);
        }

        return response()->json(['data' => $chat->settings()]);
    }

    public function agents(Request $request, SupportLiveChatService $chat): JsonResponse
    {
        if ($request->isMethod('post')) {
            $payload = $request->validate(['admin_id' => ['required', 'integer', 'exists:admins,id'], 'support_enabled' => ['nullable', 'boolean'], 'queue' => ['nullable', 'string'], 'max_concurrent_chats' => ['nullable', 'integer'], 'status' => ['nullable', 'string'], 'skills' => ['nullable', 'array']]);
            $admin = Admin::query()->findOrFail($payload['admin_id']);
            return response()->json(['data' => $chat->heartbeat($admin, $payload)]);
        }

        return response()->json(['data' => SupportAgentProfile::query()->with('admin:id,name,email')->latest('last_heartbeat_at')->paginate((int) $request->query('per_page', 30))]);
    }

    public function heartbeat(Request $request, SupportLiveChatService $chat): JsonResponse
    {
        $payload = $request->validate(['status' => ['nullable', 'string'], 'queue' => ['nullable', 'string'], 'max_concurrent_chats' => ['nullable', 'integer'], 'skills' => ['nullable', 'array']]);
        $admin = $request->user() instanceof Admin ? $request->user() : Admin::query()->firstOrFail();

        return response()->json(['data' => $chat->heartbeat($admin, $payload)]);
    }

    public function conversations(Request $request): JsonResponse
    {
        return response()->json(['data' => SupportChat::query()->with('user:id,name,email')->latest('last_activity_at')->paginate((int) $request->query('per_page', 30))]);
    }

    public function replay(Request $request, SupportChat $conversation, SupportLiveChatService $chat): JsonResponse
    {
        $admin = $request->user() instanceof Admin ? $request->user() : Admin::query()->firstOrFail();
        return response()->json(['data' => $chat->replay($admin, $conversation, (int) $request->query('after_sequence', 0))]);
    }

    public function message(Request $request, SupportChat $conversation, SupportLiveChatService $chat): JsonResponse
    {
        $payload = $request->validate(['body' => ['required', 'string'], 'internal' => ['nullable', 'boolean']]);
        $admin = $request->user() instanceof Admin ? $request->user() : Admin::query()->firstOrFail();
        return response()->json(['data' => $chat->sendAgentMessage($admin, $conversation, $payload['body'], (bool) ($payload['internal'] ?? false), $request->header('Idempotency-Key'))], 201);
    }

    public function assign(Request $request, SupportChat $conversation, SupportLiveChatService $chat): JsonResponse
    {
        $payload = $request->validate(['agent_id' => ['required', 'integer', 'exists:admins,id']]);
        $actor = $request->user() instanceof Admin ? $request->user() : Admin::query()->firstOrFail();
        try {
            return response()->json(['data' => $chat->manualAssign($conversation, Admin::query()->findOrFail($payload['agent_id']), $actor)]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function transfer(Request $request, SupportChat $conversation, SupportLiveChatService $chat): JsonResponse
    {
        $payload = $request->validate(['agent_id' => ['nullable', 'integer', 'exists:admins,id'], 'queue' => ['nullable', 'string']]);
        $actor = $request->user() instanceof Admin ? $request->user() : Admin::query()->firstOrFail();
        return response()->json(['data' => $chat->transfer($conversation, isset($payload['agent_id']) ? Admin::query()->find($payload['agent_id']) : null, $payload['queue'] ?? null, $actor)]);
    }

    public function end(Request $request, SupportChat $conversation, SupportLiveChatService $chat): JsonResponse
    {
        $actor = $request->user() instanceof Admin ? $request->user() : Admin::query()->firstOrFail();
        return response()->json(['data' => $chat->end($conversation, $actor)]);
    }

    public function convert(Request $request, SupportChat $conversation, SupportLiveChatService $chat): JsonResponse
    {
        $payload = $request->validate(['subject' => ['nullable', 'string']]);
        $actor = $request->user() instanceof Admin ? $request->user() : Admin::query()->firstOrFail();
        return response()->json(['data' => $chat->convertToTicket($conversation, $actor, $payload['subject'] ?? 'Support chat follow-up')], 201);
    }

    public function canned(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $payload = $request->validate(['category' => ['nullable', 'string'], 'title' => ['required', 'string'], 'body' => ['required', 'string'], 'status' => ['nullable', 'string']]);
            return response()->json(['data' => SupportCannedResponse::create($payload + ['created_by_admin_id' => $request->user()?->id])], 201);
        }

        return response()->json(['data' => SupportCannedResponse::query()->where('status', 'ACTIVE')->paginate((int) $request->query('per_page', 50))]);
    }

    public function health(SupportLiveChatService $chat): JsonResponse
    {
        return response()->json(['data' => $chat->health()]);
    }
}
