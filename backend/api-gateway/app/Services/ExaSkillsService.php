<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\InstructorProfile;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\SkillsCategory;
use App\Models\SkillsChallenge;
use App\Models\SkillsChallengeEscrow;
use App\Models\SkillsContentReport;
use App\Models\SkillsBusinessSeat;
use App\Models\SkillsCourseAssignment;
use App\Models\SkillsCoursePurchase;
use App\Models\SkillsCredential;
use App\Models\SkillsInstructorEarning;
use App\Models\SkillsInstructorPayout;
use App\Models\SkillsLessonProgress;
use App\Models\SkillsMediaAsset;
use App\Models\SkillsOpportunity;
use App\Models\SkillsOrganization;
use App\Models\SkillsOrganizationMember;
use App\Models\SkillsReconciliationIncident;
use App\Models\SkillsSubscription;
use App\Models\SkillsTaxPolicy;
use App\Models\SkillsTrainingProgram;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
                'subscriptions' => (bool) config('exaskills.subscriptions.enabled', true),
                'business_portal' => (bool) config('exaskills.business_portal_enabled', true),
                'employer_platform' => (bool) config('exaskills.employer_platform_enabled', true),
            ],
        ];
    }

    public function subscriptionPlans(): array
    {
        return array_values(config('exaskills.subscriptions.plans', []));
    }

    public function currentSubscription(User $user): ?SkillsSubscription
    {
        return SkillsSubscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['ACTIVE', 'PAST_DUE', 'PAUSED'])
            ->latest('starts_at')
            ->first();
    }

    public function activateSubscription(User $user, string $planCode, string $billingCycle, ?string $idempotencyKey): SkillsSubscription
    {
        if (!config('exaskills.subscriptions.enabled', true)) {
            throw new RuntimeException('ExaSkills subscriptions are disabled.');
        }
        if (!$idempotencyKey) {
            throw new RuntimeException('Idempotency-Key is required for subscription activation.');
        }
        $plans = config('exaskills.subscriptions.plans', []);
        $plan = $plans[$planCode] ?? null;
        if (!$plan || ($plan['status'] ?? 'ACTIVE') !== 'ACTIVE') {
            throw new RuntimeException('Subscription plan is not available.');
        }
        $price = $this->fmt((string) ($plan['prices'][$billingCycle] ?? $plan['price'] ?? '0'));
        $asset = strtoupper((string) ($plan['asset'] ?? 'USDT'));
        $period = $billingCycle === 'yearly' ? 12 : 1;
        $reference = 'SKILLS-SUBSCRIPTION-'.hash('sha256', $user->id.':'.$planCode.':'.$billingCycle.':'.$idempotencyKey);

        return DB::transaction(function () use ($user, $plan, $planCode, $billingCycle, $price, $asset, $period, $reference, $idempotencyKey): SkillsSubscription {
            $existing = SkillsSubscription::query()->where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) return $existing;

            if ($this->compare($price, '0') > 0) {
                $funding = $this->ledger->getOrCreateAccount($user->id, 'funding', $asset);
                $revenue = $this->ledger->getOrCreateAccount(null, 'skills_subscription_revenue', $asset);
                $this->ledger->postDoubleEntry($reference, 'ExaSkills subscription activation', [
                    ['account_id' => $funding->id, 'amount' => $this->sub('0', $price), 'asset' => $asset, 'user_id' => $user->id],
                    ['account_id' => $revenue->id, 'amount' => $price, 'asset' => $asset],
                ], 'skills_subscription_payment', ['source' => 'exaskills', 'plan_code' => $planCode]);
            }

            SkillsSubscription::query()->where('user_id', $user->id)->where('status', 'ACTIVE')->update(['status' => 'EXPIRED']);

            return SkillsSubscription::query()->create([
                'user_id' => $user->id,
                'plan_code' => $planCode,
                'status' => 'ACTIVE',
                'billing_cycle' => $billingCycle,
                'amount' => $price,
                'settlement_asset' => $asset,
                'renewal_reference' => $reference,
                'starts_at' => now(),
                'ends_at' => now()->addMonths($period),
                'idempotency_key' => $idempotencyKey,
                'pricing_snapshot' => ['engine' => 'PricingPolicyEngine-ready', 'plan' => $plan, 'price' => $price, 'asset' => $asset, 'billing_cycle' => $billingCycle, 'policy_version' => $plan['pricing_policy_version'] ?? 'exaskills-2026-10'],
                'metadata' => ['access_policy' => $plan['course_access_policy'] ?? 'catalog', 'seat_policy' => $plan['seat_policy'] ?? null],
            ]);
        });
    }

    public function renewSubscription(User $user, SkillsSubscription $subscription, ?string $idempotencyKey): SkillsSubscription
    {
        if (!$idempotencyKey) throw new RuntimeException('Idempotency-Key is required for subscription renewal.');
        if ((int) $subscription->user_id !== (int) $user->id) abort(404);
        return $this->activateSubscription($user, $subscription->plan_code, $subscription->billing_cycle, $idempotencyKey);
    }

    public function cancelSubscription(User $user, SkillsSubscription $subscription, bool $atPeriodEnd = true): SkillsSubscription
    {
        if ((int) $subscription->user_id !== (int) $user->id) abort(404);
        $subscription->update($atPeriodEnd
            ? ['status' => 'ACTIVE', 'cancels_at' => $subscription->ends_at]
            : ['status' => 'CANCELLED', 'cancelled_at' => now(), 'ends_at' => now()]
        );
        return $subscription->fresh();
    }

    public function expireSubscriptions(): int
    {
        return SkillsSubscription::query()
            ->whereIn('status', ['ACTIVE', 'PAST_DUE', 'PAUSED'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->update(['status' => 'EXPIRED']);
    }

    public function hasCourseEntitlement(User $user, Course $course): bool
    {
        if (CourseEnrollment::query()->where('user_id', $user->id)->where('course_id', $course->id)->exists()) return true;
        $subscription = $this->currentSubscription($user);
        if ($subscription && $subscription->status === 'ACTIVE' && (!$subscription->ends_at || $subscription->ends_at->isFuture())) return true;
        return SkillsCourseAssignment::query()->where('assigned_to_user_id', $user->id)->where('course_id', $course->id)->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])->exists();
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
            ->with(['category', 'lessons.mediaAssets', 'quiz.questions'])
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
            if (in_array($user->kyc_status ?? '', ['REJECTED', 'BLOCKED', 'SUSPENDED'], true)) {
                throw new RuntimeException('Instructor applications require identity verification.');
            }

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

        if (!$idempotencyKey) {
            throw new RuntimeException('Idempotency-Key is required for paid course purchase.');
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

        $reference = 'SKILLS-COURSE-' . hash('sha256', $user->id . ':' . $course->id . ':' . $idempotencyKey);
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
                'metadata' => ['ledger_reference' => $reference, 'pricing_snapshot' => ['course_price' => $price, 'commission_rate' => $commissionRate, 'platform_fee' => $platformFee, 'instructor_share' => $instructorAmount, 'engine' => 'PricingPolicyEngine-ready', 'at' => now()->toISOString()]],
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

    public function createCourse(User $user, array $payload): Course
    {
        $profile = InstructorProfile::query()->where('user_id', $user->id)->first();
        if (!$profile || !in_array($profile->status, ['approved', 'APPROVED'], true)) {
            throw new RuntimeException('Only approved instructors can create ExaSkills courses.');
        }

        return Course::query()->create([
            'created_by' => $user->id,
            'category_id' => $payload['category_id'] ?? null,
            'title' => $payload['title'],
            'slug' => Str::slug($payload['title']).'-'.strtolower(Str::random(6)),
            'instructor_name' => $profile->display_name,
            'description' => $payload['description'] ?? '',
            'difficulty' => $payload['difficulty'] ?? 'beginner',
            'language' => $payload['language'] ?? 'English',
            'duration' => (int) ($payload['duration'] ?? 0),
            'price' => $this->fmt((string) ($payload['price'] ?? '0')),
            'settlement_asset' => strtoupper((string) ($payload['settlement_asset'] ?? 'USDT')),
            'status' => 'draft',
            'credential_available' => (bool) ($payload['credential_available'] ?? true),
            'metadata' => ['state_history' => [['from' => null, 'to' => 'draft', 'actor' => $user->id, 'at' => now()->toISOString()]]],
        ]);
    }

    public function addLesson(User $user, int|string $courseId, array $payload): Lesson
    {
        $course = $this->instructorCourse($user, $courseId);
        return Lesson::query()->create([
            'course_id' => $course->id,
            'title' => $payload['title'],
            'content' => $payload['content'] ?? null,
            'duration_seconds' => (int) ($payload['duration_seconds'] ?? 0),
            'minimum_watch_seconds' => (int) ($payload['minimum_watch_seconds'] ?? 0),
            'order_index' => (int) ($payload['order_index'] ?? ($course->lessons()->count() + 1)),
            'metadata' => ['lesson_type' => strtoupper((string) ($payload['lesson_type'] ?? 'TEXT')), 'completion_rule' => strtoupper((string) ($payload['completion_rule'] ?? 'MANUAL'))],
        ]);
    }

    public function uploadMedia(User $user, int|string $courseId, UploadedFile $file, array $payload): SkillsMediaAsset
    {
        $course = $this->instructorCourse($user, $courseId);
        $lessonId = $payload['lesson_id'] ?? null;
        if ($lessonId && !Lesson::query()->where('course_id', $course->id)->whereKey($lessonId)->exists()) {
            throw new RuntimeException('Lesson does not belong to this course.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();
        if (!in_array($extension, config('exaskills.media.allowed_extensions', []), true) || !in_array($mime, config('exaskills.media.allowed_mimes', []), true)) {
            throw new RuntimeException('Unsupported ExaSkills media type.');
        }
        if ($file->getSize() > (int) config('exaskills.media.max_size_bytes', 20971520)) {
            throw new RuntimeException('ExaSkills media file is too large.');
        }

        $visibility = strtolower((string) ($payload['visibility'] ?? 'private'));
        if (!in_array($visibility, ['public', 'private'], true)) {
            throw new RuntimeException('Unsupported media visibility.');
        }
        $disk = (string) config('exaskills.media.disk', 'local');
        $path = $file->store('exaskills/'.$visibility.'/'.$course->id, $disk);

        return SkillsMediaAsset::query()->create([
            'owner_user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lessonId,
            'asset_type' => strtoupper((string) ($payload['asset_type'] ?? 'LESSON_ATTACHMENT')),
            'visibility' => $visibility,
            'provider' => (string) config('exaskills.media.provider', 'local'),
            'disk' => $disk,
            'storage_reference' => $path,
            'safe_filename' => Str::limit(preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName()), 180, ''),
            'mime_type' => $mime,
            'size_bytes' => $file->getSize(),
            'processing_state' => 'READY',
            'uploaded_at' => now(),
        ]);
    }

    public function mediaPath(User|Admin $actor, SkillsMediaAsset $asset): string
    {
        if ($asset->visibility !== 'public' && $actor instanceof User) {
            $owns = (int) $asset->owner_user_id === (int) $actor->id;
            $course = Course::query()->findOrFail($asset->course_id);
            if (!$owns && !$this->hasCourseEntitlement($actor, $course)) {
                abort(403);
            }
        }

        ActivityLog::query()->create([
            'user_id' => $actor instanceof User ? $actor->id : null,
            'admin_id' => $actor instanceof Admin ? $actor->id : null,
            'type' => 'exaskills',
            'action' => 'media.accessed',
            'status' => 'success',
            'data' => ['asset_id' => $asset->id, 'course_id' => $asset->course_id],
        ]);

        return Storage::disk($asset->disk)->path($asset->storage_reference);
    }

    public function submitCourseForReview(User $user, int|string $courseId): Course
    {
        $course = $this->instructorCourse($user, $courseId);
        if ($course->lessons()->count() < 1) {
            throw new RuntimeException('A course needs at least one lesson before review.');
        }
        return $this->transitionCourse($course, 'ready_for_review', $user->id, 'Instructor submitted course for review.');
    }

    public function reviewCourse(Admin $admin, int|string $courseId, string $action, string $reason): Course
    {
        $course = $this->findCourseAny($courseId);
        $target = match (strtoupper($action)) {
            'APPROVE' => 'approved',
            'REQUEST_CHANGES' => 'needs_changes',
            'REJECT' => 'rejected',
            'SUSPEND' => 'suspended',
            default => throw new RuntimeException('Unsupported course review action.'),
        };
        return $this->transitionCourse($course, $target, $admin->id, $reason);
    }

    public function publishCourse(User $user, int|string $courseId): Course
    {
        $course = $this->instructorCourse($user, $courseId);
        if ($course->status !== 'approved') {
            throw new RuntimeException('Course must be approved before publishing.');
        }
        if (SkillsMediaAsset::query()->where('course_id', $course->id)->whereIn('processing_state', ['UPLOADING', 'PROCESSING', 'FAILED'])->exists()) {
            throw new RuntimeException('Course media is not ready.');
        }
        $course = $this->transitionCourse($course, 'published', $user->id, 'Instructor published approved course.');
        $course->published_at = now();
        $course->save();
        return $course->fresh(['lessons', 'mediaAssets']);
    }

    public function completeLesson(User $user, int|string $courseId, int $lessonId, array $payload): SkillsLessonProgress
    {
        $course = $this->course($courseId);
        $enrollment = CourseEnrollment::query()->where('user_id', $user->id)->where('course_id', $course->id)->firstOrFail();
        $lesson = Lesson::query()->where('course_id', $course->id)->whereKey($lessonId)->firstOrFail();
        $watchSeconds = max((int) ($payload['watch_seconds'] ?? 0), (int) $lesson->minimum_watch_seconds);

        $progress = SkillsLessonProgress::query()->updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['course_id' => $course->id, 'status' => 'completed', 'watch_seconds' => $watchSeconds, 'started_at' => now(), 'completed_at' => now(), 'metadata' => ['source' => 'server_authoritative']]
        );

        $completed = SkillsLessonProgress::query()->where('user_id', $user->id)->where('course_id', $course->id)->where('status', 'completed')->count();
        $total = max(1, Lesson::query()->where('course_id', $course->id)->count());
        $percent = $this->fmt(FinancialDecimal::mul(FinancialDecimal::div((string) $completed, (string) $total, self::SCALE), '100', self::SCALE), 2);
        $enrollment->update(['progress_percentage' => $percent, 'last_unlocked_lesson_order' => max((int) $enrollment->last_unlocked_lesson_order, (int) $lesson->order_index + 1)]);
        SkillsCourseAssignment::query()
            ->where('assigned_to_user_id', $user->id)
            ->where('course_id', $course->id)
            ->update(['status' => $percent === '100.00' ? 'COMPLETED' : 'IN_PROGRESS', 'progress_percentage' => $percent, 'completed_at' => $percent === '100.00' ? now() : null]);

        $this->completeCourseIfEligible($user, $course);

        return $progress;
    }

    public function submitAssessment(User $user, int|string $courseId, array $answers, ?string $idempotencyKey): QuizAttempt
    {
        if (!$idempotencyKey) {
            throw new RuntimeException('Idempotency-Key is required for assessment submission.');
        }
        $course = $this->course($courseId);
        $quiz = Quiz::query()->with('questions')->where('course_id', $course->id)->firstOrFail();
        $existing = QuizAttempt::query()->where('user_id', $user->id)->where('attempt_fingerprint', hash('sha256', $idempotencyKey))->first();
        if ($existing) {
            return $existing;
        }
        $attempts = QuizAttempt::query()->where('user_id', $user->id)->where('quiz_id', $quiz->id)->count();
        if ($attempts >= (int) $quiz->max_attempts) {
            throw new RuntimeException('Assessment attempt limit reached.');
        }

        $correct = 0;
        foreach ($quiz->questions as $question) {
            if (array_key_exists((string) $question->id, $answers) && hash_equals((string) $question->getRawOriginal('correct_answer'), (string) $answers[(string) $question->id])) {
                $correct++;
            }
        }
        $total = max(1, $quiz->questions->count());
        $score = (int) floor(((int) $correct / $total) * 100);
        $passed = $score >= (int) $quiz->passing_score;

        $attempt = QuizAttempt::query()->create([
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'course_id' => $course->id,
            'score' => $score,
            'passed' => $passed,
            'time_spent_seconds' => 0,
            'submitted_answers' => ['assessment_version' => $quiz->metadata['version'] ?? 1, 'answers' => $answers],
            'attempt_fingerprint' => hash('sha256', $idempotencyKey),
            'submitted_at' => now(),
        ]);

        $this->completeCourseIfEligible($user, $course);

        return $attempt;
    }

    public function revokeCredential(Admin $admin, string $code, string $reason): SkillsCredential
    {
        $credential = SkillsCredential::query()->where('credential_code', $code)->firstOrFail();
        $credential->update(['status' => 'revoked', 'metadata' => array_merge($credential->metadata ?? [], ['revoked_by_admin_id' => $admin->id, 'revocation_reason' => $reason, 'revoked_at' => now()->toISOString()])]);
        return $credential->fresh();
    }

    public function requestInstructorPayout(User $user, string $asset, string $amount, ?string $idempotencyKey): SkillsInstructorPayout
    {
        if (!$idempotencyKey) {
            throw new RuntimeException('Idempotency-Key is required for instructor payout.');
        }
        $amount = $this->fmt($amount);
        if ($this->compare($amount, (string) config('exaskills.payouts.minimum_amount', '1')) < 0) {
            throw new RuntimeException('Payout amount is below minimum.');
        }

        return DB::transaction(function () use ($user, $asset, $amount, $idempotencyKey): SkillsInstructorPayout {
            $existing = SkillsInstructorPayout::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            $earnings = SkillsInstructorEarning::query()->where('instructor_user_id', $user->id)->where('asset', strtoupper($asset))->where('status', 'pending')->lockForUpdate()->get();
            $available = $earnings->reduce(fn (string $sum, SkillsInstructorEarning $earning): string => $this->add($sum, (string) $earning->net_amount), '0');
            if ($this->compare($available, $amount) < 0) {
                throw new RuntimeException('Available instructor payable is insufficient.');
            }
            $reference = 'SKILLS-INSTRUCTOR-PAYOUT-'.hash('sha256', $user->id.':'.$asset.':'.$idempotencyKey);
            $payout = SkillsInstructorPayout::query()->create([
                'instructor_user_id' => $user->id,
                'asset' => strtoupper($asset),
                'amount' => $amount,
                'status' => 'REQUESTED',
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'requested_at' => now(),
                'earning_ids' => $earnings->pluck('id')->values()->all(),
            ]);
            $earnings->each->update(['status' => 'payout_requested']);
            return $payout;
        });
    }

    public function approveInstructorPayout(Admin $admin, SkillsInstructorPayout $payout): SkillsInstructorPayout
    {
        return DB::transaction(function () use ($admin, $payout): SkillsInstructorPayout {
            $payout = SkillsInstructorPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if ($payout->status === 'COMPLETED') {
                return $payout;
            }
            if (!in_array($payout->status, ['REQUESTED', 'PENDING_REVIEW', 'APPROVED'], true)) {
                throw new RuntimeException('Payout is not approvable.');
            }

            $asset = strtoupper((string) $payout->asset);
            $payable = $this->ledger->getOrCreateAccount($payout->instructor_user_id, 'skills_instructor_payable', $asset);
            $funding = $this->ledger->getOrCreateAccount($payout->instructor_user_id, 'funding', $asset);
            $this->ledger->postDoubleEntry($payout->reference, 'ExaSkills instructor payout', [
                ['account_id' => $payable->id, 'amount' => $this->sub('0', (string) $payout->amount), 'asset' => $asset, 'user_id' => $payout->instructor_user_id],
                ['account_id' => $funding->id, 'amount' => (string) $payout->amount, 'asset' => $asset, 'user_id' => $payout->instructor_user_id],
            ], 'skills_instructor_payout', ['source' => 'exaskills', 'payout_id' => $payout->id]);
            $payout->update(['status' => 'COMPLETED', 'reviewed_by_admin_id' => $admin->id, 'reviewed_at' => now(), 'completed_at' => now()]);
            SkillsInstructorEarning::query()->whereIn('id', $payout->earning_ids ?? [])->update(['status' => 'paid']);
            return $payout->fresh();
        });
    }

    public function reconciliation(): array
    {
        $findings = [];
        $orphanPurchases = SkillsCoursePurchase::query()->where('status', 'completed')->whereDoesntHave('course')->count();
        $purchaseWithoutEnrollment = SkillsCoursePurchase::query()->where('status', 'completed')->whereNotExists(function ($query): void {
            $query->selectRaw('1')->from('course_enrollments')->whereColumn('course_enrollments.user_id', 'skills_course_purchases.user_id')->whereColumn('course_enrollments.course_id', 'skills_course_purchases.course_id');
        })->count();
        $activeExpiredSubscriptions = SkillsSubscription::query()->where('status', 'ACTIVE')->whereNotNull('ends_at')->where('ends_at', '<', now())->count();
        $assignmentWithoutEnrollment = SkillsCourseAssignment::query()->whereNotExists(function ($query): void {
            $query->selectRaw('1')->from('course_enrollments')->whereColumn('course_enrollments.user_id', 'skills_course_assignments.assigned_to_user_id')->whereColumn('course_enrollments.course_id', 'skills_course_assignments.course_id');
        })->count();
        if ($orphanPurchases > 0) $findings[] = ['type' => 'orphan_purchase', 'count' => $orphanPurchases];
        if ($purchaseWithoutEnrollment > 0) $findings[] = ['type' => 'purchase_without_enrollment', 'count' => $purchaseWithoutEnrollment];
        if ($activeExpiredSubscriptions > 0) $findings[] = ['type' => 'active_expired_subscription', 'count' => $activeExpiredSubscriptions];
        if ($assignmentWithoutEnrollment > 0) $findings[] = ['type' => 'business_assignment_without_enrollment', 'count' => $assignmentWithoutEnrollment];
        foreach ($findings as $finding) {
            SkillsReconciliationIncident::query()->firstOrCreate(['incident_type' => $finding['type'], 'status' => 'OPEN'], ['severity' => 'high', 'evidence' => $finding]);
        }
        return ['status' => $findings ? 'FAIL' : 'PASS', 'findings' => $findings];
    }

    public function accountClosureBlockers(int $userId): array
    {
        $blockers = [];
        if (SkillsSubscription::query()->where('user_id', $userId)->whereIn('status', ['ACTIVE', 'PAST_DUE', 'PAUSED'])->exists()) {
            $blockers[] = ['product' => 'exaskills', 'reason' => 'active_subscription'];
        }
        if (SkillsInstructorPayout::query()->where('instructor_user_id', $userId)->whereIn('status', ['REQUESTED', 'PENDING_REVIEW', 'APPROVED'])->exists()) {
            $blockers[] = ['product' => 'exaskills', 'reason' => 'pending_instructor_payout'];
        }
        if (InstructorProfile::query()->where('user_id', $userId)->whereIn('tax_verification_status', ['PENDING', 'DOCUMENT_REQUIRED', 'HOLD'])->exists()) {
            $blockers[] = ['product' => 'exaskills', 'reason' => 'tax_profile_pending_or_hold'];
        }
        if (SkillsOrganization::query()->where('owner_user_id', $userId)->whereIn('billing_status', ['PAST_DUE', 'OPEN_INVOICE'])->exists()) {
            $blockers[] = ['product' => 'exaskills', 'reason' => 'business_billing_obligation'];
        }
        if (SkillsOpportunity::query()->where('company_user_id', $userId)->whereIn('status', ['open', 'UNDER_REVIEW', 'PUBLISHED', 'PAUSED'])->exists()) {
            $blockers[] = ['product' => 'exaskills', 'reason' => 'active_employer_opportunity'];
        }
        return $blockers;
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
            'subscriptions_active' => SkillsSubscription::query()->where('status', 'ACTIVE')->count(),
            'business_organizations' => SkillsOrganization::query()->count(),
            'business_seats_assigned' => SkillsBusinessSeat::query()->where('status', 'ASSIGNED')->count(),
            'tax_profiles_pending' => InstructorProfile::query()->whereIn('tax_verification_status', ['PENDING', 'DOCUMENT_REQUIRED'])->count(),
        ];
    }

    public function updateInstructorTaxProfile(User $user, array $payload): InstructorProfile
    {
        $profile = InstructorProfile::query()->where('user_id', $user->id)->firstOrFail();
        $profile->update([
            'legal_name' => $payload['legal_name'] ?? $profile->legal_name,
            'entity_type' => $payload['entity_type'] ?? $profile->entity_type ?? 'INDIVIDUAL',
            'country' => strtoupper((string) ($payload['country'] ?? $profile->country ?? '')),
            'tax_residency' => strtoupper((string) ($payload['tax_residency'] ?? $profile->tax_residency ?? '')),
            'tax_identifier_hash' => isset($payload['tax_identifier']) ? hash('sha256', (string) $payload['tax_identifier']) : $profile->tax_identifier_hash,
            'tax_status' => 'SUBMITTED',
            'tax_verification_status' => 'PENDING',
        ]);
        return $profile->fresh();
    }

    public function createTaxPolicy(Admin $admin, array $payload): SkillsTaxPolicy
    {
        $policy = SkillsTaxPolicy::query()->create([
            'country' => isset($payload['country']) ? strtoupper((string) $payload['country']) : null,
            'entity_type' => $payload['entity_type'] ?? null,
            'income_category' => $payload['income_category'] ?? 'instructor_payout',
            'payout_asset' => isset($payload['payout_asset']) ? strtoupper((string) $payload['payout_asset']) : null,
            'outcome' => $payload['outcome'] ?? 'MANUAL_REVIEW',
            'withholding_rate' => $this->fmt((string) ($payload['withholding_rate'] ?? '0')),
            'policy_version' => $payload['policy_version'],
            'status' => $payload['status'] ?? 'DRAFT',
            'effective_from' => $payload['effective_from'] ?? now(),
            'metadata' => ['created_by_admin_id' => $admin->id, 'external_tax_review_required' => true],
        ]);
        ActivityLog::query()->create(['admin_id' => $admin->id, 'type' => 'exaskills', 'action' => 'tax.policy.created', 'status' => 'success', 'data' => ['policy_id' => $policy->id]]);
        return $policy;
    }

    public function taxDecision(InstructorProfile $profile, string $asset, string $gross): array
    {
        $policy = SkillsTaxPolicy::query()
            ->where('status', 'APPROVED')
            ->where(function ($query) use ($profile): void { $query->whereNull('country')->orWhere('country', strtoupper((string) $profile->tax_residency)); })
            ->where(function ($query) use ($profile): void { $query->whereNull('entity_type')->orWhere('entity_type', $profile->entity_type); })
            ->where(function ($query) use ($asset): void { $query->whereNull('payout_asset')->orWhere('payout_asset', strtoupper($asset)); })
            ->where(function ($query): void { $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()); })
            ->latest('id')
            ->first();
        if (!$policy) {
            return ['outcome' => 'MANUAL_REVIEW', 'withholding_amount' => '0.00000000', 'net_amount' => $this->fmt($gross), 'policy_version' => null];
        }
        $withholding = $this->mul($this->fmt($gross), (string) $policy->withholding_rate);
        return ['outcome' => $policy->outcome, 'withholding_amount' => $withholding, 'net_amount' => $this->sub($this->fmt($gross), $withholding), 'policy_version' => $policy->policy_version];
    }

    public function createOrganization(User $owner, array $payload): SkillsOrganization
    {
        $organization = SkillsOrganization::query()->create([
            'owner_user_id' => $owner->id,
            'name' => $payload['name'],
            'country' => strtoupper((string) ($payload['country'] ?? '')),
            'industry' => $payload['industry'] ?? null,
            'status' => 'PENDING_REVIEW',
            'kyb_status' => 'PENDING',
            'billing_status' => 'NOT_CONFIGURED',
            'plan_code' => $payload['plan_code'] ?? 'BUSINESS',
            'metadata' => ['source' => 'exaskills_business'],
        ]);
        SkillsOrganizationMember::query()->create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'email' => $owner->email, 'role' => 'OWNER', 'status' => 'ACTIVE', 'accepted_at' => now()]);
        return $organization->fresh(['members']);
    }

    public function inviteBusinessMember(User $owner, SkillsOrganization $organization, string $email, string $role = 'LEARNER'): SkillsOrganizationMember
    {
        $this->assertOrganizationAdmin($owner, $organization);
        return SkillsOrganizationMember::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'email' => strtolower($email)],
            ['role' => strtoupper($role), 'status' => 'PENDING', 'invited_at' => now(), 'expires_at' => now()->addDays(14)]
        );
    }

    public function createBusinessSeats(User $owner, SkillsOrganization $organization, int $count): int
    {
        $this->assertOrganizationAdmin($owner, $organization);
        $limit = min(max($count, 1), (int) config('exaskills.business.max_seat_batch', 250));
        for ($i = 0; $i < $limit; $i++) SkillsBusinessSeat::query()->create(['organization_id' => $organization->id, 'status' => 'AVAILABLE']);
        return $limit;
    }

    public function createTrainingProgram(User $owner, SkillsOrganization $organization, array $payload): SkillsTrainingProgram
    {
        $this->assertOrganizationAdmin($owner, $organization);
        return SkillsTrainingProgram::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $owner->id,
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'required_course_ids' => $payload['required_course_ids'] ?? [],
            'optional_course_ids' => $payload['optional_course_ids'] ?? [],
            'status' => 'ACTIVE',
            'deadline_at' => $payload['deadline_at'] ?? null,
        ]);
    }

    public function assignCourse(User $owner, SkillsOrganization $organization, Course $course, User $learner, ?SkillsTrainingProgram $program = null): SkillsCourseAssignment
    {
        $this->assertOrganizationAdmin($owner, $organization);
        $member = SkillsOrganizationMember::query()->where('organization_id', $organization->id)->where('user_id', $learner->id)->where('status', 'ACTIVE')->first();
        if (!$member) throw new RuntimeException('Learner is not an active organization member.');
        $seat = SkillsBusinessSeat::query()->where('organization_id', $organization->id)->where('status', 'AVAILABLE')->lockForUpdate()->first();
        if (!$seat) throw new RuntimeException('No available ExaSkills business seats.');
        $seat->update(['user_id' => $learner->id, 'status' => 'ASSIGNED', 'assigned_at' => now()]);
        CourseEnrollment::query()->firstOrCreate(['user_id' => $learner->id, 'course_id' => $course->id], ['progress_percentage' => '0.00', 'completed' => false, 'progress_metadata' => ['source' => 'business_assignment', 'organization_id' => $organization->id]]);
        return SkillsCourseAssignment::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'course_id' => $course->id, 'assigned_to_user_id' => $learner->id],
            ['program_id' => $program?->id, 'assigned_by_user_id' => $owner->id, 'status' => 'ASSIGNED', 'assigned_at' => now()]
        );
    }

    public function businessDashboard(User $owner, SkillsOrganization $organization): array
    {
        $this->assertOrganizationAdmin($owner, $organization);
        return [
            'organization' => $organization->fresh(),
            'members' => SkillsOrganizationMember::query()->where('organization_id', $organization->id)->get(),
            'seats' => ['total' => SkillsBusinessSeat::query()->where('organization_id', $organization->id)->count(), 'assigned' => SkillsBusinessSeat::query()->where('organization_id', $organization->id)->where('status', 'ASSIGNED')->count()],
            'assignments' => SkillsCourseAssignment::query()->where('organization_id', $organization->id)->latest()->get(),
            'programs' => SkillsTrainingProgram::query()->where('organization_id', $organization->id)->latest()->get(),
        ];
    }

    public function createEmployerOpportunity(User $owner, SkillsOrganization $organization, array $payload): SkillsOpportunity
    {
        $this->assertOrganizationAdmin($owner, $organization);
        if (!in_array($organization->status, ['APPROVED', 'ACTIVE'], true)) throw new RuntimeException('Employer organization must be approved before publishing opportunities.');
        return SkillsOpportunity::query()->create([
            'company_user_id' => $owner->id,
            'organization_id' => (string) $organization->id,
            'company_name' => $organization->name,
            'title' => $payload['title'],
            'slug' => Str::slug($payload['title']).'-'.strtolower(Str::random(6)),
            'type' => $payload['type'] ?? 'job',
            'employment_type' => $payload['employment_type'] ?? 'FULL_TIME',
            'description' => $payload['description'],
            'required_skills' => $payload['required_skills'] ?? [],
            'preferred_skills' => $payload['preferred_skills'] ?? [],
            'required_credentials' => $payload['required_credentials'] ?? [],
            'compensation_label' => $payload['compensation_label'] ?? null,
            'location_type' => $payload['location_type'] ?? 'remote',
            'remote_policy' => $payload['remote_policy'] ?? 'REMOTE',
            'experience_level' => $payload['experience_level'] ?? null,
            'status' => 'UNDER_REVIEW',
            'review_status' => 'PENDING',
            'deadline_at' => $payload['deadline_at'] ?? null,
        ]);
    }

    public function moderateOpportunity(Admin $admin, SkillsOpportunity $opportunity, string $action, string $reason): SkillsOpportunity
    {
        $target = match (strtoupper($action)) {
            'PUBLISH' => ['status' => 'open', 'review_status' => 'APPROVED', 'published_at' => now()],
            'PAUSE' => ['status' => 'PAUSED', 'paused_at' => now()],
            'CLOSE' => ['status' => 'CLOSED', 'closed_at' => now()],
            'REJECT' => ['status' => 'ARCHIVED', 'review_status' => 'REJECTED'],
            default => throw new RuntimeException('Unsupported opportunity moderation action.'),
        };
        $opportunity->update(array_merge($target, ['moderation' => ['admin_id' => $admin->id, 'action' => strtoupper($action), 'reason' => $reason, 'at' => now()->toISOString()]]));
        return $opportunity->fresh();
    }

    private function assertOrganizationAdmin(User $user, SkillsOrganization $organization): void
    {
        $allowed = (int) $organization->owner_user_id === (int) $user->id || SkillsOrganizationMember::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->whereIn('role', ['OWNER', 'ADMIN', 'MANAGER'])
            ->where('status', 'ACTIVE')
            ->exists();
        if (!$allowed) abort(403);
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

    private function completeCourseIfEligible(User $user, Course $course): void
    {
        $enrollment = CourseEnrollment::query()->where('user_id', $user->id)->where('course_id', $course->id)->first();
        if (!$enrollment || $enrollment->completed) {
            return;
        }

        $lessonTotal = Lesson::query()->where('course_id', $course->id)->count();
        $lessonComplete = SkillsLessonProgress::query()->where('user_id', $user->id)->where('course_id', $course->id)->where('status', 'completed')->count();
        $quiz = Quiz::query()->where('course_id', $course->id)->first();
        $quizPassed = !$quiz || QuizAttempt::query()->where('user_id', $user->id)->where('quiz_id', $quiz->id)->where('passed', true)->exists();

        if ($lessonTotal > 0 && $lessonComplete >= $lessonTotal && $quizPassed) {
            $enrollment->update(['completed' => true, 'completed_at' => now(), 'progress_percentage' => '100.00']);
            if ($course->credential_available) {
                SkillsCredential::query()->firstOrCreate(
                    ['user_id' => $user->id, 'course_id' => $course->id],
                    [
                        'credential_code' => 'EXASKILLS-'.strtoupper(Str::random(14)),
                        'title' => $course->title.' Certificate',
                        'skills' => [$course->difficulty, $course->category?->name],
                        'status' => 'verified',
                        'issued_at' => now(),
                        'verification_hash' => hash('sha256', $user->id.':'.$course->id.':'.now()->timestamp),
                        'metadata' => ['course_version' => $course->metadata['version'] ?? 1, 'issuer' => 'ExaEarn ExaSkills'],
                    ]
                );
            }
        }
    }

    private function transitionCourse(Course $course, string $status, int $actorId, string $reason): Course
    {
        $allowed = [
            'draft' => ['incomplete', 'ready_for_review', 'archived'],
            'incomplete' => ['ready_for_review', 'archived'],
            'ready_for_review' => ['under_review', 'needs_changes', 'approved', 'rejected'],
            'under_review' => ['needs_changes', 'approved', 'rejected'],
            'needs_changes' => ['ready_for_review', 'archived'],
            'approved' => ['scheduled', 'published', 'suspended'],
            'scheduled' => ['published', 'paused', 'archived'],
            'published' => ['paused', 'suspended', 'archived'],
            'active' => ['paused', 'suspended', 'archived'],
            'paused' => ['published', 'archived'],
            'suspended' => ['needs_changes', 'archived'],
        ];
        if (!in_array($status, $allowed[$course->status] ?? [], true)) {
            throw new RuntimeException("Invalid ExaSkills course transition {$course->status} -> {$status}.");
        }
        $history = $course->metadata['state_history'] ?? [];
        $history[] = ['from' => $course->status, 'to' => $status, 'actor' => $actorId, 'reason' => $reason, 'at' => now()->toISOString()];
        $course->update(['status' => $status, 'metadata' => array_merge($course->metadata ?? [], ['state_history' => $history])]);
        ActivityLog::query()->create(['user_id' => $course->created_by, 'type' => 'exaskills', 'action' => 'course.'.$status, 'status' => 'success', 'data' => ['course_id' => $course->id, 'actor_id' => $actorId, 'reason' => $reason]]);
        return $course->fresh();
    }

    private function instructorCourse(User $user, int|string $courseId): Course
    {
        $course = $this->findCourseAny($courseId);
        if ((int) $course->created_by !== (int) $user->id) {
            abort(404);
        }
        return $course;
    }

    private function findCourseAny(int|string $idOrSlug): Course
    {
        return Course::query()
            ->with(['category', 'lessons'])
            ->when(ctype_digit((string) $idOrSlug), fn ($query) => $query->where('id', (int) $idOrSlug), fn ($query) => $query->where('slug', (string) $idOrSlug))
            ->firstOrFail();
    }

    private function fmt(string $value, int $scale = self::SCALE): string
    {
        return FinancialDecimal::normalize($value, $scale);
    }

    private function add(string $left, string $right): string
    {
        return FinancialDecimal::add($left, $right, self::SCALE);
    }

    private function sub(string $left, string $right): string
    {
        return FinancialDecimal::sub($left, $right, self::SCALE);
    }

    private function mul(string $left, string $right): string
    {
        return FinancialDecimal::mul($left, $right, self::SCALE);
    }

    private function compare(string $left, string $right): int
    {
        return FinancialDecimal::compare($left, $right, self::SCALE);
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


