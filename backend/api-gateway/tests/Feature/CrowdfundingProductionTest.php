<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CrowdfundingCampaign;
use App\Models\CrowdfundingComment;
use App\Models\CrowdfundingCreator;
use App\Models\CrowdfundingDocument;
use App\Models\CrowdfundingMilestone;
use App\Models\CrowdfundingOperationsSetting;
use App\Models\CrowdfundingPledge;
use App\Models\LedgerEntry;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountClosureSafetyService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrowdfundingProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_creation_submission_review_and_investment_gating(): void
    {
        $creator = User::factory()->create(['verified_country' => 'NG', 'kyc_level' => 2]);
        $admin = $this->admin();

        $campaign = $this->actingAs($creator)->postJson('/api/crowdfunding/campaigns', [
            'creator_display_name' => 'Clean Water Cooperative',
            'classification' => 'PROJECT_SUPPORT',
            'title' => 'Community Water Grid',
            'summary' => 'Community project with transparent milestones.',
            'funding_goal' => '1000',
            'minimum_pledge' => '10',
            'maximum_goal' => '1200',
        ])->assertCreated()->json('data');

        $this->actingAs($creator)->postJson("/api/crowdfunding/campaigns/{$campaign['id']}/submit")->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        CrowdfundingCreator::query()->where('id', $campaign['creator_id'])->update(['verification_state' => 'VERIFIED']);
        $this->actingAs($admin)->postJson("/api/admin/crowdfunding/campaigns/{$campaign['id']}/review", ['action' => 'APPROVE', 'reason' => 'KYB and docs checked'])->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->actingAs($admin)->postJson("/api/admin/crowdfunding/campaigns/{$campaign['id']}/review", ['action' => 'LIVE'])->assertOk()->assertJsonPath('data.status', 'LIVE');

        $this->actingAs($creator)->postJson('/api/crowdfunding/campaigns', [
            'classification' => 'EQUITY',
            'title' => 'Equity style project',
            'funding_goal' => '1000',
        ])->assertStatus(422);
    }

    public function test_pledge_uses_canonical_reservation_escrow_idempotency_and_cap(): void
    {
        $campaign = $this->liveCampaign('900', '1000');
        $backer = User::factory()->create(['verified_country' => 'NG', 'kyc_level' => 2]);
        app(LedgerService::class)->fiatDeposit($backer->id, '1000', 'USDT', 'seed-backer-1');

        $first = $this->actingAs($backer)->withHeader('Idempotency-Key', 'pledge-001')->postJson("/api/crowdfunding/campaigns/{$campaign->id}/contributions", ['amount' => '400'])->assertCreated()->json('data');
        $second = $this->actingAs($backer)->withHeader('Idempotency-Key', 'pledge-001')->postJson("/api/crowdfunding/campaigns/{$campaign->id}/contributions", ['amount' => '400'])->assertCreated()->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame('HELD_IN_ESCROW', $first['status']);
        $this->assertDatabaseHas('reservations', ['reservation_id' => $first['reservation_id'], 'status' => 'consumed']);
        $this->assertGreaterThanOrEqual(2, LedgerEntry::query()->where('reference', $first['ledger_reference'])->count());
        $this->assertSame(0, \App\Services\FinancialDecimal::compare('400', (string) $campaign->fresh()->raised_amount));
        $this->assertDatabaseHas('notifications', ['user_id' => $backer->id, 'event_key' => 'crowdfunding.pledge.completed']);

        $this->actingAs(User::factory()->create())->withHeader('Idempotency-Key', 'pledge-002')->postJson("/api/crowdfunding/campaigns/{$campaign->id}/contributions", ['amount' => '700'])->assertStatus(422);
    }

    public function test_milestone_review_release_maker_checker_and_reconciliation(): void
    {
        $campaign = $this->liveCampaign('500', '1000');
        $backer = User::factory()->create(['verified_country' => 'NG', 'kyc_level' => 2]);
        app(LedgerService::class)->fiatDeposit($backer->id, '800', 'USDT', 'seed-backer-2');
        $this->actingAs($backer)->withHeader('Idempotency-Key', 'pledge-release')->postJson("/api/crowdfunding/campaigns/{$campaign->id}/pledges", ['amount' => '500'])->assertCreated();

        $maker = $this->admin('maker');
        $checker = $this->admin('checker');
        $milestone = $this->actingAs($maker)->postJson("/api/admin/crowdfunding/campaigns/{$campaign->id}/milestones", [
            'sequence' => 1,
            'title' => 'Prototype delivered',
            'target_amount' => '200',
        ])->assertCreated()->json('data');

        $this->actingAs($campaign->creator->user)->postJson("/api/crowdfunding/milestones/{$milestone['id']}/submit", ['evidence' => ['report' => 'private-document-ref']])->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $this->actingAs($maker)->postJson("/api/admin/crowdfunding/milestones/{$milestone['id']}/review", ['action' => 'APPROVE'])->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->actingAs($maker)->postJson("/api/admin/crowdfunding/milestones/{$milestone['id']}/release", ['checker_admin_id' => $maker->id])->assertStatus(422);
        $payout = $this->actingAs($maker)->postJson("/api/admin/crowdfunding/milestones/{$milestone['id']}/release", ['checker_admin_id' => $checker->id])->assertCreated()->json('data');

        $this->assertSame('COMPLETED', $payout['status']);
        $this->assertGreaterThanOrEqual(2, LedgerEntry::query()->where('reference', $payout['payable_reference'])->count());
        $this->assertGreaterThanOrEqual(2, LedgerEntry::query()->where('reference', $payout['payout_reference'])->count());
        $this->actingAs($maker)->getJson('/api/admin/crowdfunding/reconciliation')->assertOk()->assertJsonPath('data.status', 'PASS');
    }

    public function test_refund_batches_are_idempotent_and_account_closure_is_blocked_until_resolution(): void
    {
        $campaign = $this->liveCampaign('500', '1000');
        $backer = User::factory()->create(['verified_country' => 'NG', 'kyc_level' => 2]);
        app(LedgerService::class)->fiatDeposit($backer->id, '600', 'USDT', 'seed-backer-3');
        $pledge = $this->actingAs($backer)->withHeader('Idempotency-Key', 'pledge-refund')->postJson("/api/crowdfunding/campaigns/{$campaign->id}/pledges", ['amount' => '250'])->assertCreated()->json('data');

        $this->assertFalse(app(AccountClosureSafetyService::class)->readiness($backer->id)['can_close']);
        $admin = $this->admin();
        $this->actingAs($admin)->postJson("/api/admin/crowdfunding/campaigns/{$campaign->id}/review", ['action' => 'SUSPEND', 'reason' => 'Creator verification concern'])->assertOk();
        $batch = $this->actingAs($admin)->postJson("/api/admin/crowdfunding/campaigns/{$campaign->id}/refund", ['reason' => 'campaign_suspended'])->assertCreated()->json('data');

        $this->assertSame('COMPLETED', $batch['status']);
        $this->assertSame('REFUNDED', CrowdfundingPledge::query()->findOrFail($pledge['id'])->status);
        $this->assertTrue(app(AccountClosureSafetyService::class)->readiness($backer->id)['can_close']);
    }

    public function test_comments_questions_reports_moderation_and_notifications_are_persisted(): void
    {
        $campaign = $this->liveCampaign();
        $backer = User::factory()->create(['verified_country' => 'NG', 'kyc_level' => 2]);
        app(LedgerService::class)->fiatDeposit($backer->id, '100', 'USDT', 'seed-comment-backer');
        $this->actingAs($backer)->withHeader('Idempotency-Key', 'pledge-comment')->postJson("/api/crowdfunding/campaigns/{$campaign->id}/pledges", ['amount' => '50'])->assertCreated();

        $question = $this->actingAs($backer)->postJson("/api/crowdfunding/campaigns/{$campaign->id}/comments", [
            'type' => 'QUESTION',
            'body' => 'When will the first milestone be shipped?',
        ])->assertCreated()->json('data');

        $reply = $this->actingAs($campaign->creator->user)->postJson("/api/crowdfunding/campaigns/{$campaign->id}/comments", [
            'parent_id' => $question['id'],
            'type' => 'ANSWER',
            'body' => 'The first milestone is planned after admin review.',
        ])->assertCreated()->json('data');

        $this->assertTrue((bool) $reply['is_creator_reply']);
        $this->assertDatabaseHas('activity_logs', ['type' => 'crowdfunding', 'action' => 'comment.created']);
        $this->assertDatabaseHas('notifications', ['user_id' => $campaign->creator->user_id, 'event_key' => 'crowdfunding.question.received']);

        $this->actingAs($backer)->postJson("/api/crowdfunding/comments/{$question['id']}/report", ['reason' => 'spam'])->assertOk()->assertJsonPath('data.status', 'UNDER_REVIEW');
        $admin = $this->admin();
        $this->actingAs($admin)->postJson("/api/admin/crowdfunding/comments/{$question['id']}/moderate", [
            'status' => 'HIDDEN',
            'reason' => 'User report verified',
        ])->assertOk()->assertJsonPath('data.status', 'HIDDEN');

        $this->assertSame('HIDDEN', CrowdfundingComment::query()->findOrFail($question['id'])->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $backer->id, 'event_key' => 'crowdfunding.comment.moderated']);
        $this->actingAs($backer)->getJson("/api/crowdfunding/campaigns/{$campaign->id}/comments")->assertOk();
    }

    public function test_documents_upload_access_review_and_investment_operations_gate(): void
    {
        Storage::fake('local');
        $campaign = $this->liveCampaign();
        $creator = $campaign->creator->user;

        $public = $this->actingAs($creator)->post("/api/crowdfunding/campaigns/{$campaign->id}/documents", [
            'document_type' => 'LEGAL_DISCLOSURE',
            'visibility' => 'PUBLIC',
            'document' => UploadedFile::fake()->create('disclosure.pdf', 64, 'application/pdf'),
        ])->assertCreated()->json('data');

        $private = $this->actingAs($creator)->post("/api/crowdfunding/campaigns/{$campaign->id}/documents", [
            'document_type' => 'PRIVATE_CREATOR_DOCUMENT',
            'visibility' => 'PRIVATE',
            'document' => UploadedFile::fake()->create('kyb.pdf', 64, 'application/pdf'),
        ])->assertCreated()->json('data');

        $this->assertSame('APPROVED', $public['status']);
        $this->assertSame('PENDING_REVIEW', $private['status']);
        $this->actingAs(User::factory()->create())->get("/api/crowdfunding/documents/{$private['id']}")->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->postJson("/api/admin/crowdfunding/documents/{$private['id']}/review", [
            'status' => 'APPROVED',
            'reason' => 'KYB document matches creator profile',
        ])->assertOk()->assertJsonPath('data.status', 'APPROVED');

        $assignee = $this->admin('crowdfunding-assignee');
        $this->actingAs($admin)->postJson('/api/admin/crowdfunding/assignments', [
            'entity_type' => 'DOCUMENT',
            'entity_id' => $private['id'],
            'assignee_admin_id' => $assignee->id,
            'reason' => 'Document review workload assignment',
        ])->assertCreated()->assertJsonPath('data.assignment.assigned_to', $assignee->id);
        $this->assertSame($assignee->id, CrowdfundingDocument::query()->findOrFail($private['id'])->metadata['review_assignment']['assigned_to']);

        $this->actingAs($creator)->post("/api/crowdfunding/campaigns/{$campaign->id}/documents", [
            'document_type' => 'PRIVATE_CREATOR_DOCUMENT',
            'visibility' => 'PRIVATE',
            'document' => UploadedFile::fake()->create('payload.exe', 8, 'application/x-msdownload'),
        ])->assertStatus(422);

        $this->actingAs($admin)->putJson('/api/admin/crowdfunding/operations', [
            'key' => 'new_pledges_enabled',
            'value' => ['enabled' => false, 'reason' => 'maintenance'],
        ])->assertOk();
        $this->assertFalse(CrowdfundingOperationsSetting::query()->where('key', 'new_pledges_enabled')->firstOrFail()->value['enabled']);

        $this->actingAs($admin)->putJson('/api/admin/crowdfunding/operations', [
            'key' => 'investment_campaigns_enabled',
            'value' => ['enabled' => true],
        ])->assertStatus(422);
        $this->assertDatabaseHas('notifications', ['user_id' => $creator->id, 'event_key' => 'crowdfunding.document.reviewed']);
    }

    public function test_creator_and_backer_dashboards_activity_and_support_deep_links_are_real(): void
    {
        $campaign = $this->liveCampaign();
        $backer = User::factory()->create(['verified_country' => 'NG', 'kyc_level' => 2]);
        app(LedgerService::class)->fiatDeposit($backer->id, '300', 'USDT', 'seed-dash-backer');
        $pledge = $this->actingAs($backer)->withHeader('Idempotency-Key', 'pledge-dash')->postJson("/api/crowdfunding/campaigns/{$campaign->id}/pledges", ['amount' => '100'])->assertCreated()->json('data');

        $this->actingAs($campaign->creator->user)->getJson('/api/crowdfunding/creator/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.campaigns', 1);
        $this->actingAs($backer)->getJson('/api/crowdfunding/backer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.total_pledges', 1);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $backer->id, 'type' => 'crowdfunding']);
        $this->actingAs($backer)->postJson('/api/v1/support/tickets', [
            'category' => 'Crowdfunding',
            'subject' => 'Question about pledge',
            'description' => 'Please help me with my crowdfunding pledge.',
            'product' => 'Crowdfunding',
            'related_entity_type' => 'crowdfunding_pledge',
            'related_entity_id' => (string) $pledge['id'],
        ], ['Idempotency-Key' => 'support-crowdfunding-pledge'])->assertCreated();
        $this->assertDatabaseHas('support_tickets', ['product' => 'Crowdfunding', 'related_entity_type' => 'crowdfunding_pledge']);
    }

    private function liveCampaign(string $goal = '1000', string $cap = '1500'): CrowdfundingCampaign
    {
        $creatorUser = User::factory()->create(['verified_country' => 'NG', 'kyc_level' => 2]);
        $creator = CrowdfundingCreator::query()->create([
            'user_id' => $creatorUser->id,
            'display_name' => 'Verified Creator '.str()->random(5),
            'country' => 'NG',
            'kyc_status' => 'VERIFIED',
            'verification_state' => 'VERIFIED',
            'status' => 'ACTIVE',
        ]);

        return CrowdfundingCampaign::query()->create([
            'public_id' => 'CF-'.strtoupper(str()->random(10)),
            'creator_id' => $creator->id,
            'classification' => 'PROJECT_SUPPORT',
            'title' => 'Ledger backed community project '.str()->random(5),
            'slug' => 'ledger-backed-project-'.str()->random(6),
            'category' => 'Community',
            'asset' => 'USDT',
            'funding_goal' => $goal,
            'minimum_goal' => '0',
            'maximum_goal' => $cap,
            'minimum_pledge' => '10',
            'maximum_pledge' => null,
            'status' => 'LIVE',
            'funding_model' => 'ALL_OR_NOTHING',
            'published_at' => now(),
        ])->fresh(['creator.user']);
    }

    private function admin(string $label = 'crowdfunding'): Admin
    {
        $role = Role::create(['name' => $label.'-'.str()->random(6)]);
        foreach (['crowdfunding.view', 'crowdfunding.review', 'crowdfunding.manage', 'crowdfunding.milestones', 'crowdfunding.release', 'crowdfunding.refund', 'crowdfunding.reconcile'] as $permission) {
            $model = Permission::query()->firstOrCreate(['name' => $permission]);
            $role->permissions()->attach($model->id);
        }

        return Admin::create([
            'name' => 'Crowdfunding Admin',
            'email' => 'crowdfunding-'.str()->random(8).'@exaearn.test',
            'password' => 'password',
            'status' => 'active',
            'role_id' => $role->id,
            'two_factor_enabled' => true,
        ]);
    }
}
