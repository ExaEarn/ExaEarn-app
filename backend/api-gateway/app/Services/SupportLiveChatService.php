<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\SupportAgentProfile;
use App\Models\SupportCannedResponse;
use App\Models\SupportChat;
use App\Models\SupportChatMessage;
use App\Models\SupportLiveChatSetting;
use App\Models\SupportQueue;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use RuntimeException;

class SupportLiveChatService
{
    public function __construct(private readonly SupportService $support, private readonly NotificationService $notifications)
    {
    }

    public function defaults(): array
    {
        return [
            'live_chat_enabled' => false,
            'public_chat_enabled' => false,
            'mobile_chat_enabled' => false,
            'web_chat_enabled' => false,
            'operating_hours_enabled' => true,
            'timezone' => 'UTC',
            'operating_hours' => ['monday' => ['09:00', '17:00'], 'tuesday' => ['09:00', '17:00'], 'wednesday' => ['09:00', '17:00'], 'thursday' => ['09:00', '17:00'], 'friday' => ['09:00', '17:00']],
            'holiday_dates' => [],
            'default_queue' => 'GENERAL',
            'max_waiting_conversations' => 25,
            'max_concurrent_chats_per_agent' => 2,
            'offline_ticket_fallback' => true,
            'auto_assignment_enabled' => true,
            'user_wait_timeout' => 900,
            'chat_inactivity_timeout' => 1800,
            'maintenance_mode' => false,
            'maintenance_message' => 'Live support is temporarily unavailable. Please create a support ticket.',
        ];
    }

    public function settings(): array
    {
        $settings = $this->defaults();
        foreach (SupportLiveChatSetting::query()->get() as $row) {
            $settings[$row->key] = $row->value['value'] ?? $row->value;
        }

        return $settings;
    }

    public function updateSettings(array $payload, ?Admin $admin = null): array
    {
        foreach ($payload as $key => $value) {
            if (!array_key_exists($key, $this->defaults())) {
                continue;
            }
            SupportLiveChatSetting::query()->updateOrCreate(['key' => $key], ['value' => ['value' => $value], 'updated_by_admin_id' => $admin?->id]);
        }

        return $this->settings();
    }

    public function availability(string $source = 'WEB', ?string $queueCode = null): array
    {
        $settings = $this->settings();
        if (!$settings['live_chat_enabled'] || !$settings['public_chat_enabled']) {
            return $this->unavailable('DISABLED', 'Live support is currently unavailable. You can submit a support ticket and we will follow up.', $settings);
        }
        if ($source === 'WEB' && !$settings['web_chat_enabled']) {
            return $this->unavailable('DISABLED', 'Web live chat is disabled. Please create a support ticket.', $settings);
        }
        if ($source === 'MOBILE' && !$settings['mobile_chat_enabled']) {
            return $this->unavailable('DISABLED', 'Mobile live chat is disabled. Please create a support ticket.', $settings);
        }
        if ($settings['maintenance_mode']) {
            return $this->unavailable('MAINTENANCE', (string) $settings['maintenance_message'], $settings);
        }
        if (!$this->withinOperatingHours($settings)) {
            return $this->unavailable('OFFLINE', 'Live support is outside operating hours. Please create a support ticket.', $settings);
        }

        $queue = $this->queue($queueCode ?: (string) $settings['default_queue']);
        $waiting = SupportChat::query()->where('queue_id', $queue->id)->where('status', 'WAITING')->count();
        if ($waiting >= (int) $settings['max_waiting_conversations']) {
            return $this->unavailable('CAPACITY_REACHED', 'The support queue is full. Please create a support ticket.', $settings);
        }
        $agents = $this->availableAgents($queue, $settings)->count();
        if ($agents < 1) {
            return ['live_chat_enabled' => true, 'status' => 'BUSY', 'queue_available' => true, 'estimated_wait' => null, 'offline_fallback' => 'ticket'];
        }

        return ['live_chat_enabled' => true, 'status' => 'ONLINE', 'queue_available' => true, 'estimated_wait' => null, 'offline_fallback' => 'ticket'];
    }

