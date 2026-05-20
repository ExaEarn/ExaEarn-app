<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmartOrderRoutingLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmartOrderRoutingAdminController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $query = SmartOrderRoutingLog::query();

        if ($request->filled('symbol')) {
            $query->where('symbol', strtoupper((string) $request->query('symbol')));
        }

        $from = $request->query('from');
        $to = $request->query('to');
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $rows = $query->latest()->limit(1000)->get();
        $total = $rows->count();
        $success = $rows->where('status', 'success')->count();
        $successRate = $total > 0 ? round(($success / $total) * 100, 2) : 0;
        $avgSlippage = round((float) $rows->avg(fn ($r) => (float) ($r->slippage_percent ?? 0)), 6);
        $avgExecutionMs = (int) round((float) $rows->avg(fn ($r) => (float) ($r->execution_time_ms ?? 0)));

        $sourceCounts = ['internal' => 0, 'binance' => 0, 'internal_fallback' => 0];
        foreach ($rows as $row) {
            foreach ((array) ($row->execution_result ?? []) as $step) {
                $src = (string) ($step['source'] ?? '');
                if (array_key_exists($src, $sourceCounts)) {
                    $sourceCounts[$src]++;
                }
            }
        }

        return response()->json([
            'data' => [
                'total_orders' => $total,
                'success_rate_percent' => $successRate,
                'avg_slippage_percent' => $avgSlippage,
                'avg_execution_time_ms' => $avgExecutionMs,
                'fill_source_ratio' => $sourceCounts,
            ],
        ]);
    }

    public function executions(Request $request): JsonResponse
    {
        $query = SmartOrderRoutingLog::query()->latest();

        if ($request->filled('symbol')) {
            $query->where('symbol', strtoupper((string) $request->query('symbol')));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        return response()->json([
            'data' => $query->paginate((int) $request->query('per_page', 50)),
        ]);
    }
}
