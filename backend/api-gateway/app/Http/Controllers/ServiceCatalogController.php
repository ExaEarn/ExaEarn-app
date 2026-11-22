<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ServiceCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceCatalogController extends Controller
{
    public function __construct(private readonly ServiceCatalogService $catalog)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->catalog->publicCatalog()]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->catalog->catalogForUser($request->user())]);
    }
}
