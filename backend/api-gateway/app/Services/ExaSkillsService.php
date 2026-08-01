<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\InstructorProfile;
use App\Models\SkillsCategory;
use App\Models\SkillsChallenge;
use App\Models\SkillsChallengeEscrow;
use App\Models\SkillsCoursePurchase;
use App\Models\SkillsInstructorEarning;
use App\Models\SkillsCredential;
use App\Models\SkillsOpportunity;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExaSkillsService
{
    private const SCALE = 8;

    public function __construct(private readonly LedgerService $ledger)
    {
    }

    public function home(?User $user = null): array
    {
        return [
            'summary' => $this->summary($user),
            'continue_learning' => $user ? $this->continueLearning($user) : [],
            'categories' => $this->categories(),
            'featured_courses' => $this->courseQuery(['featured' => true])->limit(8)->get(),
            'challenges' => SkillsChallenge::query()
                ->whereIn('status', ['open', 'judging'])
                ->orderByRaw('deadline_at is null, deadline_at asc')
                ->limit(6)
                ->get(),
            'opportunities' => SkillsOpportunity::query()
                ->where('status', 'open')
                ->latest()
                ->limit(6)
                ->get(),
            'instructor_profile' => $user ? InstructorProfile::query()->where('user_id', $user->id)->first() : null,
            'credentials' => $user ? SkillsCredential::query()->where('user_id', $user->id)->latest('issued_at')->limit(4)->get() : [],
            'supported' => [
                'courses' => true,
                'lessons' => true,
                'enrollments' => true,
                'certificates' => true,
                'categories' => true,
                'challenges' => true,
                'opportunities' => true,
                'instructor_profiles' => true,
                'paid_course_settlement' => true,
                'challenge_escrow_settlement' => true,
                'business_portal' => false,
            ],
        ];
    }

    public function categories(): Collection
    {
        return SkillsCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function courses(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->courseQuery($filters)->paginate(max(1, min($perPage, 50)));
    }

    public function course(int|string $idOrSlug): Course
    {
        return Course::query()
            ->with(['category', 'lessons'])
            ->when(ctype_digit((string) $idOrSlug),
                fn ($query) => $query->where('id', (int) $idOrSlug),
                fn ($query) => $query->where('slug', (string) $idOrSlug)
            )
            ->whereIn('status', ['published', 'active'])
            ->firstOrFail();
    }

    public function enroll(User $user, Course $course, ?string $idempotencyKey = null): CourseEnrollment
    {
        if (!in_array($course->status, ['published', 'active'], true)) {
            throw new RuntimeException('This course is not currently available.');
        }

        if ((float) $course->price > 0) {
            throw new RuntimeException('Paid course checkout is not enabled yet. Please use a free course or connect the course payment ledger.');
        }

        return DB::transaction(function () use ($user, $course, $idempotencyKey): CourseEnrollment {
            return CourseEnrollment::query()->firstOrCreate(
                ['user_id' => $user->id, 'course_id' => $course->id],
                [
                    'progress_percentage' => '0.00',
                    'completed' => false,
                    'watch_seconds' => 0,
                    'last_unlocked_lesson_order' => 1,
                    'progress_metadata' => ['source' => 'exaskills', 'idempotency_key' => $idempotencyKey],
                ]
            );
        });
    }

    public function myDashboard(User $user): array
    {
        $enrollments = CourseEnrollment::query()
            ->with('course.category')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return [
            'learning' => $enrollments,
            'overview' => [
                'courses_in_progress' => $enrollments->where('completed', false)->count(),
                'courses_completed' => $enrollments->where('completed', true)->count(),
                'credentials_earned' => SkillsCredential::query()->where('user_id', $user->id)->count(),
                'challenges_entered' => DB::table('skills_challenge_submissions')->where('user_id', $user->id)->count(),
                'applications' => DB::table('skills_applications')->where('user_id', $user->id)->count(),
            ],
            'credentials' => SkillsCredential::query()->where('user_id', $user->id)->latest('issued_at')->limit(10)->get(),
        ];
    }

    public function applyInstructor(User $user, array $payload): InstructorProfile
    {
        return DB::transaction(function () use ($user, $payload): InstructorProfile {
            return InstructorProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'display_name' => $payload['display_name'],
                    'headline' => $payload['headline'] ?? null,
                    'bio' => $payload['bio'] ?? null,
                    'expertise' => $payload['expertise'] ?? [],
                    'portfolio_links' => $payload['portfolio_links'] ?? [],
                    'status' => 'pending',
                ]
            );
        });
    }

    public function purchaseCourse(User $user, Course $course, ?string $idempotencyKey = null): SkillsCoursePurchase
    {
        if (!in_array($course->status, ['published', 'active'], true)) {
            throw new RuntimeException('This course is not currently available.');
        }

        $price = $this->fmt((string) ($course->price ?? '0'));
        if ($this->compare($price, '0') <= 0) {
            $this->enroll($user, $course, $idempotencyKey);

            return SkillsCoursePurchase::query()->firstOrCreate(
                ['user_id' => $user->id, 'course_id' => $course->id],
                [
                    'asset' => strtoupper((string) ($course->settlement_asset ?? 'USDT')),
                    'gross_amount' => '0.00000000',
                    'platform_fee_amount' => '0.00000000',
                    'instructor_amount' => '0.00000000',
                    'commission_rate' => '0.000000',
                    'status' => 'completed',
                    'reference' => 'SKILLS-FREE-' . $user->id . '-' . $course->id,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => ['type' => 'free_enrollment'],
                ]
            );
        }

        if ($idempotencyKey) {
            $existing = SkillsCoursePurchase::query()->where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $asset = strtoupper((string) ($course->settlement_asset ?? 'USDT'));
        if ((int) $course->created_by === (int) $user->id) {
            throw new RuntimeException('You cannot purchase your own paid course.');
        }

        $reference = 'SKILLS-COURSE-' . $user->id . '-' . $course->id . '-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);
        $commissionRate = $this->platformCommissionRate($course);
        $platformFee = $this->mul($price, $commissionRate);
        $instructorAmount = $this->sub($price, $platformFee);

        return DB::transaction(function () use ($user, $course, $asset, $price, $platformFee, $instructorAmount, $commissionRate, $reference, $idempotencyKey): SkillsCoursePurchase {
            if (SkillsCoursePurchase::query()->where('user_id', $user->id)->where('course_id', $course->id)->lockForUpdate()->exists()) {
                return SkillsCoursePurchase::query()->where('user_id', $user->id)->where('course_id', $course->id)->firstOrFail();
            }

            $buyerFunding = $this->ledger->getOrCreateAccount($user->id, 'funding', $asset);
            $platformRevenue = $this->ledger->getOrCreateAccount(null, 'skills_platform_revenue', $asset);
            $instructorPayable = $this->ledger->getOrCreateAccount((int) $course->created_by, 'skills_instructor_payable', $asset);

            $this->ledger->postDoubleEntry($reference, 'ExaSkills course purchase', [
                ['account_id' => $buyerFunding->id, 'amount' => $this->sub('0', $price), 'asset' => $asset, 'user_id' => $user->id, 'metadata' => ['course_id' => $course->id]],
                ['account_id' => $platformRevenue->id, 'amount' => $platformFee, 'asset' => $asset, 'metadata' => ['course_id' => $course->id]],
                ['account_id' => $instructorPayable->id, 'amount' => $instructorAmount, 'asset' => $asset, 'user_id' => (int) $course->created_by, 'metadata' => ['course_id' => $course->id]],
            ], 'skills_course_purchase', ['source' => 'exaskills']);

            $purchase = SkillsCoursePurchase::query()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'asset' => $asset,
                'gross_amount' => $price,
                'platform_fee_amount' => $platformFee,
                'instructor_amount' => $instructorAmount,
                'commission_rate' => $commissionRate,
                'status' => 'completed',
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'metadata' => ['ledger_reference' => $reference],
            ]);

            SkillsInstructorEarning::query()->create([
                'instructor_user_id' => (int) $course->created_by,
                'course_id' => $course->id,
                'purchase_id' => $purchase->id,
                'asset' => $asset,
                'gross_amount' => $price,
                'platform_fee_amount' => $platformFee,
                'net_amount' => $instructorAmount,
                'status' => 'pending',
                'reference' => $reference . '-EARN',
                'metadata' => ['purchase_reference' => $reference],
            ]);

            CourseEnrollment::query()->firstOrCreate(
                ['user_id' => $user->id, 'course_id' => $course->id],
                ['progress_percentage' => '0.00', 'completed' => false, 'watch_seconds' => 0, 'last_unlocked_lesson_order' => 1, 'progress_metadata' => ['purchase_reference' => $reference]]
            );

            return $purchase;
        });
    }

    public function fundChallengeEscrow(User $sponsor, int|string $idOrSlug, ?string $idempotencyKey = null): SkillsChallengeEscrow
    {
        $challenge = SkillsChallenge::query()
            ->when(ctype_digit((string) $idOrSlug), fn ($query) => $query->where('id', (int) $idOrSlug), fn ($query) => $query->where('slug', (string) $idOrSlug))
            ->firstOrFail();

        if ($challenge->sponsor_user_id && (int) $challenge->sponsor_user_id !== (int) $sponsor->id) {
            throw new RuntimeException('Only the challenge sponsor can fund this challenge.');
        }

        if ($idempotencyKey) {
            $existing = SkillsChallengeEscrow::query()->where('sponsor_user_id', $sponsor->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $alreadyFunded = SkillsChallengeEscrow::query()
            ->where('challenge_id', $challenge->id)
            ->where('sponsor_user_id', $sponsor->id)
            ->whereIn('status', ['funded', 'paid'])
            ->first();
        if ($alreadyFunded) {
            return $alreadyFunded;
        }

        $asset = strtoupper((string) $challenge->reward_asset);
        $amount = $this->fmt((string) $challenge->reward_amount);
        if ($this->compare($amount, '0') <= 0) {
            throw new RuntimeException('Challenge reward amount must be greater than zero.');
        }

        $reference = 'SKILLS-ESCROW-' . $challenge->id . '-' . $sponsor->id . '-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);

        return DB::transaction(function () use ($sponsor, $challenge, $asset, $amount, $reference, $idempotencyKey): SkillsChallengeEscrow {
            $sponsorFunding = $this->ledger->getOrCreateAccount($sponsor->id, 'funding', $asset);
            $escrowAccount = $this->ledger->getOrCreateAccount(null, 'skills_challenge_escrow', $asset);

            $this->ledger->postDoubleEntry($reference, 'ExaSkills challenge escrow funding', [
                ['account_id' => $sponsorFunding->id, 'amount' => $this->sub('0', $amount), 'asset' => $asset, 'user_id' => $sponsor->id, 'metadata' => ['challenge_id' => $challenge->id]],
                ['account_id' => $escrowAccount->id, 'amount' => $amount, 'asset' => $asset, 'metadata' => ['challenge_id' => $challenge->id]],
            ], 'skills_challenge_escrow', ['source' => 'exaskills']);

            $escrow = SkillsChallengeEscrow::query()->create([
                'challenge_id' => $challenge->id,
                'sponsor_user_id' => $sponsor->id,
                'asset' => $asset,
                'amount' => $amount,
                'paid_amount' => '0.00000000',
                'status' => 'funded',
                'funding_reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'funded_at' => now(),
                'metadata' => ['ledger_reference' => $reference],
            ]);

            $challenge->status = 'open';
            $challenge->save();

            return $escrow;
        });
    }

    public function payoutChallengeWinner(Admin|User $admin, int|string $idOrSlug, int $winnerUserId): SkillsChallengeEscrow
    {
        $challenge = SkillsChallenge::query()
            ->when(ctype_digit((string) $idOrSlug), fn ($query) => $query->where('id', (int) $idOrSlug), fn ($query) => $query->where('slug', (string) $idOrSlug))
            ->firstOrFail();

        return DB::transaction(function () use ($challenge, $winnerUserId, $admin): SkillsChallengeEscrow {
            $escrow = SkillsChallengeEscrow::query()->where('challenge_id', $challenge->id)->where('status', 'funded')->lockForUpdate()->firstOrFail();
            $hasSubmission = DB::table('skills_challenge_submissions')
                ->where('challenge_id', $challenge->id)
                ->where('user_id', $winnerUserId)
                ->exists();

            if (!$hasSubmission) {
                throw new RuntimeException('Winner must have a submitted project for this challenge.');
            }

            $asset = strtoupper((string) $escrow->asset);
            $amount = $this->fmt((string) $escrow->amount);
            $reference = 'SKILLS-PAYOUT-' . $challenge->id . '-' . $winnerUserId . '-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);

            $escrowAccount = $this->ledger->getOrCreateAccount(null, 'skills_challenge_escrow', $asset);
            $winnerFunding = $this->ledger->getOrCreateAccount($winnerUserId, 'funding', $asset);

            $this->ledger->postDoubleEntry($reference, 'ExaSkills challenge winner payout', [
                ['account_id' => $escrowAccount->id, 'amount' => $this->sub('0', $amount), 'asset' => $asset, 'metadata' => ['challenge_id' => $challenge->id, 'admin_id' => $admin->id]],
                ['account_id' => $winnerFunding->id, 'amount' => $amount, 'asset' => $asset, 'user_id' => $winnerUserId, 'metadata' => ['challenge_id' => $challenge->id]],
            ], 'skills_challenge_payout', ['source' => 'exaskills']);

            $escrow->winner_user_id = $winnerUserId;
            $escrow->paid_amount = $amount;
            $escrow->status = 'paid';
            $escrow->payout_reference = $reference;
            $escrow->paid_at = now();
            $escrow->metadata = array_merge((array) $escrow->metadata, ['payout_admin_id' => $admin->id]);
            $escrow->save();

            $challenge->status = 'completed';
            $challenge->save();

            return $escrow;
        });
    }
    public function challenges(int $perPage = 15): LengthAwarePaginator
    {
        return SkillsChallenge::query()
            ->whereIn('status', ['open', 'judging', 'completed'])
            ->orderByRaw('deadline_at is null, deadline_at asc')
            ->paginate(max(1, min($perPage, 50)));
    }

    public function opportunities(int $perPage = 15): LengthAwarePaginator
    {
        return SkillsOpportunity::query()
            ->where('status', 'open')
            ->latest()
            ->paginate(max(1, min($perPage, 50)));
    }

    public function challenge(int|string $idOrSlug): SkillsChallenge
    {
        return SkillsChallenge::query()
            ->when(ctype_digit((string) $idOrSlug),
                fn ($query) => $query->where('id', (int) $idOrSlug),
                fn ($query) => $query->where('slug', (string) $idOrSlug)
            )
            ->whereIn('status', ['open', 'judging', 'completed'])
            ->firstOrFail();
    }

    public function submitChallenge(User $user, int|string $idOrSlug, array $payload): object
    {
        $challenge = $this->challenge($idOrSlug);

        if ($challenge->status !== 'open') {
            throw new RuntimeException('This challenge is not accepting submissions right now.');
        }

        if ($challenge->deadline_at && $challenge->deadline_at->isPast()) {
            throw new RuntimeException('The submission deadline has passed.');
        }

        return DB::transaction(function () use ($user, $challenge, $payload): object {
            DB::table('skills_challenge_submissions')->updateOrInsert(
                ['challenge_id' => $challenge->id, 'user_id' => $user->id],
                [
                    'description' => $payload['description'] ?? null,
                    'repository_url' => $payload['repository_url'] ?? null,
                    'demo_url' => $payload['demo_url'] ?? null,
                    'attachments' => isset($payload['attachments']) ? json_encode($payload['attachments']) : null,
                    'status' => 'submitted',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            return (object) ['challenge_id' => $challenge->id, 'user_id' => $user->id, 'status' => 'submitted'];
        });
    }

    public function opportunity(int|string $idOrSlug): SkillsOpportunity
    {
        return SkillsOpportunity::query()
            ->when(ctype_digit((string) $idOrSlug),
                fn ($query) => $query->where('id', (int) $idOrSlug),
                fn ($query) => $query->where('slug', (string) $idOrSlug)
            )
            ->where('status', 'open')
            ->firstOrFail();
    }

    public function applyOpportunity(User $user, int|string $idOrSlug, array $payload): object
    {
        $opportunity = $this->opportunity($idOrSlug);

        if ($opportunity->deadline_at && $opportunity->deadline_at->isPast()) {
            throw new RuntimeException('The application deadline has passed.');
        }

        return DB::transaction(function () use ($user, $opportunity, $payload): object {
            DB::table('skills_applications')->updateOrInsert(
                ['opportunity_id' => $opportunity->id, 'user_id' => $user->id],
                [
                    'cover_note' => $payload['cover_note'] ?? null,
                    'portfolio_url' => $payload['portfolio_url'] ?? null,
                    'status' => 'submitted',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            return (object) ['opportunity_id' => $opportunity->id, 'user_id' => $user->id, 'status' => 'submitted'];
        });
    }

    public function verifyCredential(string $credentialCode): ?SkillsCredential
    {
        return SkillsCredential::query()
            ->with(['course:id,title,slug', 'user:id,name'])
            ->where('credential_code', $credentialCode)
            ->orWhere('verification_hash', $credentialCode)
            ->first();
    }

    public function adminOverview(): array
    {
        return [
            'learners' => CourseEnrollment::query()->distinct('user_id')->count('user_id'),
            'published_courses' => Course::query()->whereIn('status', ['published', 'active'])->count(),
            'draft_courses' => Course::query()->where('status', 'draft')->count(),
            'instructors_pending' => InstructorProfile::query()->where('status', 'pending')->count(),
            'instructors_approved' => InstructorProfile::query()->where('status', 'approved')->count(),
            'open_challenges' => SkillsChallenge::query()->where('status', 'open')->count(),
            'challenge_submissions' => DB::table('skills_challenge_submissions')->count(),
            'open_opportunities' => SkillsOpportunity::query()->where('status', 'open')->count(),
            'applications' => DB::table('skills_applications')->count(),
            'credentials_issued' => SkillsCredential::query()->count(),
            'revenue_enabled' => true,
            'challenge_escrow_enabled' => true,
        ];
    }
    private function platformCommissionRate(Course $course): string
    {
        $metadata = (array) ($course->metadata ?? []);
        $configured = $metadata['platform_commission_rate'] ?? config('exaskills.default_commission_rate', '0.150000');
        $rate = $this->fmt((string) $configured, 6);
        if ($this->compare($rate, '0') < 0 || $this->compare($rate, '1') > 0) {
            return '0.150000';
        }

        return $rate;
    }

    private function fmt(string $value): string
    {
        return function_exists('bcadd') ? bcadd($value, '0', self::SCALE) : number_format((float) $value, self::SCALE, '.', '');
    }

    private function add(string $left, string $right): string
    {
        return function_exists('bcadd') ? bcadd($left, $right, self::SCALE) : number_format((float) $left + (float) $right, self::SCALE, '.', '');
    }

    private function sub(string $left, string $right): string
    {
        return function_exists('bcsub') ? bcsub($left, $right, self::SCALE) : number_format((float) $left - (float) $right, self::SCALE, '.', '');
    }

    private function mul(string $left, string $right): string
    {
        return function_exists('bcmul') ? bcmul($left, $right, self::SCALE) : number_format((float) $left * (float) $right, self::SCALE, '.', '');
    }

    private function compare(string $left, string $right): int
    {
        return function_exists('bccomp') ? bccomp($left, $right, self::SCALE) : ((float) $left <=> (float) $right);
    }
    private function courseQuery(array $filters)
    {
        return Course::query()
            ->with('category')
            ->withCount('enrollments')
            ->whereIn('status', ['published', 'active'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $term = '%' . mb_strtolower($search) . '%';
                $query->where(function ($nested) use ($term): void {
                    $nested->whereRaw('LOWER(title) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(description) LIKE ?', [$term])
                        ->orWhereRaw("LOWER(COALESCE(instructor_name, '')) LIKE ?", [$term]);
                });
            })
            ->when($filters['category'] ?? null, function ($query, string $category): void {
                $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('slug', $category));
            })
            ->when($filters['difficulty'] ?? null, fn ($query, string $difficulty) => $query->where('difficulty', $difficulty))
            ->when(($filters['price'] ?? null) === 'free', fn ($query) => $query->where('price', '<=', 0))
            ->when(($filters['price'] ?? null) === 'paid', fn ($query) => $query->where('price', '>', 0))
            ->when($filters['featured'] ?? false, function ($query): void {
                $query->where(function ($nested): void {
                    $nested->where('credential_available', true)->orWhereNotNull('published_at');
                });
            })
            ->orderByRaw('published_at is null, published_at desc')
            ->latest();
    }

    private function continueLearning(User $user): array
    {
        return CourseEnrollment::query()
            ->with('course.category')
            ->where('user_id', $user->id)
            ->where('completed', false)
            ->latest()
            ->limit(3)
            ->get()
            ->all();
    }

    private function summary(?User $user): array
    {
        return [
            'active_learners' => CourseEnrollment::query()->distinct('user_id')->count('user_id'),
            'published_courses' => Course::query()->whereIn('status', ['published', 'active'])->count(),
            'open_challenges' => SkillsChallenge::query()->where('status', 'open')->count(),
            'open_opportunities' => SkillsOpportunity::query()->where('status', 'open')->count(),
            'my_credentials' => $user ? SkillsCredential::query()->where('user_id', $user->id)->count() : 0,
        ];
    }
}


