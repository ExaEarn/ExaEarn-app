<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\CardDispute;
use App\Models\KbArticle;
use App\Models\P2PDispute;
use App\Models\SupportQueue;
use App\Models\SupportTicket;
use App\Services\AdminAuditService;
use App\Services\SupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SupportOperationsController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit)
    {
    }

    public function overview(SupportService $support): JsonResponse
    {
        return response()->json(['data' => [
            'open_tickets' => SupportTicket::query()->whereNotIn('status', ['RESOLVED', 'CLOSED', 'CANCELLED'])->count(),
            'unassigned' => SupportTicket::query()->whereNull('assigned_agent_id')->whereNotIn('status', ['RESOLVED', 'CLOSED', 'CANCELLED'])->count(),
            'waiting_for_user' => SupportTicket::query()->where('status', 'WAITING_FOR_USER')->count(),
            'urgent' => SupportTicket::query()->whereIn('priority', ['URGENT', 'CRITICAL'])->whereNotIn('status', ['RESOLVED', 'CLOSED', 'CANCELLED'])->count(),
            'sla' => $support->evaluateSla(),
            'resolved_today' => SupportTicket::query()->whereDate('resolved_at', now()->toDateString())->count(),
            'live_chat' => ['status' => 'OFFLINE', 'backend' => 'PERSISTED_OFFLINE_INTAKE'],
        ]]);
    }

    public function tickets(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->with('user:id,name,email', 'assignedTeam:id,code,name')
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('priority'), fn ($q, $v) => $q->where('priority', $v))
            ->when($request->query('product'), fn ($q, $v) => $q->where('product', $v))
            ->latest('last_activity_at')
            ->paginate((int) $request->query('per_page', 30));

        return response()->json(['data' => $tickets]);
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        return response()->json(['data' => $ticket->load(['user:id,name,email', 'messages' => fn ($q) => $q->oldest(), 'attachments', 'events', 'assignedTeam'])]);
    }

    public function reply(Request $request, SupportTicket $ticket, SupportService $support): JsonResponse
    {
        $payload = $request->validate(['body' => ['required', 'string', 'max:5000'], 'internal' => ['nullable', 'boolean']]);
        $admin = $request->user();
        if (!$admin instanceof Admin) {
            $admin = Admin::query()->firstOrFail();
        }
        $message = $support->addAgentMessage($admin, $ticket, $payload['body'], (bool) ($payload['internal'] ?? false));
        $this->audit->log($admin, 'support.ticket.reply', ['ticket' => $ticket->ticket_number, 'internal' => (bool) ($payload['internal'] ?? false)], $request);

        return response()->json(['data' => $message], 201);
    }

    public function assign(Request $request, SupportTicket $ticket, SupportService $support): JsonResponse
    {
        $payload = $request->validate(['agent_id' => ['nullable', 'integer', 'exists:admins,id'], 'queue_code' => ['nullable', 'string']]);
        $admin = $request->user() instanceof Admin ? $request->user() : Admin::query()->firstOrFail();
        $agent = isset($payload['agent_id']) ? Admin::query()->find($payload['agent_id']) : null;
        $queue = isset($payload['queue_code']) ? SupportQueue::query()->firstOrCreate(['code' => strtoupper($payload['queue_code'])], ['name' => str($payload['queue_code'])->headline()->toString()]) : null;

        return response()->json(['data' => $support->assign($ticket, $agent, $queue, $admin)]);
    }

    public function transition(Request $request, SupportTicket $ticket, SupportService $support): JsonResponse
    {
        $payload = $request->validate(['status' => ['required', 'string'], 'resolution_code' => ['nullable', 'string'], 'reason' => ['nullable', 'string']]);
        $admin = $request->user() instanceof Admin ? $request->user() : Admin::query()->firstOrFail();

        try {
            return response()->json(['data' => $support->transition($ticket, $payload['status'], 'ADMIN', $admin->id, $payload)]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function escalate(Request $request, SupportTicket $ticket, SupportService $support): JsonResponse
    {
        $payload = $request->validate(['to_queue' => ['required', 'string'], 'reason' => ['required', 'string', 'max:500']]);
        $admin = $request->user() instanceof Admin ? $request->user() : Admin::query()->firstOrFail();

        return response()->json(['data' => $support->escalate($ticket, strtoupper($payload['to_queue']), $payload['reason'], $admin)]);
    }

    public function disputes(): JsonResponse
    {
        return response()->json(['data' => [
            'p2p' => class_exists(P2PDispute::class) ? P2PDispute::query()->latest()->limit(20)->get() : [],
            'exacard' => class_exists(CardDispute::class) ? CardDispute::query()->latest()->limit(20)->get() : [],
            'policy' => 'Support links to product disputes; product domains remain authoritative.',
        ]]);
    }

    public function knowledgeBase(Request $request, SupportService $support): JsonResponse
    {
        if ($request->isMethod('post')) {
            $payload = $request->validate(['title' => ['required', 'string'], 'slug' => ['nullable', 'string'], 'summary' => ['nullable', 'string'], 'body' => ['required', 'string'], 'category' => ['nullable', 'string'], 'locale' => ['nullable', 'string'], 'status' => ['nullable', 'string'], 'keywords' => ['nullable', 'array']]);
            return response()->json(['data' => $support->publishArticle($payload, $request->user() instanceof Admin ? $request->user() : null)], 201);
        }

        return response()->json(['data' => KbArticle::query()->with('category', 'versions')->latest()->paginate((int) $request->query('per_page', 30))]);
    }
}
