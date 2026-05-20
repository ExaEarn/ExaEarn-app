<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SportsController extends Controller
{
    public function __construct(private readonly SportsService $sportsService)
    {
    }

    public function athletes(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->sportsService->athleteDirectory($request->only([
                'sport',
                'country',
                'search',
                'verified_only',
            ]), (int) $request->query('per_page', 20)),
        ]);
    }

    public function athlete(int $athleteId): JsonResponse
    {
        return response()->json([
            'data' => $this->sportsService->athleteProfile($athleteId),
        ]);
    }

    public function saveAthleteProfile(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'sport' => ['required', 'string', 'max:64'],
            'country' => ['required', 'string', 'max:120'],
            'age' => ['required', 'integer', 'min:10', 'max:80'],
            'position' => ['nullable', 'string', 'max:120'],
            'club' => ['nullable', 'string', 'max:255'],
            'profile_photo' => ['nullable', 'string', 'max:255'],
            'highlight_video' => ['nullable', 'string', 'max:255'],
            'performance_statistics' => ['nullable', 'array'],
            'identity_metadata' => ['nullable', 'array'],
            'identity_verified' => ['nullable', 'boolean'],
            'is_searchable' => ['nullable', 'boolean'],
        ]);

        try {
            $athlete = $this->sportsService->createOrUpdateAthleteProfile($request->user(), $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $athlete], 201);
    }

    public function competitions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->sportsService->competitions($request->only(['sport', 'status']), (int) $request->query('per_page', 20)),
        ]);
    }

    public function createCompetition(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'sport' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string', 'max:32'],
            'reward_pool' => ['required', 'numeric', 'gte:0'],
            'manual_review_required' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $competition = $this->sportsService->createCompetition($request->user(), $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $competition], 201);
    }

    public function register(Request $request, int $competitionId): JsonResponse
    {
        $payload = $request->validate([
            'athlete_id' => ['nullable', 'integer', 'exists:athletes,id'],
        ]);

        try {
            $participant = $this->sportsService->registerAthleteForCompetition(
                $request->user(),
                $competitionId,
                $payload['athlete_id'] ?? null
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $participant], 201);
    }

    public function submitScores(Request $request, int $competitionId): JsonResponse
    {
        $payload = $request->validate([
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.athlete_id' => ['required', 'integer', 'exists:athletes,id'],
            'scores.*.score' => ['required', 'numeric', 'gte:0'],
            'scores.*.community_votes' => ['nullable', 'integer', 'gte:0'],
            'scores.*.review_notes' => ['nullable', 'string'],
            'scores.*.manual_verified' => ['nullable', 'boolean'],
        ]);

        try {
            $this->sportsService->submitCompetitionScores($request->user(), $competitionId, $payload['scores']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['status' => 'accepted'], 202);
    }

    public function finalize(Request $request, int $competitionId): JsonResponse
    {
        try {
            $competition = $this->sportsService->finalizeCompetition($request->user(), $competitionId);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $competition]);
    }

    public function leaderboard(Request $request, int $competitionId): JsonResponse
    {
        return response()->json([
            'data' => $this->sportsService->competitionLeaderboard($competitionId),
        ]);
    }

    public function athleteLeaderboard(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->sportsService->topAthletes(
                $request->query('sport') ? (string) $request->query('sport') : null,
                (int) $request->query('limit', config('sports.leaderboard_limit', 25))
            ),
        ]);
    }

    public function createSponsorship(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'athlete_id' => ['required', 'integer', 'exists:athletes,id'],
            'competition_id' => ['nullable', 'integer', 'exists:competitions,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'status' => ['nullable', 'string', 'max:32'],
            'milestone' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $sponsorship = $this->sportsService->createSponsorship($request->user(), $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $sponsorship], 201);
    }

    public function updateSponsorship(Request $request, int $sponsorshipId): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'string', 'max:32'],
        ]);

        try {
            $sponsorship = $this->sportsService->updateSponsorshipStatus($request->user(), $sponsorshipId, (string) $payload['status']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $sponsorship]);
    }

    public function inquiry(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'athlete_id' => ['required', 'integer', 'exists:athletes,id'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $inquiry = $this->sportsService->createInquiry($request->user(), $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $inquiry], 201);
    }
}
