<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PortfolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PortfolioController extends Controller
{
    public function __construct(private readonly PortfolioService $portfolioService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $baseCurrency = $request->query('base_currency', 'USDT');

        try {
            $portfolio = $this->portfolioService->getUserPortfolioValue(
                $request->user()->id,
                $baseCurrency,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Portfolio value retrieved successfully.',
            'data' => $portfolio,
        ]);
    }
}
