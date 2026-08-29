<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\UnifiedActivityCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnifiedActivityCenterController extends Controller
{
    public function __construct(private readonly UnifiedActivityCenterService $center)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $category = $request->query('category') ? strtolower((string) $request->query('category')) : null;

        return response()->json(['data' => $this->center->overview($request->user(), $perPage, $category)]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        return response()->json(['data' => $this->center->notifications($request->user(), $perPage)]);
    }

    public function activity(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $category = $request->query('category') ? strtolower((string) $request->query('category')) : null;

        return response()->json(['data' => $this->center->activity($request->user(), $perPage, $category)]);
    }
}
