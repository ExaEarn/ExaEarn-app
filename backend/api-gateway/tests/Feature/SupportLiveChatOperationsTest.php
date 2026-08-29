<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SupportAgentProfile;
use App\Models\SupportChat;
use App\Models\SupportChatMessage;
use App\Models\SupportLiveChatSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportLiveChatOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_live_chat_is_disabled_by_default_and_falls_back_to_ticket(): void
    {
        $user = User::factory()->create();

        $availability = $this->actingAs($user)->getJson('/api/v1/support/chat/availability?source=WEB')->assertOk()->json('data');

        $this->assertSame('DISABLED', $availability['status']);
        $this->assertSame('ticket', $availability['offline_fallback']);
        $this->actingAs($user)->postJson('/api/v1/support/chat/conversations', ['source' => 'WEB'])->assertStatus(422)->assertJsonPath('fallback', 'ticket');
        $this->assertDatabaseCount('support_chats', 0);
    }

    public function test_admin_can_enable_chat_and_staff_activation_requires_no_code_change(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->putJson('/api/admin/support/live-chat/settings', [
            'live_chat_enabled' => true,
            'public_chat_enabled' => true,
            'web_chat_enabled' => true,
            'mobile_chat_enabled' => true,
            'operating_hours_enabled' => false,
            'max_waiting_conversations' => 5,
            'max_concurrent_chats_per_agent' => 2,
        ])->assertOk()->assertJsonPath('data.live_chat_enabled', true);

        $this->assertDatabaseHas('support_live_chat_settings', ['key' => 'public_chat_enabled']);

        $this->actingAs($admin)->postJson('/api/admin/support/live-chat/heartbeat', [
            'status' => 'ONLINE',
            'support_enabled' => true,
            'queue' => 'GENERAL',
            'max_concurrent_chats' => 2,
        ])->assertOk()->assertJsonPath('data.status', 'ONLINE');

        $this->actingAs($user)->getJson('/api/v1/support/chat/availability?source=WEB')->assertOk()->assertJsonPath('data.status', 'ONLINE');
        $conversation = $this->actingAs($user)->postJson('/api/v1/support/chat/conversations', [
            'source' => 'WEB',
            'topic' => 'Need help',
            'product' => 'WALLET',
        ])->assertCreated()->json('data');

        $this->assertStringStartsWith('EXA-CHAT-', $conversation['conversation_number']);
        $this->assertSame($admin->id, SupportChat::query()->findOrFail($conversation['id'])->assigned_agent_id);
        $this->assertGreaterThanOrEqual(2, SupportChatMessage::query()->where('chat_id', $conversation['id'])->count());
    }

    public function test_operating_hours_queue_capacity_and_stale_presence_are_enforced(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->enableChat($admin, ['operating_hours_enabled' => true, 'timezone' => 'UTC', 'operating_hours' => []]);
        $this->actingAs($user)->getJson('/api/v1/support/chat/availability?source=WEB')->assertOk()->assertJsonPath('data.status', 'OFFLINE');

        $this->enableChat($admin, ['operating_hours_enabled' => false, 'max_waiting_conversations' => 1]);
        $this->actingAs($user)->getJson('/api/v1/support/chat/availability?source=WEB')->assertOk()->assertJsonPath('data.status', 'BUSY');

        $first = $this->actingAs($user)->postJson('/api/v1/support/chat/conversations', ['source' => 'WEB'])->assertCreated()->json('data');
        $this->assertNotEmpty($first['id']);
        $this->actingAs(User::factory()->create())->getJson('/api/v1/support/chat/availability?source=WEB')->assertOk()->assertJsonPath('data.status', 'CAPACITY_REACHED');

        SupportAgentProfile::query()->create([
            'admin_id' => $admin->id,
            'support_enabled' => true,
            'status' => 'ONLINE',
            'max_concurrent_chats' => 2,
            'last_heartbeat_at' => now()->subMinutes(5),
        ]);
        $this->actingAs(User::factory()->create())->getJson('/api/v1/support/chat/availability?source=WEB')->assertOk()->assertJsonPath('data.status', 'CAPACITY_REACHED');
    }

    public function test_messages_are_ordered_idempotent_replayable_and_internal_notes_are_private(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $this->enableChat($admin);
        $this->actingAs($admin)->postJson('/api/admin/support/live-chat/heartbeat', ['status' => 'ONLINE'])->assertOk();
        $conversation = $this->actingAs($user)->postJson('/api/v1/support/chat/conversations', ['source' => 'WEB'])->assertCreated()->json('data');

        $first = $this->actingAs($user)->withHeader('Idempotency-Key', 'user-msg-1')->postJson("/api/v1/support/chat/conversations/{$conversation['id']}/messages", ['body' => 'password: hunter2 please help'])->assertCreated()->json('data');
        $second = $this->actingAs($user)->withHeader('Idempotency-Key', 'user-msg-1')->postJson("/api/v1/support/chat/conversations/{$conversation['id']}/messages", ['body' => 'duplicate'])->assertCreated()->json('data');
        $this->assertSame($first['id'], $second['id']);
        $this->assertStringContainsString('[redacted]', $first['body']);

        $this->actingAs($admin)->withHeader('Idempotency-Key', 'agent-msg-1')->postJson("/api/admin/support/live-chat/conversations/{$conversation['id']}/messages", ['body' => 'Visible agent reply'])->assertCreated();
        $this->actingAs($admin)->withHeader('Idempotency-Key', 'agent-note-1')->postJson("/api/admin/support/live-chat/conversations/{$conversation['id']}/messages", ['body' => 'Internal note', 'internal' => true])->assertCreated();

        $userReplay = $this->actingAs($user)->getJson("/api/v1/support/chat/conversations/{$conversation['id']}/replay?after_sequence=0")->assertOk()->json('data.messages');
        $this->assertNotContains('Internal note', array_column($userReplay, 'body'));
        $this->assertSame(range(1, count($userReplay)), array_map(fn ($row) => (int) $row['sequence'], $userReplay));

        $adminReplay = $this->actingAs($admin)->getJson("/api/admin/support/live-chat/conversations/{$conversation['id']}/replay?after_sequence=0")->assertOk()->json('data.messages');
        $this->assertContains('Internal note', array_column($adminReplay, 'body'));
    }

    public function test_manual_assignment_concurrency_transfer_end_and_ticket_conversion_are_safe(): void
    {
        $supervisor = $this->admin();
        $agent = $this->admin('support-agent');
        $user = User::factory()->create();
        $this->enableChat($supervisor, ['auto_assignment_enabled' => false]);
        $this->actingAs($supervisor)->postJson('/api/admin/support/live-chat/agents', [
            'admin_id' => $agent->id,
            'support_enabled' => true,
            'status' => 'ONLINE',
            'max_concurrent_chats' => 1,
        ])->assertOk();

        $conversation = $this->actingAs($user)->postJson('/api/v1/support/chat/conversations', ['source' => 'WEB'])->assertCreated()->json('data');
        $this->actingAs($supervisor)->postJson("/api/admin/support/live-chat/conversations/{$conversation['id']}/assign", ['agent_id' => $agent->id])->assertOk();

        $second = $this->actingAs(User::factory()->create())->postJson('/api/v1/support/chat/conversations', ['source' => 'WEB'])->assertCreated()->json('data');
        $this->actingAs($supervisor)->postJson("/api/admin/support/live-chat/conversations/{$second['id']}/assign", ['agent_id' => $agent->id])->assertStatus(422);

        $this->actingAs($supervisor)->postJson("/api/admin/support/live-chat/conversations/{$conversation['id']}/transfer", ['queue' => 'SECURITY'])->assertOk()->assertJsonPath('data.status', 'WAITING');
        $ticket = $this->actingAs($agent)->postJson("/api/admin/support/live-chat/conversations/{$conversation['id']}/convert-to-ticket", ['subject' => 'Live chat follow-up'])->assertCreated()->json('data');
        $again = $this->actingAs($agent)->postJson("/api/admin/support/live-chat/conversations/{$conversation['id']}/convert-to-ticket", ['subject' => 'Duplicate'])->assertCreated()->json('data');
        $this->assertSame($ticket['id'], $again['id']);

        $this->actingAs($agent)->postJson("/api/admin/support/live-chat/conversations/{$second['id']}/end")->assertOk()->assertJsonPath('data.status', 'ENDED');
    }

    private function enableChat(Admin $admin, array $overrides = []): void
    {
        $this->actingAs($admin)->putJson('/api/admin/support/live-chat/settings', array_merge([
            'live_chat_enabled' => true,
            'public_chat_enabled' => true,
            'web_chat_enabled' => true,
            'mobile_chat_enabled' => true,
            'operating_hours_enabled' => false,
            'max_waiting_conversations' => 25,
            'max_concurrent_chats_per_agent' => 2,
            'offline_ticket_fallback' => true,
            'auto_assignment_enabled' => true,
        ], $overrides))->assertOk();
    }

    private function admin(string $roleName = 'support-supervisor'): Admin
    {
        $role = Role::create(['name' => $roleName.'-'.str()->random(6)]);
        foreach ([
            'support.view',
            'support.reply',
            'support.assign',
            'support.resolve',
            'support.escalate',
            'support.manage_kb',
            'support.live_chat.view',
            'support.live_chat.manage',
            'support.live_chat.agent',
        ] as $permission) {
            $model = Permission::query()->firstOrCreate(['name' => $permission]);
            $role->permissions()->attach($model->id);
        }

        return Admin::create([
            'name' => 'Support Admin',
            'email' => 'support-live-chat-'.str()->random(8).'@exaearn.test',
            'password' => 'password',
            'status' => 'active',
            'role_id' => $role->id,
            'two_factor_enabled' => true,
        ]);
    }
}
