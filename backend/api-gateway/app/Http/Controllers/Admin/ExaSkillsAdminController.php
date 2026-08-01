<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ExaSkillsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ExaSkillsAdminController extends Controller
{
    public function __construct(private readonly ExaSkillsService $exaSkills)
    {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaSkills->adminOverview()]);
    }

    public function payoutChallengeWinner(Request $request, string $challenge): JsonResponse
    {
        $payload = $request->validate([
            'winner_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $escrow = $this->exaSkills->payoutChallengeWinner($request->user(), $challenge, (int) $payload['winner_user_id']);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Challenge winner paid.', 'data' => $escrow]);
    }
}
