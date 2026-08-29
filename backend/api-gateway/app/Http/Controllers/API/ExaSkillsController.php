<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SkillsMediaAsset;
use App\Models\SkillsInstructorPayout;
use App\Models\SkillsOrganization;
use App\Models\SkillsSubscription;
use App\Services\ExaSkillsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ExaSkillsController extends Controller
{
    public function __construct(private readonly ExaSkillsService $exaSkills)
    {
    }

    public function home(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaSkills->home($request->user())]);
    }

    public function categories(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaSkills->categories()]);
    }

    public function courses(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaSkills->courses($request->query(), (int) $request->query('per_page', 15))]);
    }

    public function course(string $course): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaSkills->course($course)]);
    }

    public function enroll(Request $request, string $course): JsonResponse
    {
        try {
            $enrollment = $this->exaSkills->enroll(
                $request->user(),
                $this->exaSkills->course($course),
                $request->header('Idempotency-Key')
            );
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Enrollment created.', 'data' => $enrollment], 201);
    }

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaSkills->myDashboard($request->user())]);
    }

    public function subscriptionPlans(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaSkills->subscriptionPlans()]);
    }

    public function currentSubscription(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaSkills->currentSubscription($request->user())]);
    }

    public function activateSubscription(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'plan_code' => ['required', 'string', 'max:40'],
            'billing_cycle' => ['nullable', 'in:monthly,yearly'],
        ]);
        try {
            return response()->json(['success' => true, 'data' => $this->exaSkills->activateSubscription($request->user(), strtoupper($payload['plan_code']), $payload['billing_cycle'] ?? 'monthly', $request->header('Idempotency-Key'))], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function renewSubscription(Request $request, SkillsSubscription $subscription): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->exaSkills->renewSubscription($request->user(), $subscription, $request->header('Idempotency-Key'))]);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function cancelSubscription(Request $request, SkillsSubscription $subscription): JsonResponse
    {
        $payload = $request->validate(['at_period_end' => ['nullable', 'boolean']]);
        return response()->json(['success' => true, 'data' => $this->exaSkills->cancelSubscription($request->user(), $subscription, (bool) ($payload['at_period_end'] ?? true))]);
    }

    public function instructorApply(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'headline' => ['nullable', 'string', 'max:180'],
            'bio' => ['nullable', 'string', 'max:4000'],
            'expertise' => ['nullable', 'array'],
            'portfolio_links' => ['nullable', 'array'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Instructor application submitted for review.',
            'data' => $this->exaSkills->applyInstructor($request->user(), $payload),
        ], 201);
    }

    public function createCourse(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:skills_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'difficulty' => ['nullable', 'string', 'max:40'],
            'language' => ['nullable', 'string', 'max:40'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'settlement_asset' => ['nullable', 'string', 'max:20'],
            'credential_available' => ['nullable', 'boolean'],
        ]);

        try {
            return response()->json(['success' => true, 'data' => $this->exaSkills->createCourse($request->user(), $payload)], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function addLesson(Request $request, string $course): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'minimum_watch_seconds' => ['nullable', 'integer', 'min:0'],
            'order_index' => ['nullable', 'integer', 'min:1'],
            'lesson_type' => ['nullable', 'string'],
            'completion_rule' => ['nullable', 'string'],
        ]);

        try {
            return response()->json(['success' => true, 'data' => $this->exaSkills->addLesson($request->user(), $course, $payload)], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function uploadMedia(Request $request, string $course): JsonResponse
    {
        $payload = $request->validate([
            'media' => ['required', 'file'],
            'lesson_id' => ['nullable', 'integer'],
            'asset_type' => ['nullable', 'string'],
            'visibility' => ['nullable', 'string'],
        ]);

        try {
            return response()->json(['success' => true, 'data' => $this->exaSkills->uploadMedia($request->user(), $course, $payload['media'], $payload)], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function media(Request $request, SkillsMediaAsset $asset): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return response()->file($this->exaSkills->mediaPath($request->user(), $asset));
    }

    public function submitCourse(Request $request, string $course): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->exaSkills->submitCourseForReview($request->user(), $course)]);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function publishCourse(Request $request, string $course): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->exaSkills->publishCourse($request->user(), $course)]);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function completeLesson(Request $request, string $course, int $lesson): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->exaSkills->completeLesson($request->user(), $course, $lesson, $request->validate(['watch_seconds' => ['nullable', 'integer', 'min:0']]))]);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function submitAssessment(Request $request, string $course): JsonResponse
    {
        $payload = $request->validate(['answers' => ['required', 'array']]);
        try {
            return response()->json(['success' => true, 'data' => $this->exaSkills->submitAssessment($request->user(), $course, $payload['answers'], $request->header('Idempotency-Key'))], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function requestPayout(Request $request): JsonResponse
    {
        $payload = $request->validate(['asset' => ['required', 'string', 'max:20'], 'amount' => ['required', 'numeric', 'min:0']]);
        try {
            return response()->json(['success' => true, 'data' => $this->exaSkills->requestInstructorPayout($request->user(), $payload['asset'], (string) $payload['amount'], $request->header('Idempotency-Key'))], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function taxProfile(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'legal_name' => ['required', 'string', 'max:180'],
            'entity_type' => ['required', 'string', 'max:40'],
            'country' => ['required', 'string', 'size:2'],
            'tax_residency' => ['required', 'string', 'size:2'],
            'tax_identifier' => ['nullable', 'string', 'max:180'],
        ]);
        return response()->json(['success' => true, 'data' => $this->exaSkills->updateInstructorTaxProfile($request->user(), $payload)]);
    }

    public function createOrganization(Request $request): JsonResponse
    {
        $payload = $request->validate(['name' => ['required', 'string', 'max:180'], 'country' => ['nullable', 'string', 'size:2'], 'industry' => ['nullable', 'string', 'max:120'], 'plan_code' => ['nullable', 'string', 'max:60']]);
        return response()->json(['success' => true, 'data' => $this->exaSkills->createOrganization($request->user(), $payload)], 201);
    }

    public function businessDashboard(Request $request, SkillsOrganization $organization): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaSkills->businessDashboard($request->user(), $organization)]);
    }

    public function inviteBusinessMember(Request $request, SkillsOrganization $organization): JsonResponse
    {
        $payload = $request->validate(['email' => ['required', 'email', 'max:180'], 'role' => ['nullable', 'string', 'max:40']]);
        return response()->json(['success' => true, 'data' => $this->exaSkills->inviteBusinessMember($request->user(), $organization, $payload['email'], $payload['role'] ?? 'LEARNER')], 201);
    }

    public function createBusinessSeats(Request $request, SkillsOrganization $organization): JsonResponse
    {
        $payload = $request->validate(['count' => ['required', 'integer', 'min:1', 'max:250']]);
        return response()->json(['success' => true, 'data' => ['created' => $this->exaSkills->createBusinessSeats($request->user(), $organization, (int) $payload['count'])]], 201);
    }

    public function createTrainingProgram(Request $request, SkillsOrganization $organization): JsonResponse
    {
        $payload = $request->validate(['title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string'], 'required_course_ids' => ['nullable', 'array'], 'optional_course_ids' => ['nullable', 'array'], 'deadline_at' => ['nullable', 'date']]);
        return response()->json(['success' => true, 'data' => $this->exaSkills->createTrainingProgram($request->user(), $organization, $payload)], 201);
    }

    public function createEmployerOpportunity(Request $request, SkillsOrganization $organization): JsonResponse
    {
        $payload = $request->validate(['title' => ['required', 'string', 'max:180'], 'description' => ['required', 'string'], 'type' => ['nullable', 'string'], 'employment_type' => ['nullable', 'string'], 'required_skills' => ['nullable', 'array'], 'preferred_skills' => ['nullable', 'array'], 'required_credentials' => ['nullable', 'array'], 'compensation_label' => ['nullable', 'string'], 'location_type' => ['nullable', 'string'], 'deadline_at' => ['nullable', 'date']]);
        try {
            return response()->json(['success' => true, 'data' => $this->exaSkills->createEmployerOpportunity($request->user(), $organization, $payload)], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function payoutStatus(SkillsInstructorPayout $payout): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $payout]);
    }

    public function challenges(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaSkills->challenges((int) $request->query('per_page', 15))]);
    }

    public function opportunities(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaSkills->opportunities((int) $request->query('per_page', 15))]);
    }

    public function purchaseCourse(Request $request, string $course): JsonResponse
    {
        try {
            $purchase = $this->exaSkills->purchaseCourse(
                $request->user(),
                $this->exaSkills->course($course),
                $request->header('Idempotency-Key')
            );
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Course purchase completed.', 'data' => $purchase], 201);
    }

    public function fundChallenge(Request $request, string $challenge): JsonResponse
    {
        try {
            $escrow = $this->exaSkills->fundChallengeEscrow($request->user(), $challenge, $request->header('Idempotency-Key'));
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Challenge reward escrow funded.', 'data' => $escrow], 201);
    }    public function submitChallenge(Request $request, string $challenge): JsonResponse
    {
        $payload = $request->validate([
            'description' => ['nullable', 'string', 'max:6000'],
            'repository_url' => ['nullable', 'url', 'max:255'],
            'demo_url' => ['nullable', 'url', 'max:255'],
            'attachments' => ['nullable', 'array'],
        ]);

        try {
            $submission = $this->exaSkills->submitChallenge($request->user(), $challenge, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Challenge submission saved.', 'data' => $submission], 201);
    }

    public function applyOpportunity(Request $request, string $opportunity): JsonResponse
    {
        $payload = $request->validate([
            'cover_note' => ['nullable', 'string', 'max:6000'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
        ]);

        try {
            $application = $this->exaSkills->applyOpportunity($request->user(), $opportunity, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Opportunity application submitted.', 'data' => $application], 201);
    }

    public function verifyCredential(string $credential): JsonResponse
    {
        $record = $this->exaSkills->verifyCredential($credential);

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Credential not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $record]);
    }

    public function adminOverview(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaSkills->adminOverview()]);
    }
}
