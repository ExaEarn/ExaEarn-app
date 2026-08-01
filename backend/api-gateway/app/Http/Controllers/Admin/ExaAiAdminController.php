<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ExaAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExaAiAdminController extends Controller
{
    public function __construct(private readonly ExaAiService $exaAi)
    {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['data' => $this->exaAi->adminOverview()]);
    }

    public function plans(): JsonResponse
    {
        return response()->json(['data' => $this->exaAi->adminPlans()]);
    }

    public function strategies(): JsonResponse
    {
        return response()->json(['data' => $this->exaAi->adminStrategies()]);
    }

    public function sessions(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->exaAi->adminSessions((int) $request->query('per_page', 25))]);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->exaAi->adminSubscriptions((int) $request->query('per_page', 25))]);
    }

    public function trades(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->exaAi->adminTrades((int) $request->query('per_page', 25))]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->exaAi->adminAuditLogs((int) $request->query('per_page', 25))]);
    }
}