    public function start(User $user, array $payload): SupportChat
    {
        $source = strtoupper((string) ($payload['source'] ?? 'WEB'));
        $availability = $this->availability($source, $payload['queue'] ?? null);
        if (!in_array($availability['status'], ['ONLINE', 'BUSY'], true)) {
            throw new RuntimeException($availability['message'] ?? 'Live chat is unavailable. Please create a support ticket.');
        }

        return DB::transaction(function () use ($payload, $source, $user): SupportChat {
            $queue = $this->queue($payload['queue'] ?? null);
            $chat = SupportChat::create([
                'chat_uuid' => (string) Str::uuid(),
                'conversation_number' => $this->conversationNumber(),
                'user_id' => $user->id,
                'queue_id' => $queue->id,
                'status' => 'WAITING',
                'priority' => strtoupper((string) ($payload['priority'] ?? 'NORMAL')),
                'source' => $source,
                'product' => $payload['product'] ?? null,
                'related_entity_type' => $payload['related_entity_type'] ?? null,
                'related_entity_id' => isset($payload['related_entity_id']) ? (string) $payload['related_entity_id'] : null,
                'started_at' => now(),
                'waiting_since' => now(),
                'last_activity_at' => now(),
                'metadata' => ['topic' => $payload['topic'] ?? null],
            ]);
            $this->system($chat, 'Conversation started.');
            $this->assignNext($queue);

            return $chat->fresh(['messages']);
        });
    }

    public function heartbeat(Admin $admin, array $payload): SupportAgentProfile
    {
        $queue = isset($payload['queue']) ? $this->queue($payload['queue']) : null;
        return SupportAgentProfile::query()->updateOrCreate(
            ['admin_id' => $admin->id],
            [
                'support_enabled' => (bool) ($payload['support_enabled'] ?? true),
                'default_queue_id' => $queue?->id,
                'max_concurrent_chats' => (int) ($payload['max_concurrent_chats'] ?? $this->settings()['max_concurrent_chats_per_agent']),
                'status' => strtoupper((string) ($payload['status'] ?? 'ONLINE')),
                'last_heartbeat_at' => now(),
                'skills' => $payload['skills'] ?? [],
            ]
        );
    }

    public function sendUserMessage(User $user, SupportChat $chat, string $body, ?string $key = null): SupportChatMessage
    {
        if ((int) $chat->user_id !== (int) $user->id) {
            abort(404);
        }

        return $this->message($chat, 'USER', $user->id, $body, 'PUBLIC', $key);
    }

    public function sendAgentMessage(Admin $admin, SupportChat $chat, string $body, bool $internal = false, ?string $key = null): SupportChatMessage
    {
        if (!$internal && !$chat->first_agent_response_at) {
            $chat->update(['first_agent_response_at' => now()]);
        }

        return $this->message($chat, 'AGENT', $admin->id, $body, $internal ? 'INTERNAL' : 'PUBLIC', $key);
    }

    public function replay(User|Admin $actor, SupportChat $chat, int $after = 0): array
    {
        if ($actor instanceof User && (int) $chat->user_id !== (int) $actor->id) {
            abort(404);
        }
        $query = $chat->messages()->where('sequence', '>', $after)->orderBy('sequence');
        if ($actor instanceof User) {
            $query->where('visibility', 'PUBLIC');
        }

        return ['chat' => $chat->fresh(), 'messages' => $query->get()->values(), 'next_sequence' => (int) $chat->messages()->max('sequence')];
    }

    public function manualAssign(SupportChat $chat, Admin $agent, Admin $actor): SupportChat
    {
        return DB::transaction(function () use ($actor, $agent, $chat): SupportChat {
            $profile = SupportAgentProfile::query()->where('admin_id', $agent->id)->first();
            $limit = (int) ($profile?->max_concurrent_chats ?: $this->settings()['max_concurrent_chats_per_agent']);
            $active = SupportChat::query()->where('assigned_agent_id', $agent->id)->whereIn('status', ['ASSIGNED', 'ACTIVE'])->count();
            if (!$profile?->support_enabled || !in_array($profile->status, ['ONLINE', 'BUSY'], true) || $active >= $limit) {
                throw new RuntimeException('Selected agent is not available for another live chat.');
            }
            $chat->update(['assigned_agent_id' => $agent->id, 'status' => 'ASSIGNED', 'assigned_at' => now(), 'last_activity_at' => now()]);
            $this->system($chat, 'Conversation assigned.');
            $this->publish($chat, 'assignment.updated', ['agent_id' => $agent->id, 'actor_id' => $actor->id]);

            return $chat->fresh();
        });
    }

