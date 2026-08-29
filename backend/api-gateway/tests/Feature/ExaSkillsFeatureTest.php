<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\SkillsCategory;
use App\Models\SkillsChallenge;
use App\Models\SkillsCredential;
use App\Models\SkillsInstructorEarning;
use App\Models\SkillsInstructorPayout;
use App\Models\SkillsMediaAsset;
use App\Models\SkillsOpportunity;
use App\Models\SkillsOrganization;
use App\Models\SkillsOrganizationMember;
use App\Models\SkillsSubscription;
use App\Models\SkillsTaxPolicy;
use App\Services\AccountClosureSafetyService;
use App\Models\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExaSkillsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_exaskills_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/exaskills/home')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.supported.courses', true)
            ->assertJsonPath('data.supported.paid_course_settlement', true);
    }

    public function test_courses_endpoint_returns_published_courses_with_categories(): void
    {
        $user = User::factory()->create();
        $category = SkillsCategory::query()->firstOrCreate(
            ['slug' => 'software-development'],
            ['name' => 'Software Development', 'is_active' => true]
        );

        Course::query()->create([
            'created_by' => $user->id,
            'category_id' => $category->id,
            'title' => 'React Portfolio Builder',
            'slug' => 'react-portfolio-builder',
            'instructor_name' => 'ExaEarn Skills Team',
            'description' => 'Build a production portfolio dashboard.',
            'difficulty' => 'beginner',
            'duration' => 240,
            'price' => '0.00000000',
            'settlement_asset' => 'USDT',
            'status' => 'published',
            'credential_available' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/exaskills/courses?search=react')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.title', 'React Portfolio Builder')
            ->assertJsonPath('data.data.0.category.slug', 'software-development');
    }

    public function test_user_can_enroll_in_free_course_once(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create([
            'created_by' => $user->id,
            'title' => 'AI Automation Basics',
            'slug' => 'ai-automation-basics',
            'description' => 'Learn practical automation workflows.',
            'difficulty' => 'beginner',
            'duration' => 120,
            'price' => '0.00000000',
            'settlement_asset' => 'USDT',
            'status' => 'published',
            'credential_available' => false,
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson("/api/exaskills/courses/{$course->slug}/enroll")
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.course_id', $course->id);

        $this->actingAs($user)
            ->postJson("/api/exaskills/courses/{$course->slug}/enroll")
            ->assertCreated()
            ->assertJsonPath('data.course_id', $course->id);

        $this->assertDatabaseCount('course_enrollments', 1);
    }

    public function test_paid_course_requires_payment_ledger_before_enrollment(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create([
            'created_by' => $user->id,
            'title' => 'Premium Web3 Builder',
            'slug' => 'premium-web3-builder',
            'description' => 'Paid premium course.',
            'difficulty' => 'intermediate',
            'duration' => 320,
            'price' => '50.00000000',
            'settlement_asset' => 'USDT',
            'status' => 'published',
            'credential_available' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson("/api/exaskills/courses/{$course->slug}/enroll")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_user_can_submit_instructor_application(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/exaskills/instructors/apply', [
                'display_name' => 'Ada Skills',
                'headline' => 'Senior product engineer',
                'bio' => 'I teach practical engineering skills.',
                'expertise' => ['React', 'Web3'],
                'portfolio_links' => ['https://example.com'],
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('instructor_profiles', [
            'user_id' => $user->id,
            'display_name' => 'Ada Skills',
            'status' => 'pending',
        ]);
    }
    public function test_user_can_submit_challenge_project(): void
    {
        $user = User::factory()->create();
        $challenge = SkillsChallenge::query()->create([
            'title' => 'Build a React Crypto Portfolio Dashboard',
            'slug' => 'react-crypto-portfolio-dashboard',
            'sponsor_name' => 'ExaEarn Labs',
            'description' => 'Create a portfolio dashboard with real user flows.',
            'reward_amount' => '300.00000000',
            'reward_asset' => 'USDT',
            'difficulty' => 'intermediate',
            'status' => 'open',
            'deadline_at' => now()->addDays(7),
        ]);

        $this->actingAs($user)
            ->postJson("/api/exaskills/challenges/{$challenge->slug}/submissions", [
                'description' => 'Submitted implementation.',
                'repository_url' => 'https://example.com/repo',
                'demo_url' => 'https://example.com/demo',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('skills_challenge_submissions', [
            'challenge_id' => $challenge->id,
            'user_id' => $user->id,
            'status' => 'submitted',
        ]);
    }

    public function test_user_can_apply_to_open_opportunity(): void
    {
        $user = User::factory()->create();
        $opportunity = SkillsOpportunity::query()->create([
            'company_name' => 'ExaEarn Talent',
            'title' => 'Frontend Contract Developer',
            'slug' => 'frontend-contract-developer',
            'type' => 'contract',
            'description' => 'Build professional trading interfaces.',
            'compensation_label' => '$1,500 fixed',
            'location_type' => 'remote',
            'status' => 'open',
            'deadline_at' => now()->addDays(14),
        ]);

        $this->actingAs($user)
            ->postJson("/api/exaskills/opportunities/{$opportunity->slug}/applications", [
                'cover_note' => 'I can build this.',
                'portfolio_url' => 'https://example.com/portfolio',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('skills_applications', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $user->id,
            'status' => 'submitted',
        ]);
    }

    public function test_public_credential_verification_returns_safe_record(): void
    {
        $user = User::factory()->create(['name' => 'Verified Learner']);
        $course = Course::query()->create([
            'created_by' => $user->id,
            'title' => 'Credential Course',
            'slug' => 'credential-course',
            'description' => 'Course with credential.',
            'difficulty' => 'beginner',
            'duration' => 60,
            'price' => '0.00000000',
            'settlement_asset' => 'USDT',
            'status' => 'published',
            'credential_available' => true,
            'published_at' => now(),
        ]);

        SkillsCredential::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'credential_code' => 'EXASKILLS-CRED-001',
            'title' => 'Verified React Builder',
            'skills' => ['React', 'UI'],
            'status' => 'verified',
            'issued_at' => now(),
            'verification_hash' => 'verify-hash-001',
        ]);

        $this->getJson('/api/exaskills/verify/EXASKILLS-CRED-001')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Verified React Builder')
            ->assertJsonPath('data.course.title', 'Credential Course');
    }

    public function test_admin_exaskills_overview_returns_operational_counts(): void
    {
        $role = Role::query()->create(['name' => 'admin']);
        $admin = Admin::query()->create([
            'name' => 'Skills Admin',
            'email' => 'skills-admin@example.com',
            'password' => Hash::make('StrongPassword123!'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/exaskills')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['learners', 'published_courses', 'open_challenges', 'open_opportunities', 'revenue_enabled']]);
    }
    public function test_paid_course_purchase_uses_double_entry_ledger_and_creates_enrollment(): void
    {
        $buyer = User::factory()->create();
        $instructor = User::factory()->create();
        Account::query()->create(['user_id' => $buyer->id, 'account_type' => 'funding', 'asset' => 'USDT', 'balance' => '100.000000000000000000']);

        $course = Course::query()->create([
            'created_by' => $instructor->id,
            'title' => 'Professional Product Design',
            'slug' => 'professional-product-design',
            'description' => 'Paid course.',
            'difficulty' => 'intermediate',
            'duration' => 300,
            'price' => '50.00000000',
            'settlement_asset' => 'USDT',
            'status' => 'published',
            'credential_available' => true,
            'published_at' => now(),
            'metadata' => ['platform_commission_rate' => '0.200000'],
        ]);

        $this->actingAs($buyer)
            ->withHeader('Idempotency-Key', 'skills-course-buy-1')
            ->postJson("/api/exaskills/courses/{$course->slug}/purchase")
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.gross_amount', '50.00000000')
            ->assertJsonPath('data.platform_fee_amount', '10.00000000')
            ->assertJsonPath('data.instructor_amount', '40.00000000');

        $this->assertDatabaseHas('course_enrollments', ['user_id' => $buyer->id, 'course_id' => $course->id]);
        $this->assertDatabaseHas('skills_instructor_earnings', ['instructor_user_id' => $instructor->id, 'net_amount' => '40.00000000']);
        $this->assertSame('50.000000000000000000', (string) Account::query()->where('user_id', $buyer->id)->where('account_type', 'funding')->where('asset', 'USDT')->firstOrFail()->balance);
    }

    public function test_challenge_escrow_can_be_funded_and_paid_to_winner_by_admin(): void
    {
        $sponsor = User::factory()->create();
        $winner = User::factory()->create();
        Account::query()->create(['user_id' => $sponsor->id, 'account_type' => 'funding', 'asset' => 'USDT', 'balance' => '500.000000000000000000']);

        $challenge = SkillsChallenge::query()->create([
            'sponsor_user_id' => $sponsor->id,
            'title' => 'Build Trading Education Tool',
            'slug' => 'build-trading-education-tool',
            'sponsor_name' => 'ExaEarn Labs',
            'description' => 'Challenge with escrow.',
            'reward_amount' => '300.00000000',
            'reward_asset' => 'USDT',
            'difficulty' => 'advanced',
            'status' => 'draft',
        ]);

        $this->actingAs($sponsor)
            ->withHeader('Idempotency-Key', 'skills-escrow-1')
            ->postJson("/api/exaskills/challenges/{$challenge->slug}/fund")
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'funded');

        $role = Role::query()->create(['name' => 'admin']);
        $admin = Admin::query()->create([
            'name' => 'Skills Admin',
            'email' => 'skills-payout-admin@example.com',
            'password' => Hash::make('StrongPassword123!'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);

        DB::table('skills_challenge_submissions')->insert([
            'challenge_id' => $challenge->id,
            'user_id' => $winner->id,
            'description' => 'Winning project',
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->postJson("/api/admin/exaskills/challenges/{$challenge->slug}/payout-winner", ['winner_user_id' => $winner->id])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.winner_user_id', $winner->id);

        $this->assertSame('300.000000000000000000', (string) Account::query()->where('user_id', $winner->id)->where('account_type', 'funding')->where('asset', 'USDT')->firstOrFail()->balance);
    }
    public function test_paid_instructor_cannot_purchase_own_course(): void
    {
        $instructor = User::factory()->create();
        Account::query()->create(['user_id' => $instructor->id, 'account_type' => 'funding', 'asset' => 'USDT', 'balance' => '100.000000000000000000']);

        $course = Course::query()->create([
            'created_by' => $instructor->id,
            'title' => 'Own Premium Course',
            'slug' => 'own-premium-course',
            'description' => 'Paid course.',
            'difficulty' => 'advanced',
            'duration' => 120,
            'price' => '25.00000000',
            'settlement_asset' => 'USDT',
            'status' => 'published',
            'credential_available' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($instructor)
            ->postJson("/api/exaskills/courses/{$course->slug}/purchase")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_only_challenge_sponsor_can_fund_escrow(): void
    {
        $sponsor = User::factory()->create();
        $otherUser = User::factory()->create();
        Account::query()->create(['user_id' => $otherUser->id, 'account_type' => 'funding', 'asset' => 'USDT', 'balance' => '500.000000000000000000']);

        $challenge = SkillsChallenge::query()->create([
            'sponsor_user_id' => $sponsor->id,
            'title' => 'Sponsor Only Challenge',
            'slug' => 'sponsor-only-challenge',
            'sponsor_name' => 'Sponsor Co',
            'description' => 'Escrow test.',
            'reward_amount' => '100.00000000',
            'reward_asset' => 'USDT',
            'difficulty' => 'intermediate',
            'status' => 'draft',
        ]);

        $this->actingAs($otherUser)
            ->postJson("/api/exaskills/challenges/{$challenge->slug}/fund")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('skills_challenge_escrows', ['challenge_id' => $challenge->id]);
    }

    public function test_challenge_winner_must_have_submission_before_payout(): void
    {
        $sponsor = User::factory()->create();
        $winner = User::factory()->create();
        Account::query()->create(['user_id' => $sponsor->id, 'account_type' => 'funding', 'asset' => 'USDT', 'balance' => '500.000000000000000000']);

        $challenge = SkillsChallenge::query()->create([
            'sponsor_user_id' => $sponsor->id,
            'title' => 'Submission Required Challenge',
            'slug' => 'submission-required-challenge',
            'sponsor_name' => 'ExaEarn Labs',
            'description' => 'Challenge with escrow.',
            'reward_amount' => '150.00000000',
            'reward_asset' => 'USDT',
            'difficulty' => 'advanced',
            'status' => 'draft',
        ]);

        $this->actingAs($sponsor)
            ->postJson("/api/exaskills/challenges/{$challenge->slug}/fund")
            ->assertCreated();

        $role = Role::query()->create(['name' => 'admin']);
        $admin = Admin::query()->create([
            'name' => 'Skills Admin',
            'email' => 'skills-submission-admin@example.com',
            'password' => Hash::make('StrongPassword123!'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->postJson("/api/admin/exaskills/challenges/{$challenge->slug}/payout-winner", ['winner_user_id' => $winner->id])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_instructor_course_media_review_publish_progress_assessment_and_credential_flow(): void
    {
        Storage::fake('local');
        $instructor = User::factory()->create(['kyc_level' => 2]);
        $this->actingAs($instructor)->postJson('/api/exaskills/instructors/apply', [
            'display_name' => 'Production Instructor',
            'headline' => 'Teaches exchange product design',
            'bio' => 'Experienced product instructor.',
        ])->assertCreated();
        DB::table('instructor_profiles')->where('user_id', $instructor->id)->update(['status' => 'approved', 'approved_at' => now()]);

        $course = $this->actingAs($instructor)->postJson('/api/exaskills/courses', [
            'title' => 'Exchange UX Production',
            'description' => 'Build trustworthy exchange user experiences.',
            'price' => '0',
            'credential_available' => true,
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data');

        $lesson = $this->actingAs($instructor)->postJson("/api/exaskills/courses/{$course['id']}/lessons", [
            'title' => 'Information hierarchy',
            'content' => 'A lesson with actual server-side progress.',
            'minimum_watch_seconds' => 5,
        ])->assertCreated()->json('data');

        $media = $this->actingAs($instructor)->post("/api/exaskills/courses/{$course['id']}/media", [
            'lesson_id' => $lesson['id'],
            'asset_type' => 'LESSON_DOCUMENT',
            'visibility' => 'private',
            'media' => UploadedFile::fake()->create('lesson.pdf', 64, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('data.processing_state', 'READY')->json('data');

        $learner = User::factory()->create();
        $this->actingAs($learner)->get("/api/exaskills/media/{$media['id']}")->assertForbidden();
        $this->actingAs($instructor)->postJson("/api/exaskills/courses/{$course['id']}/submit-review")->assertOk()->assertJsonPath('data.status', 'ready_for_review');

        $admin = $this->admin('skills-review-admin@example.com');
        $this->actingAs($admin)->postJson("/api/admin/exaskills/courses/{$course['id']}/review", [
            'action' => 'APPROVE',
            'reason' => 'Course content and media are acceptable.',
        ])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->actingAs($instructor)->postJson("/api/exaskills/courses/{$course['id']}/publish")->assertOk()->assertJsonPath('data.status', 'published');

        $this->actingAs($learner)->postJson("/api/exaskills/courses/{$course['id']}/enroll")->assertCreated();
        $this->actingAs($learner)->get("/api/exaskills/media/{$media['id']}")->assertOk();

        $quiz = Quiz::query()->create(['course_id' => $course['id'], 'passing_score' => 70, 'time_limit' => 600, 'max_attempts' => 2, 'metadata' => ['version' => 1]]);
        $question = QuizQuestion::query()->create(['quiz_id' => $quiz->id, 'question' => 'What controls the final grade?', 'options' => ['Server', 'Browser'], 'correct_answer' => 'Server', 'order_index' => 1]);

        $this->actingAs($learner)->postJson("/api/exaskills/courses/{$course['id']}/lessons/{$lesson['id']}/complete", ['watch_seconds' => 5])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->actingAs($learner)->withHeader('Idempotency-Key', 'assessment-1')->postJson("/api/exaskills/courses/{$course['id']}/assessment/attempts", [
            'answers' => [(string) $question->id => 'Server'],
        ])->assertCreated()->assertJsonPath('data.passed', true);
        $this->actingAs($learner)->withHeader('Idempotency-Key', 'assessment-1')->postJson("/api/exaskills/courses/{$course['id']}/assessment/attempts", [
            'answers' => [(string) $question->id => 'Browser'],
        ])->assertCreated()->assertJsonPath('data.passed', true);

        $this->assertDatabaseHas('course_enrollments', ['user_id' => $learner->id, 'course_id' => $course['id'], 'completed' => true]);
        $credential = SkillsCredential::query()->where('user_id', $learner->id)->where('course_id', $course['id'])->firstOrFail();
        $this->getJson("/api/exaskills/verify/{$credential->credential_code}")->assertOk()->assertJsonPath('data.status', 'verified');
        $this->actingAs($admin)->postJson("/api/admin/exaskills/credentials/{$credential->credential_code}/revoke", ['reason' => 'Test revocation'])->assertOk()->assertJsonPath('data.status', 'revoked');
    }

    public function test_instructor_payout_is_idempotent_ledger_backed_and_reconciles(): void
    {
        $instructor = User::factory()->create();
        SkillsInstructorEarning::query()->create([
            'instructor_user_id' => $instructor->id,
            'asset' => 'USDT',
            'gross_amount' => '100.00000000',
            'platform_fee_amount' => '20.00000000',
            'net_amount' => '80.00000000',
            'status' => 'pending',
            'reference' => 'skills-earning-payout-test',
        ]);
        Account::query()->create(['user_id' => $instructor->id, 'account_type' => 'skills_instructor_payable', 'asset' => 'USDT', 'balance' => '80.000000000000000000']);

        $payout = $this->actingAs($instructor)->withHeader('Idempotency-Key', 'skills-payout-1')->postJson('/api/exaskills/instructors/payouts', [
            'asset' => 'USDT',
            'amount' => '80',
        ])->assertCreated()->assertJsonPath('data.status', 'REQUESTED')->json('data');
        $this->actingAs($instructor)->withHeader('Idempotency-Key', 'skills-payout-1')->postJson('/api/exaskills/instructors/payouts', [
            'asset' => 'USDT',
            'amount' => '80',
        ])->assertCreated()->assertJsonPath('data.id', $payout['id']);

        $admin = $this->admin('skills-payout-admin-2@example.com');
        $this->actingAs($admin)->postJson("/api/admin/exaskills/instructor-payouts/{$payout['id']}/approve")->assertOk()->assertJsonPath('data.status', 'COMPLETED');
        $this->actingAs($admin)->postJson("/api/admin/exaskills/instructor-payouts/{$payout['id']}/approve")->assertOk()->assertJsonPath('data.status', 'COMPLETED');
        $this->assertSame('80.000000000000000000', (string) Account::query()->where('user_id', $instructor->id)->where('account_type', 'funding')->where('asset', 'USDT')->firstOrFail()->balance);
        $this->actingAs($admin)->getJson('/api/admin/exaskills/reconciliation')->assertOk()->assertJsonPath('data.status', 'PASS');
        $this->assertDatabaseCount('skills_instructor_payouts', 1);
    }

    public function test_subscription_activation_cancellation_expiry_and_entitlement_are_server_authoritative(): void
    {
        $learner = User::factory()->create();
        $instructor = User::factory()->create();
        $course = Course::query()->create([
            'created_by' => $instructor->id,
            'title' => 'Subscription Course',
            'slug' => 'subscription-course',
            'description' => 'Subscription eligible course.',
            'difficulty' => 'beginner',
            'duration' => 60,
            'price' => '25.00000000',
            'settlement_asset' => 'USDT',
            'status' => 'published',
            'credential_available' => true,
            'published_at' => now(),
        ]);
        Account::query()->create(['user_id' => $learner->id, 'account_type' => 'funding', 'asset' => 'USDT', 'balance' => '100.000000000000000000']);

        $first = $this->actingAs($learner)->withHeader('Idempotency-Key', 'skills-sub-1')->postJson('/api/exaskills/subscriptions', [
            'plan_code' => 'INDIVIDUAL',
            'billing_cycle' => 'monthly',
        ])->assertCreated()->assertJsonPath('data.status', 'ACTIVE')->json('data');
        $this->actingAs($learner)->withHeader('Idempotency-Key', 'skills-sub-1')->postJson('/api/exaskills/subscriptions', [
            'plan_code' => 'INDIVIDUAL',
            'billing_cycle' => 'monthly',
        ])->assertCreated()->assertJsonPath('data.id', $first['id']);

        $this->actingAs($learner)->get("/api/exaskills/media/999999")->assertNotFound();
        $this->assertTrue(app(\App\Services\ExaSkillsService::class)->hasCourseEntitlement($learner, $course));

        $this->actingAs($learner)->postJson("/api/exaskills/subscriptions/{$first['id']}/cancel", ['at_period_end' => false])->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        SkillsSubscription::query()->whereKey($first['id'])->update(['status' => 'ACTIVE', 'ends_at' => now()->subMinute()]);
        $this->assertSame(1, app(\App\Services\ExaSkillsService::class)->expireSubscriptions());
        $this->assertFalse(app(\App\Services\ExaSkillsService::class)->hasCourseEntitlement($learner->fresh(), $course));
    }

    public function test_tax_policy_profile_and_withholding_software_are_auditable_without_claiming_external_review(): void
    {
        $instructor = User::factory()->create();
        $this->actingAs($instructor)->postJson('/api/exaskills/instructors/apply', [
            'display_name' => 'Tax Instructor',
            'headline' => 'Instructor',
            'bio' => 'Instructor profile.',
        ])->assertCreated();

        $this->actingAs($instructor)->postJson('/api/exaskills/instructors/tax-profile', [
            'legal_name' => 'Tax Instructor LLC',
            'entity_type' => 'BUSINESS',
            'country' => 'US',
            'tax_residency' => 'US',
            'tax_identifier' => 'private-tax-id',
        ])->assertOk()->assertJsonPath('data.tax_verification_status', 'PENDING');

        $admin = $this->admin('skills-tax-admin@example.com');
        $policy = $this->actingAs($admin)->postJson('/api/admin/exaskills/tax-policies', [
            'country' => 'US',
            'entity_type' => 'BUSINESS',
            'outcome' => 'WITHHOLD',
            'withholding_rate' => '0.10000000',
            'policy_version' => 'test-policy-v1',
            'status' => 'APPROVED',
        ])->assertCreated()->assertJsonPath('data.metadata.external_tax_review_required', true)->json('data');

        $profile = \App\Models\InstructorProfile::query()->where('user_id', $instructor->id)->firstOrFail();
        $decision = app(\App\Services\ExaSkillsService::class)->taxDecision($profile, 'USDT', '100.00000000');
        $this->assertSame('WITHHOLD', $decision['outcome']);
        $this->assertSame('10.00000000', $decision['withholding_amount']);
        $this->assertDatabaseHas('skills_tax_policies', ['id' => $policy['id'], 'policy_version' => 'test-policy-v1']);
        $this->assertDatabaseMissing('instructor_profiles', ['tax_identifier_hash' => 'private-tax-id']);
    }

    public function test_business_training_seats_assignments_and_employer_moderation(): void
    {
        $owner = User::factory()->create();
        $learner = User::factory()->create();
        $course = Course::query()->create([
            'created_by' => $owner->id,
            'title' => 'Business Training Course',
            'slug' => 'business-training-course',
            'description' => 'Course for teams.',
            'difficulty' => 'intermediate',
            'duration' => 60,
            'price' => '0.00000000',
            'settlement_asset' => 'USDT',
            'status' => 'published',
            'credential_available' => true,
            'published_at' => now(),
        ]);

        $organization = $this->actingAs($owner)->postJson('/api/exaskills/business/organizations', [
            'name' => 'ExaSkills Business',
            'country' => 'US',
            'industry' => 'Fintech',
        ])->assertCreated()->assertJsonPath('data.status', 'PENDING_REVIEW')->json('data');
        SkillsOrganization::query()->whereKey($organization['id'])->update(['status' => 'APPROVED', 'kyb_status' => 'APPROVED']);
        SkillsOrganizationMember::query()->create(['organization_id' => $organization['id'], 'user_id' => $learner->id, 'email' => $learner->email, 'role' => 'LEARNER', 'status' => 'ACTIVE', 'accepted_at' => now()]);

        $this->actingAs($owner)->postJson("/api/exaskills/business/organizations/{$organization['id']}/seats", ['count' => 1])->assertCreated()->assertJsonPath('data.created', 1);
        $this->actingAs($owner)->postJson("/api/exaskills/business/organizations/{$organization['id']}/programs", [
            'title' => 'Exchange Onboarding',
            'required_course_ids' => [$course->id],
        ])->assertCreated()->assertJsonPath('data.status', 'ACTIVE');

        app(\App\Services\ExaSkillsService::class)->assignCourse($owner, SkillsOrganization::query()->findOrFail($organization['id']), $course, $learner);
        $this->assertTrue(app(\App\Services\ExaSkillsService::class)->hasCourseEntitlement($learner, $course));

        $opportunity = $this->actingAs($owner)->postJson("/api/exaskills/business/organizations/{$organization['id']}/opportunities", [
            'title' => 'Verified Product Designer',
            'description' => 'Work on verified fintech learning products.',
            'required_skills' => ['Product Design'],
            'required_credentials' => ['EXASKILLS'],
        ])->assertCreated()->assertJsonPath('data.status', 'UNDER_REVIEW')->json('data');
        $admin = $this->admin('skills-employer-admin@example.com');
        $this->actingAs($admin)->postJson("/api/admin/exaskills/opportunities/{$opportunity['id']}/moderate", [
            'action' => 'PUBLISH',
            'reason' => 'Legitimate verified employer opportunity.',
        ])->assertOk()->assertJsonPath('data.status', 'open');
    }

    public function test_account_closure_blocks_active_exaskills_obligations(): void
    {
        $user = User::factory()->create();
        SkillsSubscription::query()->create(['user_id' => $user->id, 'plan_code' => 'INDIVIDUAL', 'status' => 'ACTIVE', 'billing_cycle' => 'monthly', 'amount' => '0', 'settlement_asset' => 'USDT', 'starts_at' => now(), 'ends_at' => now()->addMonth()]);

        $readiness = app(AccountClosureSafetyService::class)->readiness($user->id);
        $this->assertFalse($readiness['can_close']);
        $this->assertContains('active_subscription', array_column($readiness['blockers'], 'reason'));
    }

    private function admin(string $email): Admin
    {
        $role = Role::query()->create(['name' => 'skills-admin-'.str()->random(6)]);

        return Admin::query()->create([
            'name' => 'Skills Admin',
            'email' => $email,
            'password' => Hash::make('StrongPassword123!'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }
}
