<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ExaAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ExaAiController extends Controller
{
    public function __construct(private readonly ExaAiService $exaAi)
    {
    }

    public function overview(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaAi->getOverview($request->user())]);
    }

    public function plans(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaAi->getPlans()]);
    }

    public function subscription(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaAi->getCurrentSubscription($request->user())]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'plan_code' => ['required', 'string', 'max:40'],
            'billing_cycle' => ['nullable', 'string', 'in:monthly,annual'],
        ]);

        try {
            $subscription = $this->exaAi->subscribe($request->user(), $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'ExaAI subscription activated.', 'data' => $subscription], 201);
    }

    public function strategies(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaAi->strategiesFor($request->user())]);
    }

    public function allocations(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaAi->allocations($request->user())]);
    }

    public function activeAllocation(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaAi->activeAllocation($request->user())]);
    }

    public function allocationStore(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'asset' => ['required', 'string', 'max:20'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $allocation = $this->exaAi->createAllocation($request->user(), $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'ExaAI capital allocated.', 'data' => $allocation], 201);
    }

    public function sessionCurrent(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaAi->getCurrentSession($request->user())]);
    }

    public function sessionStore(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'allocation_id' => ['required', 'integer'],
            'strategy_id' => ['required', 'integer'],
            'mode' => ['nullable', 'string', 'in:live,demo'],
            'duration' => ['nullable', 'string', 'in:24h,7d,30d,90d,manual'],
            'max_daily_loss' => ['nullable', 'numeric', 'gte:0'],
            'max_drawdown_percent' => ['nullable', 'numeric', 'gte:0'],
            'max_open_positions' => ['nullable', 'integer', 'min:1', 'max:100'],
            'eligible_markets' => ['nullable', 'array'],
            'eligible_markets.*' => ['string', 'max:40'],
            'constraints' => ['nullable', 'array'],
        ]);

        try {
            $session = $this->exaAi->startSession($request->user(), $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'ExaAI session activated.', 'data' => $session], 201);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        try {
            $session = $this->exaAi->pauseSession($request->user(), $id);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'ExaAI paused.', 'data' => $session]);
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        try {
            $session = $this->exaAi->resumeSession($request->user(), $id);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'ExaAI resumed.', 'data' => $session]);
    }

    public function stop(Request $request, int $id): JsonResponse
    {
        try {
            $session = $this->exaAi->stopSession($request->user(), $id);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'ExaAI stopped.', 'data' => $session]);
    }

    public function positions(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 25);
        return response()->json(['success' => true, 'data' => $this->exaAi->positions($request->user(), $perPage)]);
    }

    public function trades(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 25);
        return response()->json(['success' => true, 'data' => $this->exaAi->trades($request->user(), $perPage)]);
    }

    public function performance(Request $request): JsonResponse
    {
        $period = (string) $request->query('period', '30d');
        return response()->json(['success' => true, 'data' => $this->exaAi->performance($request->user(), $period)]);
    }
}
