<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ListingApplication;
use App\Models\ListingOrganization;
use App\Models\ListingReviewAssignment;
use App\Models\ListingReviewDecisionEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ListingP1GovernanceConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security-ratelimit.enabled' => false]);
        Queue::fake();
        Redis::shouldReceive('publish')->zeroOrMoreTimes();
    }

    public function test_applicant_owner_and_general_admin_cannot_reach_sensitive_listing_decisions(): void
    {
        [$owner, $application] = $this->submittedApplication();
        $generalAdmin = $this->admin('general-admin@example.com', ['listing.view']);

        $this->actingAs($owner)->postJson("/api/admin/v1/listing-center/applications/{$application->reference}/approve", [
            'reason' => 'Applicant owner trying to self-approve.',
        ])->assertForbidden();

        $this->actingAs($generalAdmin)->postJson("/api/admin/v1/listing-center/applications/{$application->reference}/reviews", [
            'review_type' => 'COMPLIANCE',
            'status' => 'PASSED',
            'expected_revision' => $application->application_revision,
        ])->assertForbidden()->assertJsonFragment(['message' => 'Permission denied: listing.compliance.review']);

        $reviewer = $this->admin('revoked-compliance@example.com', ['listing.compliance.review', 'listing.review.assign']);
        $reviewer->role->permissions()->detach();
        $this->actingAs($reviewer)->postJson('/api/admin/v1/listing-center/applications/'.$application->reference.'/reviews', [
            'review_type' => 'COMPLIANCE',
            'status' => 'PASSED',
            'expected_revision' => $application->application_revision,
        ])->assertForbidden()->assertJsonFragment(['message' => 'Permission denied: listing.compliance.review']);
    }

    public function test_review_assignment_reassignment_and_immutable_decision_history_are_enforced(): void
    {
        [, $application] = $this->submittedApplication();
        $lead = $this->admin('listing-lead@example.com', ['listing.review.assign', 'listing.compliance.review']);
        $assigned = $this->admin('assigned-compliance@example.com', ['listing.compliance.review']);
        $other = $this->admin('other-compliance@example.com', ['listing.compliance.review']);

        $this->actingAs($lead)->postJson("/api/admin/v1/listing-center/applications/{$application->reference}/review-assignments", [
            'review_domain' => 'COMPLIANCE',
            'reviewer_admin_id' => $assigned->id,
            'reason' => 'Assign compliance reviewer for independent KYB/listing review.',
        ])->assertCreated()->assertJsonPath('data.status', 'ACTIVE');

        $this->actingAs($other)->postJson("/api/admin/v1/listing-center/applications/{$application->reference}/reviews", [
            'review_type' => 'COMPLIANCE',
            'status' => 'PASSED',
            'expected_revision' => $application->application_revision,
        ])->assertUnprocessable()->assertJsonPath('message', 'REVIEW_ASSIGNMENT_REQUIRED');

        $this->actingAs($assigned)->postJson("/api/admin/v1/listing-center/applications/{$application->reference}/reviews", [
            'review_type' => 'COMPLIANCE',
            'status' => 'PASSED',
            'score' => 91,
            'notes' => 'Compliance evidence reviewed for the current revision.',
            'expected_revision' => $application->application_revision,
        ])->assertCreated()->assertJsonPath('data.status', 'PASSED');

        $this->assertSame(1, ListingReviewDecisionEvent::query()->where('review_domain', 'COMPLIANCE')->count());

        $this->actingAs($assigned)->postJson('/api/admin/v1/listing-center/applications/'.$application->reference.'/reviews', [
            'review_type' => 'COMPLIANCE',
            'status' => 'PASSED',
            'expected_revision' => $application->application_revision,
        ])->assertUnprocessable()->assertJsonPath('message', 'REVIEW_ALREADY_DECIDED');

        $this->actingAs($lead)->postJson("/api/admin/v1/listing-center/applications/{$application->reference}/review-assignments", [
            'review_domain' => 'COMPLIANCE',
            'reviewer_admin_id' => $other->id,
            'reason' => 'Reviewer rotation without erasing prior decision evidence.',
        ])->assertCreated()->assertJsonPath('data.reviewer_admin_id', $other->id);

        $this->assertSame(1, ListingReviewAssignment::query()->where('review_domain', 'COMPLIANCE')->where('status', 'ACTIVE')->count());
        $this->assertSame(1, ListingReviewAssignment::query()->where('review_domain', 'COMPLIANCE')->where('status', 'REASSIGNED')->count());
        $this->assertSame(1, ListingReviewDecisionEvent::query()->where('review_domain', 'COMPLIANCE')->where('reviewer_admin_id', $assigned->id)->count());
    }

    public function test_conflicted_internal_admin_and_stale_document_revision_are_blocked(): void
    {
        Storage::fake('listing_quarantine');
        [$owner, $application] = $this->submittedApplication();
        $lead = $this->admin('assignment-lead@example.com', ['listing.review.assign', 'listing.security.review']);
        $conflicted = $this->admin('conflicted-reviewer@example.com', ['listing.security.review'], $owner);
        $reviewer = $this->admin('security-reviewer@example.com', ['listing.security.review']);
        $oldRevision = (int) $application->application_revision;

        $this->actingAs($lead)->postJson("/api/admin/v1/listing-center/applications/{$application->reference}/review-assignments", [
            'review_domain' => 'SECURITY',
            'reviewer_admin_id' => $conflicted->id,
            'reason' => 'This conflicted reviewer must not be assignable.',
        ])->assertUnprocessable()->assertJsonPath('message', 'CONFLICT_OF_INTEREST');

        $this->actingAs($lead)->postJson("/api/admin/v1/listing-center/applications/{$application->reference}/review-assignments", [
            'review_domain' => 'SECURITY',
            'reviewer_admin_id' => $reviewer->id,
            'reason' => 'Assign independent security reviewer.',
        ])->assertCreated();

        $this->actingAs($owner)->post("/api/listing/applications/{$application->reference}/documents", [
            'document_type' => 'WHITEPAPER',
            'file' => UploadedFile::fake()->create('audit.pdf', 10, 'application/pdf'),
        ])->assertCreated();

        $application->refresh();
        $this->assertGreaterThan($oldRevision, (int) $application->application_revision);

        $this->actingAs($reviewer)->postJson("/api/admin/v1/listing-center/applications/{$application->reference}/reviews", [
            'review_type' => 'SECURITY',
            'status' => 'PASSED',
            'expected_revision' => $oldRevision,
            'notes' => 'Attempt to approve a stale document/application version.',
        ])->assertUnprocessable()->assertJsonPath('message', 'STALE_REVISION');
    }

    public function test_kyb_person_change_after_approval_invalidates_current_kyb_approval(): void
    {
        [$owner, $application] = $this->submittedApplication();
        $organization = $application->organization;

        $this->actingAs($owner)->postJson('/api/listing/organizations/'.$organization->id.'/kyb/people', [
            'person_type' => 'UBO',
            'full_legal_name' => 'Updated Beneficial Owner',
            'ownership_percentage' => 25,
            'nationality' => 'NG',
            'residence_country' => 'NG',
        ])->assertCreated();

        $organization->refresh();
        $this->assertSame('REVERIFICATION_REQUIRED', $organization->kyb_status);
        $this->assertDatabaseHas('listing_audit_logs', [
            'action' => 'listing.kyb.approval.invalidated',
            'resource_type' => 'listing_organization',
            'resource_id' => $organization->id,
        ]);
    }

    private function submittedApplication(): array
    {
        $owner = User::factory()->create();
        $organization = ListingOrganization::query()->create([
            'organization_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'owner_user_id' => $owner->id,
            'legal_name' => 'P1 Governance Labs',
            'project_name' => 'Governed Token',
            'jurisdiction' => 'NG',
            'kyb_status' => 'APPROVED',
        ]);
        $organization->teamMembers()->create([
            'email' => $owner->email,
            'user_id' => $owner->id,
            'role' => 'OWNER',
            'status' => 'ACTIVE',
        ]);

        $application = ListingApplication::query()->create([
            'application_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'EXA-LIST-P1-'.strtoupper(\Illuminate\Support\Str::random(6)),
            'organization_id' => $organization->id,
            'submitted_by' => $owner->id,
            'application_type' => 'NEW_TOKEN_LISTING',
            'application_status' => 'SUBMITTED',
            'integration_status' => 'NOT_STARTED',
            'application_revision' => 2,
            'progress_percent' => 100,
            'project_information' => ['name' => 'Governed Token'],
            'asset_information' => ['name' => 'Governed Token', 'symbol' => 'GOV'],
            'blockchain_information' => ['network' => 'ETHEREUM', 'contract_address' => '0x0000000000000000000000000000000000000d01'],
            'tokenomics' => ['total_supply' => '1000000'],
            'technology' => ['repository' => 'https://github.com/example/governed'],
            'security' => ['audit_reports' => ['https://audit.example/governed.pdf']],
            'legal_compliance' => ['legal_opinion' => 'provided'],
            'market_community' => ['website' => 'https://governed.example'],
            'liquidity' => ['market_maker' => 'institutional'],
            'listing_request' => ['requested_pairs' => ['GOV/USDT']],
            'risk_flags' => [],
        ]);

        return [$owner, $application];
    }

    private function admin(string $email, array $permissions, ?User $canonicalUser = null): Admin
    {
        $role = Role::query()->create(['name' => 'listing-p1-'.md5($email)]);
        $ids = collect($permissions)
            ->map(fn (string $name): int => Permission::query()->firstOrCreate(['name' => $name])->id)
            ->all();
        $role->permissions()->sync($ids);

        return Admin::query()->create([
            'name' => 'Listing P1 Admin',
            'canonical_user_id' => $canonicalUser?->id,
            'email' => $email,
            'password' => Hash::make('StrongPassword123!'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }
}
