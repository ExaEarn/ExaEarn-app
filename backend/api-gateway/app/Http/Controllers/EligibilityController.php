<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CompliancePolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EligibilityController extends Controller
{
    public function __construct(private readonly CompliancePolicyService $compliance)
    {
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'products' => $this->compliance->getProductEligibility($request->user()),
                'evaluated_at' => now()->toISOString(),
            ],
        ]);
    }
}
