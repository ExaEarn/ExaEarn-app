<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SupportPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_creation_is_persisted_idempotent_and_notifies_user(): void
    {
        $user = User::factory()->create();

        $payload = [
            'category' => 'Withdrawal',
            'product' => 'WALLET',
            'subject' => 'Withdrawal is stuck',
            'description' => 'My withdrawal has been pending longer than expected.',
            'related_entity_type' => 'withdrawal',
            'related_entity_id' => 'wd-001',
        ];

        $first = $this->actingAs($user)->withHeader('Idempotency-Key', 'ticket-001')->postJson('/api/v1/support/tickets', $payload)->assertCreated()->json('data');
        $second = $this->actingAs($user)->withHeader('Idempotency-Key', 'ticket-001')->postJson('/api/v1/support/tickets', $payload)->assertOk()->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertStringStartsWith('EXA-SUP-', $first['ticket_number']);
        $this->assertDatabaseCount('support_tickets', 1);
        $this->assertDatabaseHas('support_ticket_messages', ['ticket_id' => $first['id'], 'sender_type' => 'USER', 'visibility' => 'PUBLIC']);
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'event_key' => 'support.ticket.created']);
    }

    public function test_user_cannot_access_another_users_ticket_or_internal_notes(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ticket = $this->ticket($owner);

        SupportTicketMessage::create(['ticket_id' => $ticket->id, 'sender_type' => 'AGENT', 'sender_id' => 1, 'message_type' => 'INTERNAL_NOTE', 'visibility' => 'INTERNAL', 'body' => 'Do not leak']);
        SupportTicketMessage::create(['ticket_id' => $ticket->id, 'sender_type' => 'AGENT', 'sender_id' => 1, 'message_type' => 'MESSAGE', 'visibility' => 'PUBLIC', 'body' => 'Visible reply']);

        $this->actingAs($other)->getJson("/api/v1/support/tickets/{$ticket->id}")->assertNotFound();

        $messages = $this->actingAs($owner)->getJson("/api/v1/support/tickets/{$ticket->id}")->assertOk()->json('data.messages');
        $this->assertCount(1, $messages);
        $this->assertSame('Visible reply', $messages[0]['body']);
    }

    public function test_attachment_validation_rejects_executable_uploads(): void
    {
        $user = User::factory()->create();
        $ticket = $this->ticket($user);
        $file = UploadedFile::fake()->create('payload.exe', 4, 'application/x-msdownload');

        $this->actingAs($user)->postJson("/api/v1/support/tickets/{$ticket->id}/attachments", ['file' => $file])->assertStatus(422);
        $this->assertSame(0, SupportTicketAttachment::query()->count());
    }

    public function test_admin_can_reply_assign_escalate_and_validates_state_machine(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $ticket = $this->ticket($user);

        $this->actingAs($admin)->postJson("/api/admin/support/tickets/{$ticket->id}/reply", ['body' => 'We are checking this.'])->assertCreated();
        $this->assertDatabaseHas('support_ticket_messages', ['ticket_id' => $ticket->id, 'sender_type' => 'AGENT', 'visibility' => 'PUBLIC']);
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'event_key' => 'support.ticket.agent_replied']);

        $this->actingAs($admin)->postJson("/api/admin/support/tickets/{$ticket->id}/transition", ['status' => 'CLOSED'])->assertStatus(422);
        $this->actingAs($admin)->postJson("/api/admin/support/tickets/{$ticket->id}/transition", ['status' => 'RESOLVED', 'resolution_code' => 'USER_EDUCATED'])->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/support/tickets/{$ticket->id}/transition", ['status' => 'CLOSED'])->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/support/tickets/{$ticket->id}/transition", ['status' => 'REOPENED'])->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/support/tickets/{$ticket->id}/escalate", ['to_queue' => 'SECURITY', 'reason' => 'Potential account takeover'])->assertOk();
        $this->assertDatabaseHas('support_escalations', ['ticket_id' => $ticket->id, 'to_queue' => 'SECURITY']);
    }

    public function test_sla_and_dispute_views_are_real_records(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $this->ticket($user, ['priority' => 'URGENT', 'resolution_due_at' => now()->subMinute()]);

        $overview = $this->actingAs($admin)->getJson('/api/admin/support/overview')->assertOk()->json('data');
        $this->assertSame('BREACHED', $overview['sla']['status']);
        $this->assertGreaterThanOrEqual(1, $overview['sla']['breached']);

        $this->actingAs($admin)->getJson('/api/admin/support/disputes')->assertOk()->assertJsonPath('data.policy', 'Support links to product disputes; product domains remain authoritative.');
    }

    public function test_knowledge_base_is_versioned_and_searchable(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/admin/support/knowledge-base', [
            'title' => 'How withdrawals work',
            'category' => 'Withdrawals',
            'summary' => 'Understand withdrawal review and settlement.',
            'body' => 'Withdrawals may require security review before final settlement.',
            'status' => 'PUBLISHED',
            'keywords' => ['withdrawal', 'security'],
        ])->assertCreated();

        $this->assertDatabaseHas('kb_articles', ['slug' => 'how-withdrawals-work', 'status' => 'PUBLISHED']);
        $this->assertDatabaseHas('kb_article_versions', ['version' => 1]);

        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/v1/support/knowledge-base?q=withdrawals')->assertOk()->assertJsonFragment(['title' => 'How withdrawals work']);
    }
    private function ticket(User $user, array $overrides = []): SupportTicket
    {
        return SupportTicket::create(array_merge([
            'ticket_number' => 'EXA-SUP-'.strtoupper(str()->random(8)),
            'user_id' => $user->id,
            'category' => 'Account',
            'priority' => 'NORMAL',
            'severity' => 'NORMAL',
            'status' => 'OPEN',
            'subject' => 'Need help',
            'description' => 'I need help with my ExaEarn account.',
            'source' => 'WEB',
            'last_activity_at' => now(),
            'first_response_due_at' => now()->addHours(4),
            'resolution_due_at' => now()->addDays(2),
        ], $overrides));
    }

    private function admin(): Admin
    {
        $role = Role::create(['name' => 'admin']);
        foreach (['support.view', 'support.reply', 'support.assign', 'support.resolve', 'support.escalate', 'support.manage_kb'] as $permission) {
            $model = Permission::query()->firstOrCreate(['name' => $permission]);
            $role->permissions()->attach($model->id);
        }

        return Admin::create([
            'name' => 'Support Admin',
            'email' => 'support-admin-'.str()->random(6).'@exaearn.test',
            'password' => 'password',
            'status' => 'active',
            'role_id' => $role->id,
            'two_factor_enabled' => true,
        ]);
    }
}
