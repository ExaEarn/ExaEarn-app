<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
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
