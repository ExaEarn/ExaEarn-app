<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CopyComplaint;
use App\Models\CopyJurisdictionRule;
use App\Models\CopyMarketEligibility;
use App\Models\CopyTerm;
use App\Services\CopyPublicModeService;
use App\Services\CopyTradingOperationalReadinessService;
use App\Services\CopyTradingService;
use App\Services\PublicCopyTradingReadinessService;
use App\Services\PublicCopyTradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CopyTradingOperationsController extends Controller
{
    public function overview(CopyTradingOperationalReadinessService $readiness): JsonResponse
    {
        return response()->json([
            'data' => [
                'readiness' => $readiness->check(),
                'lead_traders' => DB::table('traders')->latest('id')->limit(100)->get(),
                'failed_copies' => DB::table('copy_orders')->where('status', 'failed')->latest('id')->limit(50)->get(),
                'surveillance_cases' => DB::table('copy_surveillance_cases')->latest('id')->limit(50)->get(),
                'complaints' => DB::table('copy_complaints')->latest('id')->limit(50)->get(),
                'load_runs' => DB::table('copy_load_runs')->latest('id')->limit(20)->get(),
            ],
        ]);
    }

    public function approveLead(Request $request, int $traderId, CopyTradingService $copyTrading): JsonResponse
    {
        return response()->json(['data' => $copyTrading->activateLeadTrader($traderId, (int) $request->user()->id)]);
    }

    public function copyOrders(): JsonResponse
    {
        return response()->json(['data' => DB::table('copy_orders')->latest('id')->limit(100)->get()]);
    }

    public function surveillance(): JsonResponse
    {
        return response()->json([
            'data' => [
                'events' => DB::table('copy_surveillance_events')->latest('id')->limit(100)->get(),
                'cases' => DB::table('copy_surveillance_cases')->latest('id')->limit(100)->get(),
            ],
        ]);
    }

    public function capacity(): JsonResponse
    {
        return response()->json([
            'data' => DB::table('traders')
                ->select(['id', 'lead_trader_uuid', 'display_name', 'status', 'followers_count', 'copy_aum', 'metadata'])
                ->where('is_master_trader', true)
                ->orderByDesc('copy_aum')
                ->limit(100)
                ->get(),
        ]);
    }

    public function control(Request $request, int $traderId): JsonResponse
    {
        $payload = $request->validate([
            'action' => ['required', 'string', 'in:PAUSE_NEW_COPY,PAUSE_LEAD,CLOSE_TO_NEW_FOLLOWERS,STOP_SPOT_COPY,STOP_FUTURES_COPY,DISABLE_COPY_MARKET'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $trader = \App\Models\Trader::query()->findOrFail($traderId);
        $metadata = array_merge($trader->metadata ?? [], [
            'last_admin_control' => [
                'action' => $payload['action'],
                'reason' => $payload['reason'],
                'admin_id' => $request->user()?->id,
                'at' => now()->toISOString(),
            ],
        ]);

        if (in_array($payload['action'], ['PAUSE_NEW_COPY', 'PAUSE_LEAD'], true)) {
            $trader->status = 'paused';
        }
        if ($payload['action'] === 'CLOSE_TO_NEW_FOLLOWERS') {
            $trader->status = 'closed_to_new_followers';
        }
        $trader->metadata = $metadata;
        $trader->save();

        return response()->json(['data' => $trader->fresh()]);
    }

    public function publicReadiness(PublicCopyTradingReadinessService $readiness): JsonResponse
    {
        return response()->json(['data' => $readiness->check()]);
    }

    public function requestEnable(Request $request, PublicCopyTradingService $public): JsonResponse
    {
        $payload = $request->validate([
            'mode' => ['required', 'string', 'in:SHADOW,INTERNAL,LIMITED_PUBLIC,PUBLIC'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return response()->json(['data' => $public->requestEnable((int) $request->user()->id, $payload['mode'], $payload['reason'])], 201);
    }

    public function approveEnable(Request $request, PublicCopyTradingService $public): JsonResponse
    {
        $payload = $request->validate([
            'request_id' => ['required', 'integer', 'exists:copy_public_activation_requests,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return response()->json(['data' => $public->approveEnable((int) $request->user()->id, (int) $payload['request_id'], $payload['reason'])]);
    }

    public function publicPause(Request $request, PublicCopyTradingService $public): JsonResponse
    {
        $payload = $request->validate([
            'state' => ['nullable', 'string', 'in:NEW_FOLLOWS_DISABLED,NEW_RISK_DISABLED,REDUCE_ONLY,COPY_PAUSED,EMERGENCY'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $public->pause((int) $request->user()->id, $payload['reason'], (string) ($payload['state'] ?? 'COPY_PAUSED'));

        return response()->json(['message' => 'Copy Trading public controls paused.']);
    }

    public function publicResume(Request $request, PublicCopyTradingService $public): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $public->resume((int) $request->user()->id, $payload['reason']);

        return response()->json(['message' => 'Copy Trading public controls resumed.']);
    }

    public function settings(Request $request, CopyPublicModeService $mode): JsonResponse
    {
        $payload = $request->validate([
            'copy_trading_mode' => ['nullable', 'string', 'in:DISABLED,SHADOW,INTERNAL,LIMITED_PUBLIC,PUBLIC,PAUSED,EMERGENCY'],
            'spot_copy_public' => ['nullable', 'string', 'in:DISABLED,LIMITED,ENABLED,PAUSED'],
            'futures_copy_public' => ['nullable', 'string', 'in:DISABLED,LIMITED,ENABLED,PAUSED'],
            'lead_trader_applications_public' => ['nullable', 'string', 'in:DISABLED,LIMITED,ENABLED,PAUSED'],
            'profit_share_public' => ['nullable', 'string', 'in:DISABLED,LIMITED,ENABLED,PAUSED'],
        ]);

        foreach ($payload as $key => $value) {
            $mode->set(strtoupper($key), $value, (int) $request->user()->id);
        }

        return response()->json(['message' => 'Copy Trading public settings updated.']);
    }

    public function markets(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $payload = $request->validate([
                'symbol' => ['required', 'string', 'max:32'],
                'spot_copy_public_enabled' => ['nullable', 'boolean'],
                'futures_copy_public_enabled' => ['nullable', 'boolean'],
                'maximum_copy_aum' => ['nullable', 'numeric', 'gte:0'],
                'maximum_slippage_bps' => ['nullable', 'numeric', 'gte:0'],
                'risk_tier' => ['nullable', 'string', 'max:24'],
                'status' => ['required', 'string', 'in:DISABLED,ENABLED,PAUSED'],
            ]);
            $payload['symbol'] = strtoupper(str_replace('/', '', $payload['symbol']));
            $market = CopyMarketEligibility::query()->updateOrCreate(
                ['symbol' => $payload['symbol']],
                $payload,
            );

            return response()->json(['data' => $market]);
        }

        return response()->json(['data' => CopyMarketEligibility::query()->orderBy('symbol')->get()]);
    }

    public function jurisdictions(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $payload = $request->validate([
                'country' => ['required', 'string', 'size:2'],
                'spot_copy_public' => ['required', 'string', 'in:DISABLED,LIMITED,ENABLED,PAUSED'],
                'futures_copy_public' => ['required', 'string', 'in:DISABLED,LIMITED,ENABLED,PAUSED'],
                'profit_share_public' => ['required', 'string', 'in:DISABLED,LIMITED,ENABLED,PAUSED'],
                'max_leverage' => ['required', 'integer', 'min:1', 'max:125'],
                'terms_version' => ['nullable', 'string', 'max:80'],
                'status' => ['required', 'string', 'in:DISABLED,ENABLED,PAUSED'],
            ]);
            $rule = CopyJurisdictionRule::query()->updateOrCreate(['country' => strtoupper($payload['country'])], $payload);

            return response()->json(['data' => $rule]);
        }

        return response()->json(['data' => CopyJurisdictionRule::query()->orderBy('country')->get()]);
    }

    public function terms(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $payload = $request->validate([
                'type' => ['required', 'string', 'max:60'],
                'version' => ['required', 'string', 'max:80'],
                'status' => ['required', 'string', 'in:DRAFT,ACTIVE,ARCHIVED'],
                'summary' => ['nullable', 'string', 'max:2000'],
            ]);
            $term = CopyTerm::query()->updateOrCreate(['type' => $payload['type'], 'version' => $payload['version']], $payload);

            return response()->json(['data' => $term]);
        }

        return response()->json(['data' => CopyTerm::query()->latest('id')->get()]);
    }

    public function complaints(Request $request): JsonResponse
    {
        if ($request->isMethod('patch')) {
            $payload = $request->validate([
                'complaint_id' => ['required', 'integer', 'exists:copy_complaints,id'],
                'status' => ['required', 'string', 'in:OPEN,REVIEWING,ESCALATED,RESOLVED,DISMISSED'],
                'resolution' => ['nullable', 'string', 'max:160'],
            ]);
            $complaint = CopyComplaint::query()->findOrFail((int) $payload['complaint_id']);
            $complaint->forceFill([
                'status' => $payload['status'],
                'resolution' => $payload['resolution'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();

            return response()->json(['data' => $complaint]);
        }

        return response()->json(['data' => CopyComplaint::query()->latest('id')->paginate((int) $request->query('per_page', 50))]);
    }
}