    public function transfer(SupportChat $chat, ?Admin $agent, ?string $queueCode, Admin $actor): SupportChat
    {
        return DB::transaction(function () use ($actor, $agent, $chat, $queueCode): SupportChat {
            $queue = $queueCode ? $this->queue($queueCode) : null;
            $chat->update(['assigned_agent_id' => $agent?->id, 'queue_id' => $queue?->id ?? $chat->queue_id, 'status' => $agent ? 'ASSIGNED' : 'WAITING', 'last_activity_at' => now()]);
            $this->system($chat, 'Conversation transferred.');
            $this->publish($chat, 'conversation.transferred', ['agent_id' => $agent?->id, 'queue_id' => $queue?->id, 'actor_id' => $actor->id]);

            return $chat->fresh();
        });
    }

    public function end(SupportChat $chat, Admin|User $actor): SupportChat
    {
        $chat->update(['status' => 'ENDED', 'ended_at' => now(), 'last_activity_at' => now()]);
        $this->system($chat, 'Conversation ended.');
        $this->publish($chat, 'conversation.ended', ['actor_type' => $actor instanceof Admin ? 'ADMIN' : 'USER', 'actor_id' => $actor->id]);

        return $chat->fresh();
    }

    public function convertToTicket(SupportChat $chat, Admin $actor, string $subject = 'Support chat follow-up'): SupportTicket
    {
        if ($chat->converted_ticket_id) {
            return SupportTicket::query()->findOrFail($chat->converted_ticket_id);
        }

        return DB::transaction(function () use ($actor, $chat, $subject): SupportTicket {
            $ticket = $this->support->createTicket($chat->user, [
                'category' => 'Technical Issue',
                'product' => $chat->product,
                'subject' => $subject,
                'description' => $chat->messages()->where('visibility', 'PUBLIC')->orderBy('sequence')->pluck('body')->implode("\n\n"),
                'source' => 'LIVE_CHAT',
                'related_entity_type' => $chat->related_entity_type,
                'related_entity_id' => $chat->related_entity_id,
                'metadata' => ['conversation_number' => $chat->conversation_number],
            ], 'chat-convert-'.$chat->conversation_number);
            $chat->update(['status' => 'CONVERTED_TO_TICKET', 'converted_ticket_id' => $ticket->id, 'ended_at' => now()]);
            $this->system($chat, 'Conversation converted to ticket '.$ticket->ticket_number.'.');
            $this->publish($chat, 'conversation.converted', ['ticket_number' => $ticket->ticket_number, 'actor_id' => $actor->id]);
            $this->notify($chat, 'support.chat.converted_to_ticket', ['conversation_number' => $chat->conversation_number, 'ticket_number' => $ticket->ticket_number]);

            return $ticket;
        });
    }

    public function health(): array
    {
        return [
            'backend' => 'HEALTHY',
            'realtime' => 'BEST_EFFORT_REDIS_WITH_DB_REPLAY',
            'assignment' => 'HEALTHY',
            'waiting' => SupportChat::query()->where('status', 'WAITING')->count(),
            'active' => SupportChat::query()->whereIn('status', ['ASSIGNED', 'ACTIVE'])->count(),
            'agents_online' => $this->freshAgents()->count(),
        ];
    }

    private function message(SupportChat $chat, string $senderType, int $senderId, string $body, string $visibility, ?string $key): SupportChatMessage
    {
        return DB::transaction(function () use ($body, $chat, $key, $senderId, $senderType, $visibility): SupportChatMessage {
            if ($key) {
                $existing = SupportChatMessage::query()->where('chat_id', $chat->id)->where('idempotency_key', $key)->first();
                if ($existing) {
                    return $existing;
                }
            }
            $sequence = ((int) SupportChatMessage::query()->where('chat_id', $chat->id)->lockForUpdate()->max('sequence')) + 1;
            $message = SupportChatMessage::create([
                'chat_id' => $chat->id,
                'message_uuid' => (string) Str::uuid(),
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'message_type' => $visibility === 'INTERNAL' ? 'INTERNAL_NOTE' : 'MESSAGE',
                'visibility' => $visibility,
                'body' => $this->sanitize($body),
                'metadata' => [],
                'idempotency_key' => $key,
                'sequence' => $sequence,
            ]);
            $chat->update(['status' => $chat->status === 'ASSIGNED' ? 'ACTIVE' : $chat->status, 'last_activity_at' => now()]);
            if ($visibility === 'PUBLIC') {
                $this->publish($chat, 'message.created', ['sequence' => $sequence, 'sender_type' => $senderType]);
                if ($senderType === 'AGENT') {
                    $this->notify($chat, 'support.chat.agent_replied', ['conversation_number' => $chat->conversation_number]);
                }
            }

            return $message;
        });
    }

