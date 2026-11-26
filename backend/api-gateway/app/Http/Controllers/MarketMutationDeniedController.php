<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class MarketMutationDeniedController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['message' => 'Production market management requires internal market governance authority.'], 403);
    }
}
