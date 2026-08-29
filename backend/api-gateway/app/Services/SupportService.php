<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\KbArticle;
use App\Models\KbArticleVersion;
use App\Models\KbCategory;
use App\Models\SupportEscalation;
use App\Models\SupportQueue;
use App\Models\SupportSlaPolicy;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketEvent;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SupportService
{
    private const STATUSES = ['OPEN', 'TRIAGED', 'ASSIGNED', 'IN_PROGRESS', 'WAITING_FOR_USER', 'WAITING_FOR_INTERNAL', 'ESCALATED', 'RESOLVED', 'CLOSED', 'REOPENED', 'CANCELLED'];
    private const PRIORITIES = ['LOW', 'NORMAL', 'HIGH', 'URGENT', 'CRITICAL'];
    private const USER_CATEGORIES = ['Account', 'KYC / Verification', 'Security', 'Deposit', 'Withdrawal', 'Spot', 'Futures', 'Convert', 'P2P', 'Staking / Earn', 'Gift Cards', 'ExaCard', 'ExaPay', 'ExaAI', 'Copy Trading', 'NFT', 'Crowdfunding', 'ExaSkills', 'AgriTech', 'Affiliate / Rewards', 'Developer API', 'Institutional', 'Market Maker', 'OTC', 'Technical Issue', 'Other'];

    private array $transitions = [
        'OPEN' => ['TRIAGED', 'ASSIGNED', 'IN_PROGRESS', 'WAITING_FOR_USER', 'ESCALATED', 'RESOLVED', 'CANCELLED'],
        'TRIAGED' => ['ASSIGNED', 'IN_PROGRESS', 'WAITING_FOR_USER', 'ESCALATED', 'RESOLVED', 'CANCELLED'],
        'ASSIGNED' => ['IN_PROGRESS', 'WAITING_FOR_USER', 'WAITING_FOR_INTERNAL', 'ESCALATED', 'RESOLVED', 'CANCELLED'],
        'IN_PROGRESS' => ['WAITING_FOR_USER', 'WAITING_FOR_INTERNAL', 'ESCALATED', 'RESOLVED', 'CANCELLED'],
        'WAITING_FOR_USER' => ['IN_PROGRESS', 'ESCALATED', 'RESOLVED', 'CANCELLED'],
        'WAITING_FOR_INTERNAL' => ['IN_PROGRESS', 'ESCALATED', 'RESOLVED', 'CANCELLED'],
        'ESCALATED' => ['IN_PROGRESS', 'WAITING_FOR_USER', 'WAITING_FOR_INTERNAL', 'RESOLVED', 'CANCELLED'],
        'RESOLVED' => ['CLOSED', 'REOPENED'],
        'CLOSED' => ['REOPENED'],
        'REOPENED' => ['ASSIGNED', 'IN_PROGRESS', 'WAITING_FOR_USER', 'ESCALATED', 'RESOLVED'],
        'CANCELLED' => ['REOPENED'],
    ];

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function categories(): array
    {
        return self::USER_CATEGORIES;
    }

    public function createTicket(User $user, array $payload, ?string $idempotencyKey = null): SupportTicket
    {
        return DB::transaction(function () use ($idempotencyKey, $payload, $user): SupportTicket {
            if ($idempotencyKey) {
                $existing = SupportTicket::query()->where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing) {
                    return $existing;
                }
            }

            $priority = $this->triagePriority((string) $payload['category'], (string) ($payload['product'] ?? ''), (string) ($payload['priority_hint'] ?? 'NORMAL'));
            $queue = $this->routeQueue((string) $payload['category'], (string) ($payload['product'] ?? ''));
            $sla = $this->slaPolicy($priority, $queue->code);

            $ticket = SupportTicket::create([
                'ticket_number' => $this->ticketNumber(),
                'user_id' => $user->id,
                'category' => $payload['category'],
                'subcategory' => $payload['subcategory'] ?? null,
                'priority' => $priority,
                'severity' => $priority,
                'status' => 'OPEN',
                'subject' => $payload['subject'],
                'description' => $payload['description'],
                'source' => strtoupper((string) ($payload['source'] ?? 'WEB')),
                'product' => $payload['product'] ?? null,
                'related_entity_type' => $payload['related_entity_type'] ?? null,
                'related_entity_id' => isset($payload['related_entity_id']) ? (string) $payload['related_entity_id'] : null,
                'assigned_team_id' => $queue->id,
                'sla_policy_id' => $sla->id,
                'first_response_due_at' => now()->addMinutes((int) $sla->first_response_minutes),
                'resolution_due_at' => now()->addMinutes((int) $sla->resolution_minutes),
                'last_activity_at' => now(),
                'metadata' => ['intake' => $payload['metadata'] ?? []],
                'idempotency_key' => $idempotencyKey,
            ]);

            $ticket->messages()->create([
                'sender_type' => 'USER',
                'sender_id' => $user->id,
                'message_type' => 'MESSAGE',
                'visibility' => 'PUBLIC',
                'body' => $payload['description'],
            ]);
            $this->event($ticket, 'ticket.created', 'USER', $user->id, ['queue' => $queue->code, 'priority' => $priority]);
            $this->notify($user, 'support.ticket.created', $ticket);

            return $ticket->fresh(['messages', 'attachments']);
        });
    }

    public function addUserMessage(User $user, SupportTicket $ticket, string $body, ?string $idempotencyKey = null): SupportTicketMessage
    {
        $this->assertOwner($user, $ticket);
        if (in_array($ticket->status, ['CLOSED', 'CANCELLED'], true)) {
            throw new RuntimeException('Closed tickets must be reopened before adding a reply.');
        }

        return DB::transaction(function () use ($body, $idempotencyKey, $ticket, $user): SupportTicketMessage {
            if ($idempotencyKey) {
                $existing = SupportTicketMessage::query()->where('ticket_id', $ticket->id)->whereJsonContains('metadata->idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $message = $ticket->messages()->create([
                'sender_type' => 'USER',
                'sender_id' => $user->id,
                'message_type' => 'MESSAGE',
                'visibility' => 'PUBLIC',
                'body' => $body,
                'metadata' => ['idempotency_key' => $idempotencyKey],
            ]);
            $ticket->update(['status' => in_array($ticket->status, ['RESOLVED'], true) ? 'REOPENED' : $ticket->status, 'last_activity_at' => now(), 'reopened_at' => $ticket->status === 'RESOLVED' ? now() : $ticket->reopened_at]);
            $this->event($ticket, 'message.user_created', 'USER', $user->id);

            return $message;
        });
    }

    public function addAgentMessage(Admin $admin, SupportTicket $ticket, string $body, bool $internal = false): SupportTicketMessage
    {
        return DB::transaction(function () use ($admin, $body, $internal, $ticket): SupportTicketMessage {
            $message = $ticket->messages()->create([
                'sender_type' => 'AGENT',
                'sender_id' => $admin->id,
                'message_type' => $internal ? 'INTERNAL_NOTE' : 'MESSAGE',
                'visibility' => $internal ? 'INTERNAL' : 'PUBLIC',
                'body' => $body,
            ]);
            $updates = ['last_activity_at' => now()];
            if (!$internal && !$ticket->first_responded_at) {
                $updates['first_responded_at'] = now();
            }
            if (!$internal && in_array($ticket->status, ['OPEN', 'TRIAGED', 'ASSIGNED'], true)) {
                $updates['status'] = 'IN_PROGRESS';
            }
            $ticket->update($updates);
            $this->event($ticket, $internal ? 'message.internal_note' : 'message.agent_replied', 'ADMIN', $admin->id);
            if (!$internal) {
                $this->notify($ticket->user, 'support.ticket.agent_replied', $ticket);
            }

            return $message;
        });
    }

    public function transition(SupportTicket $ticket, string $status, string $actorType, ?int $actorId, array $payload = []): SupportTicket
    {
        $status = strtoupper($status);
        if (!in_array($status, self::STATUSES, true) || !in_array($status, $this->transitions[$ticket->status] ?? [], true)) {
            throw new RuntimeException("Invalid support ticket transition {$ticket->status} -> {$status}.");
        }

        return DB::transaction(function () use ($actorId, $actorType, $payload, $status, $ticket): SupportTicket {
            $updates = ['status' => $status, 'last_activity_at' => now()];
            if ($status === 'RESOLVED') {
                $updates['resolved_at'] = now();
                $updates['resolution_code'] = $payload['resolution_code'] ?? 'NO_ACTION_REQUIRED';
            }
            if ($status === 'CLOSED') {
                $updates['closed_at'] = now();
            }
            if ($status === 'REOPENED') {
                $updates['reopened_at'] = now();
            }
            $ticket->update($updates);
            $this->event($ticket, 'ticket.status_changed', $actorType, $actorId, ['status' => $status] + $payload);
            if (in_array($status, ['RESOLVED', 'CLOSED', 'REOPENED', 'ESCALATED'], true)) {
                $this->notify($ticket->user, 'support.ticket.'.strtolower($status), $ticket);
            }

            return $ticket->fresh();
        });
    }

    public function assign(SupportTicket $ticket, ?Admin $agent, ?SupportQueue $queue, Admin $actor): SupportTicket
    {
        return DB::transaction(function () use ($actor, $agent, $queue, $ticket): SupportTicket {
            $ticket->update([
                'assigned_agent_id' => $agent?->id,
                'assigned_team_id' => $queue?->id ?? $ticket->assigned_team_id,
                'status' => in_array($ticket->status, ['OPEN', 'TRIAGED'], true) ? 'ASSIGNED' : $ticket->status,
                'last_activity_at' => now(),
            ]);
            $this->event($ticket, 'ticket.assigned', 'ADMIN', $actor->id, ['agent_id' => $agent?->id, 'queue_id' => $queue?->id]);
            $this->notify($ticket->user, 'support.ticket.assigned', $ticket);

            return $ticket->fresh();
        });
    }

    public function escalate(SupportTicket $ticket, string $toQueue, string $reason, Admin $actor): SupportTicket
    {
        return DB::transaction(function () use ($actor, $reason, $ticket, $toQueue): SupportTicket {
            SupportEscalation::create([
                'ticket_id' => $ticket->id,
                'from_queue' => $ticket->assignedTeam?->code ?? null,
                'to_queue' => $toQueue,
                'actor_admin_id' => $actor->id,
                'reason' => $reason,
            ]);
            $ticket->update(['status' => 'ESCALATED', 'priority' => $ticket->priority === 'CRITICAL' ? 'CRITICAL' : 'URGENT', 'last_activity_at' => now()]);
            $this->event($ticket, 'ticket.escalated', 'ADMIN', $actor->id, ['to_queue' => $toQueue, 'reason' => $reason]);
            $this->notify($ticket->user, 'support.ticket.escalated', $ticket);

            return $ticket->fresh();
        });
    }

    public function attach(User|Admin $actor, SupportTicket $ticket, UploadedFile $file): SupportTicketAttachment
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'text/plain'];
        if (!in_array($file->getMimeType(), $allowed, true) || $file->getSize() > 10 * 1024 * 1024) {
            throw new RuntimeException('Unsupported attachment type or size.');
        }
        if ($actor instanceof User) {
            $this->assertOwner($actor, $ticket);
        }

        $path = $file->store('support/'.date('Y/m'), 'local');

        return $ticket->attachments()->create([
            'uploaded_by_type' => $actor instanceof Admin ? 'ADMIN' : 'USER',
            'uploaded_by_id' => $actor->id,
            'original_filename' => $file->getClientOriginalName(),
            'safe_mime' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'storage_disk' => 'local',
            'storage_path' => $path,
            'scan_status' => 'PENDING',
        ]);
    }

    public function evaluateSla(): array
    {
        $now = now();
        $atRisk = SupportTicket::query()->whereNotIn('status', ['RESOLVED', 'CLOSED', 'CANCELLED'])->where('resolution_due_at', '<=', $now->copy()->addMinutes(30))->count();
        $breached = SupportTicket::query()->whereNotIn('status', ['RESOLVED', 'CLOSED', 'CANCELLED'])->where('resolution_due_at', '<', $now)->count();

        return ['status' => $breached > 0 ? 'BREACHED' : ($atRisk > 0 ? 'AT_RISK' : 'ON_TRACK'), 'at_risk' => $atRisk, 'breached' => $breached];
    }

    public function searchKnowledgeBase(string $query = '', string $locale = 'en'): LengthAwarePaginator
    {
        return KbArticle::query()
            ->with(['category', 'versions' => fn ($q) => $q->latest('version')->limit(1)])
            ->where('status', 'PUBLISHED')
            ->where('locale', $locale)
            ->when($query !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('title', 'like', "%{$query}%")->orWhere('summary', 'like', "%{$query}%")->orWhereHas('versions', fn ($v) => $v->where('body', 'like', "%{$query}%"))))
            ->latest('published_at')
            ->paginate(20);
    }

    public function publishArticle(array $payload, ?Admin $admin = null): KbArticle
    {
        return DB::transaction(function () use ($admin, $payload): KbArticle {
            $category = KbCategory::query()->firstOrCreate(['slug' => Str::slug($payload['category'] ?? 'general')], ['name' => $payload['category'] ?? 'General']);
            $article = KbArticle::query()->updateOrCreate(
                ['slug' => Str::slug($payload['slug'] ?? $payload['title']), 'locale' => $payload['locale'] ?? 'en'],
                ['category_id' => $category->id, 'title' => $payload['title'], 'summary' => $payload['summary'] ?? null, 'status' => $payload['status'] ?? 'DRAFT']
            );
            $version = $article->wasRecentlyCreated ? 1 : ((int) $article->current_version) + 1;
            KbArticleVersion::create(['article_id' => $article->id, 'version' => $version, 'body' => $payload['body'], 'keywords' => $payload['keywords'] ?? [], 'created_by_admin_id' => $admin?->id]);
            $article->update(['current_version' => $version, 'published_at' => ($payload['status'] ?? 'DRAFT') === 'PUBLISHED' ? now() : $article->published_at]);

            return $article->fresh(['category', 'versions']);
        });
    }

    private function assertOwner(User $user, SupportTicket $ticket): void
    {
        if ((int) $ticket->user_id !== (int) $user->id) {
            abort(404);
        }
    }

    private function routeQueue(string $category, string $product): SupportQueue
    {
        $code = match (strtoupper($product ?: $category)) {
            'P2P' => 'P2P',
            'EXACARD' => 'CARDS',
            'EXAPAY', 'DEPOSIT', 'WITHDRAWAL' => 'PAYMENTS',
            'SECURITY' => 'SECURITY',
            'KYC / VERIFICATION', 'COMPLIANCE' => 'COMPLIANCE',
            'DEVELOPER API' => 'DEVELOPER',
            'INSTITUTIONAL', 'MARKET MAKER', 'OTC' => 'INSTITUTIONAL',
            default => 'GENERAL',
        };

        return SupportQueue::query()->firstOrCreate(['code' => $code], ['name' => Str::headline(strtolower($code)), 'active' => true]);
    }

    private function slaPolicy(string $priority, string $queue): SupportSlaPolicy
    {
        $minutes = match ($priority) {
            'CRITICAL' => [15, 240],
            'URGENT' => [30, 480],
            'HIGH' => [120, 1440],
            'LOW' => [720, 4320],
            default => [240, 2880],
        };

        return SupportSlaPolicy::query()->firstOrCreate(
            ['code' => "default-{$priority}"],
            ['name' => "Default {$priority}", 'priority' => $priority, 'queue_code' => $queue, 'first_response_minutes' => $minutes[0], 'resolution_minutes' => $minutes[1], 'active' => true]
        );
    }

    private function triagePriority(string $category, string $product, string $hint): string
    {
        $hint = strtoupper($hint);
        $priority = in_array($hint, self::PRIORITIES, true) ? $hint : 'NORMAL';
        if (in_array(strtoupper($category), ['SECURITY', 'WITHDRAWAL'], true) || strtoupper($product) === 'EXACARD') {
            return match ($priority) {
                'LOW', 'NORMAL' => 'HIGH',
                'CRITICAL' => 'URGENT',
                default => $priority,
            };
        }

        return $priority === 'CRITICAL' ? 'HIGH' : $priority;
    }

    private function ticketNumber(): string
    {
        do {
            $number = 'EXA-SUP-'.strtoupper(Str::random(8));
        } while (SupportTicket::query()->where('ticket_number', $number)->exists());

        return $number;
    }

    private function event(SupportTicket $ticket, string $type, ?string $actorType, ?int $actorId, array $payload = []): void
    {
        SupportTicketEvent::create(['ticket_id' => $ticket->id, 'event_type' => $type, 'actor_type' => $actorType, 'actor_id' => $actorId, 'payload' => $payload]);
    }

    private function notify(User $user, string $eventKey, SupportTicket $ticket): void
    {
        $this->notifications->emit($user, $eventKey, [
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'deep_link' => '/support',
        ], $ticket->ticket_number, ['in_app']);
    }
}