    private function assignNext(SupportQueue $queue): void
    {
        $settings = $this->settings();
        if (!$settings['auto_assignment_enabled']) {
            return;
        }
        $agent = $this->availableAgents($queue, $settings)->first();
        $chat = SupportChat::query()->where('queue_id', $queue->id)->where('status', 'WAITING')->oldest('waiting_since')->lockForUpdate()->first();
        if (!$agent || !$chat) {
            return;
        }
        $chat->update(['assigned_agent_id' => $agent->admin_id, 'status' => 'ASSIGNED', 'assigned_at' => now(), 'last_activity_at' => now()]);
        $agent->update(['last_assigned_at' => now()]);
        $this->system($chat, 'Conversation assigned.');
        $this->notify($chat, 'support.chat.assigned', ['conversation_number' => $chat->conversation_number]);
    }

    private function availableAgents(SupportQueue $queue, array $settings)
    {
        return $this->freshAgents()
            ->where(function ($q) use ($queue) {
                $q->whereNull('default_queue_id')->orWhere('default_queue_id', $queue->id);
            })
            ->get()
            ->filter(fn (SupportAgentProfile $agent) => SupportChat::query()->where('assigned_agent_id', $agent->admin_id)->whereIn('status', ['ASSIGNED', 'ACTIVE'])->count() < (int) ($agent->max_concurrent_chats ?: $settings['max_concurrent_chats_per_agent']));
    }

    private function freshAgents()
    {
        return SupportAgentProfile::query()
            ->where('support_enabled', true)
            ->whereIn('status', ['ONLINE', 'BUSY'])
            ->where('last_heartbeat_at', '>=', now()->subMinutes(2));
    }

    private function withinOperatingHours(array $settings): bool
    {
        if (!$settings['operating_hours_enabled']) {
            return true;
        }
        $now = now((string) $settings['timezone']);
        if (in_array($now->toDateString(), (array) $settings['holiday_dates'], true)) {
            return false;
        }
        $window = $settings['operating_hours'][strtolower($now->format('l'))] ?? null;
        if (!$window || count($window) < 2) {
            return false;
        }

        return $now->format('H:i') >= $window[0] && $now->format('H:i') <= $window[1];
    }

    private function unavailable(string $status, string $message, array $settings): array
    {
        return [
            'live_chat_enabled' => false,
            'status' => $status,
            'message' => $message,
            'queue_available' => false,
            'estimated_wait' => null,
            'offline_fallback' => $settings['offline_ticket_fallback'] ? 'ticket' : null,
        ];
    }

    private function queue(?string $code): SupportQueue
    {
        $code = strtoupper((string) ($code ?: $this->settings()['default_queue']));
        return SupportQueue::query()->firstOrCreate(['code' => $code], ['name' => Str::headline(strtolower($code)), 'active' => true]);
    }

    private function system(SupportChat $chat, string $body): void
    {
        $this->message($chat, 'SYSTEM', 0, $body, 'PUBLIC', 'system-'.$chat->id.'-'.sha1($body.now()->timestamp));
    }

    private function publish(SupportChat $chat, string $event, array $payload): void
    {
        try {
            Redis::publish('support.chat.'.$chat->chat_uuid, json_encode(['event' => $event, 'conversation_number' => $chat->conversation_number, 'payload' => $payload, 'timestamp' => now()->toISOString()], JSON_THROW_ON_ERROR));
        } catch (\Throwable) {
            // Realtime is best effort; persisted replay remains authoritative.
        }
    }

    private function notify(SupportChat $chat, string $event, array $payload): void
    {
        $this->notifications->emit($chat->user, $event, $payload + ['deep_link' => '/support'], $chat->conversation_number.'-'.$event, ['in_app']);
    }

    private function conversationNumber(): string
    {
        do {
            $number = 'EXA-CHAT-'.strtoupper(Str::random(8));
        } while (SupportChat::query()->where('conversation_number', $number)->exists());

        return $number;
    }

    private function sanitize(string $body): string
    {
        return trim(preg_replace('/(password|private key|api secret|cvv)\s*[:=]\s*\S+/i', '$1: [redacted]', $body) ?? $body);
    }
}
