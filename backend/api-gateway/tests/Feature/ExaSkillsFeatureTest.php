<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Admin;
use App\Models\Course;
use App\Models\SkillsCategory;
use App\Models\SkillsChallenge;
use App\Models\SkillsCredential;
use App\Models\SkillsOpportunity;
use App\Models\Role;
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
}
