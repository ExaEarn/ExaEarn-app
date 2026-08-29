<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationEventDefinition;
use App\Models\NotificationLog;
use App\Models\NotificationProviderHealth;
use App\Models\NotificationTemplate;
use App\Services\AdminAuditService;
use App\Services\NotificationTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationOperationsController extends Controller
{
    public function __construct(
        private readonly NotificationTemplateService $templates,
        private readonly AdminAuditService $audit,
    ) {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['data' => [
            'notifications' => [
                'total' => Notification::query()->count(),
                'active' => Notification::query()->whereNull('archived_at')->count(),
                'failed' => Notification::query()->where('status', 'failed')->count(),
                'archived' => Notification::query()->whereNotNull('archived_at')->count(),
            ],
            'deliveries' => [
                'queued' => NotificationLog::query()->where('status', 'QUEUED')->count(),
                'delivered' => NotificationLog::query()->whereIn('status', ['DELIVERED', 'SENT'])->count(),
                'failed' => NotificationLog::query()->whereIn('status', ['FAILED', 'DEAD_LETTERED'])->count(),
                'suppressed' => NotificationLog::query()->where('status', 'SUPPRESSED')->count(),
            ],
            'templates' => NotificationTemplate::query()->where('status', 'ACTIVE')->count(),
            'events' => NotificationEventDefinition::query()->where('status', 'ACTIVE')->count(),
            'providers' => NotificationProviderHealth::query()->orderBy('channel')->get(),
        ]]);
    }

    public function events(Request $request): JsonResponse
    {
        return response()->json(['data' => NotificationEventDefinition::query()
            ->orderBy('product')
            ->orderBy('event_key')
            ->paginate((int) $request->query('per_page', 100))]);
    }

    public function deliveries(Request $request): JsonResponse
    {
        return response()->json(['data' => NotificationLog::query()
            ->with('notification:id,user_id,event_key,title,status,created_at')
            ->latest()
            ->paginate((int) $request->query('per_page', 50))]);
    }

    public function dlq(Request $request): JsonResponse
    {
        return response()->json(['data' => NotificationLog::query()
            ->whereIn('status', ['FAILED', 'DEAD_LETTERED'])
            ->latest()
            ->paginate((int) $request->query('per_page', 50))]);
    }

    public function retry(NotificationLog $delivery, Request $request): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'max:240']]);
        $delivery->update([
            'status' => 'RETRYING',
            'queued_at' => now(),
            'safe_error' => null,
        ]);
        $this->audit->log($request->user(), 'admin.notification.delivery.retry', ['delivery_id' => $delivery->id, 'reason' => $payload['reason']], $request);

        return response()->json(['data' => $delivery->fresh()]);
    }

    public function providers(): JsonResponse
    {
        $configured = [
            ['provider' => 'in_app', 'channel' => 'in_app', 'status' => 'HEALTHY', 'metadata' => ['configuration' => 'built_in']],
            ['provider' => 'email', 'channel' => 'email', 'status' => config('mail.mailers.smtp.host') ? 'UNKNOWN' : 'UNKNOWN', 'metadata' => ['configuration' => 'OPERATIONAL_SETUP_REQUIRED']],
            ['provider' => 'push', 'channel' => 'push', 'status' => config('services.fcm.key') ? 'UNKNOWN' : 'UNKNOWN', 'metadata' => ['configuration' => 'OPERATIONAL_SETUP_REQUIRED']],
        ];

        foreach ($configured as $row) {
            NotificationProviderHealth::query()->firstOrCreate(['provider' => $row['provider']], $row);
        }

        return response()->json(['data' => NotificationProviderHealth::query()->orderBy('channel')->get()]);
    }

    public function templates(Request $request): JsonResponse
    {
        return response()->json(['data' => NotificationTemplate::query()
            ->orderBy('template_key')
            ->orderBy('channel')
            ->orderBy('locale')
            ->paginate((int) $request->query('per_page', 100))]);
    }

    public function preview(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'event_key' => ['required', 'string', 'max:160'],
            'channel' => ['required', 'string', 'in:in_app,email,push'],
            'locale' => ['nullable', 'string', 'max:12'],
            'variables' => ['nullable', 'array'],
        ]);

        return response()->json(['data' => $this->templates->preview(
            $payload['event_key'],
            $payload['channel'],
            $payload['locale'] ?? 'en',
            $payload['variables'] ?? [],
        )]);
    }
}
