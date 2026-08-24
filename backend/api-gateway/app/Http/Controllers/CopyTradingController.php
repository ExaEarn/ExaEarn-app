<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CopyOrder;
use App\Models\CopyStrategyPosition;
use App\Models\CopyRelationship;
use App\Models\Trader;
use App\Services\CopyRealtimeService;
use App\Services\CopyTradingService;
use App\Services\PublicCopyTradingEligibilityService;
use App\Services\PublicCopyTradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CopyTradingController extends Controller
{
    public function __construct(private readonly CopyTradingService $copyTrading)
    {
    }

    public function leaders(Request $request): JsonResponse
    {
        $category = (string) $request->query('category', 'top');
        $product = strtolower((string) $request->query('product', 'all'));
        $query = Trader::query()
            ->where('is_master_trader', true)
            ->where('status', 'active')
            ->whereJsonContains('supported_products', $product === 'all' ? 'futures' : $product);

        match ($category) {
            'low_risk' => $query->orderBy('risk_score')->orderByDesc('performance_score'),
            'most_copied' => $query->orderByDesc('followers_count'),
            'trending' => $query->orderByDesc('copy_aum')->orderByDesc('performance_score'),
            'new' => $query->latest('approved_at'),
            default => $query->orderByDesc('performance_score')->orderBy('risk_score'),
        };

        return response()->json([
            'data' => $query->paginate((int) $request->query('per_page', 20)),
        ]);
    }

    public function leader(int $id): JsonResponse
    {
        return response()->json(['data' => Trader::query()->with('user')->findOrFail($id)]);
    }

    public function follow(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'trader_id' => ['required', 'integer', 'exists:traders,id'],
            'amount_allocated' => ['required', 'numeric', 'gt:0'],
            'risk_level' => ['nullable', 'string', 'in:low,medium,high'],
            'product_scope' => ['nullable', 'string', 'in:spot,futures,all'],
            'copy_mode' => ['nullable', 'string', 'in:fixed_amount,proportional,fixed_ratio'],
            'fixed_amount_per_trade' => ['nullable', 'numeric', 'gt:0'],
            'fixed_ratio' => ['nullable', 'numeric', 'gt:0'],
            'max_amount_per_trade' => ['nullable', 'numeric', 'gt:0'],
            'max_daily_loss' => ['nullable', 'numeric', 'gt:0'],
            'max_drawdown' => ['nullable', 'numeric', 'gt:0'],
            'max_leverage' => ['nullable', 'integer', 'min:1', 'max:125'],
            'margin_preference' => ['nullable', 'string', 'in:isolated,cross,follow_lead'],
            'allowed_symbols' => ['nullable', 'array'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $eligibility = app(PublicCopyTradingEligibilityService::class)->evaluate($request->user(), $payload);
        if (!in_array($eligibility['status'], ['ALLOWED', 'ALLOWED_WITH_LIMITS'], true)) {
            return response()->json(['message' => 'Copy Trading is not available for this account.', 'eligibility' => $eligibility], 403);
        }

        try {
            $relationship = $this->copyTrading->followTrader(
                (int) $request->user()->id,
                (int) $payload['trader_id'],
                (string) $payload['amount_allocated'],
                (string) ($payload['risk_level'] ?? 'medium'),
                $payload,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $relationship], 201);
    }

    public function relationships(Request $request): JsonResponse
    {
        return response()->json([
            'data' => CopyRelationship::query()
                ->where('follower_id', $request->user()->id)
                ->with('trader')
                ->latest()
                ->get(),
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        return response()->json([
            'data' => CopyOrder::query()
                ->where('follower_user_id', $request->user()->id)
                ->with(['leadTradeEvent', 'followerOrder'])
                ->latest()
                ->paginate((int) $request->query('per_page', 50)),
        ]);
    }

    public function applyLead(Request $request): JsonResponse
    {
        if (!in_array(app(\App\Services\CopyPublicModeService::class)->flag('LEAD_TRADER_APPLICATIONS_PUBLIC'), ['LIMITED', 'ENABLED'], true)) {
            return response()->json(['message' => 'Lead Trader applications are not open right now.'], 403);
        }

        $payload = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'supported_products' => ['nullable', 'array'],
            'profit_share_rate' => ['nullable', 'numeric', 'gte:0', 'lte:1'],
            'preferred_markets' => ['nullable', 'array'],
            'terms_accepted' => ['accepted'],
            'prohibited_conduct_accepted' => ['accepted'],
        ]);

        return response()->json(['data' => $this->copyTrading->applyLeadTrader((int) $request->user()->id, $payload)], 201);
    }

    public function replay(Request $request, CopyRealtimeService $realtime): JsonResponse
    {
        $payload = $request->validate([
            'after_sequence' => ['nullable', 'integer', 'min:0'],
            'stream' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        return response()->json([
            'data' => $realtime->replay(
                (int) $request->user()->id,
                (int) ($payload['after_sequence'] ?? 0),
                (string) ($payload['stream'] ?? 'copy'),
                (int) ($payload['limit'] ?? 250),
            ),
        ]);
    }

    public function eligibility(Request $request, PublicCopyTradingEligibilityService $eligibility): JsonResponse
    {
        $payload = $request->validate([
            'product_scope' => ['nullable', 'string', 'in:spot,futures,all'],
            'country' => ['nullable', 'string', 'size:2'],
            'allowed_symbols' => ['nullable', 'array'],
        ]);

        return response()->json(['data' => $eligibility->evaluate($request->user(), $payload)]);
    }

    public function acceptTerms(Request $request, PublicCopyTradingService $public): JsonResponse
    {
        $payload = $request->validate([
            'types' => ['required', 'array', 'min:1'],
            'types.*' => ['string', 'in:copy_trading_terms,risk_disclosure,futures_copy_disclosure,profit_share_terms'],
        ]);

        return response()->json(['data' => $public->acceptTerms((int) $request->user()->id, $payload['types'], $request->ip(), $request->userAgent())]);
    }

    public function updateFollow(Request $request, int $id): JsonResponse
    {
        $relationship = CopyRelationship::query()->where('follower_id', $request->user()->id)->findOrFail($id);
        $payload = $request->validate([
            'max_amount_per_trade' => ['nullable', 'numeric', 'gt:0'],
            'max_daily_loss' => ['nullable', 'numeric', 'gt:0'],
            'max_drawdown' => ['nullable', 'numeric', 'gt:0'],
            'max_leverage' => ['nullable', 'integer', 'min:1', 'max:125'],
            'allowed_symbols' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:active,paused'],
        ]);
        $relationship->fill($payload)->save();

        return response()->json(['data' => $relationship->fresh()]);
    }

    public function stopFollow(Request $request, int $id, PublicCopyTradingService $public): JsonResponse
    {
        $payload = $request->validate([
            'action' => ['nullable', 'string', 'in:STOP_NEW_TRADES,STOP_AND_CLOSE_COPIED_POSITIONS,DETACH_POSITION'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json(['data' => $public->stopRelationship(
            (int) $request->user()->id,
            $id,
            (string) ($payload['action'] ?? 'STOP_NEW_TRADES'),
            (string) ($payload['reason'] ?? 'User requested stop'),
        )]);
    }

    public function positions(Request $request): JsonResponse
    {
        return response()->json(['data' => CopyStrategyPosition::query()->where('follower_user_id', $request->user()->id)->latest()->get()]);
    }

    public function pnl(Request $request): JsonResponse
    {
        $relationships = CopyRelationship::query()->where('follower_id', $request->user()->id)->get();

        return response()->json(['data' => [
            'allocated' => (string) $relationships->sum('amount_allocated'),
            'available' => (string) $relationships->sum('copy_available'),
            'reserved' => (string) $relationships->sum('copy_locked'),
            'deployed' => (string) CopyStrategyPosition::query()->where('follower_user_id', $request->user()->id)->sum('attributed_cost_basis'),
            'realized_pnl' => (string) CopyStrategyPosition::query()->where('follower_user_id', $request->user()->id)->sum('realized_pnl'),
        ]]);
    }

    public function leadProfile(Request $request): JsonResponse
    {
        return response()->json(['data' => Trader::query()->where('user_id', $request->user()->id)->where('is_master_trader', true)->first()]);
    }

    public function leadPerformance(Request $request): JsonResponse
    {
        $trader = Trader::query()->where('user_id', $request->user()->id)->where('is_master_trader', true)->firstOrFail();

        return response()->json(['data' => [
            'performance_score' => (string) $trader->performance_score,
            'risk_score' => (string) $trader->risk_score,
            'followers' => (int) $trader->followers_count,
            'copy_aum' => (string) $trader->copy_aum,
            'profit_share_rate' => (string) $trader->profit_share_rate,
        ]]);
    }

    public function leadEarnings(Request $request): JsonResponse
    {
        $trader = Trader::query()->where('user_id', $request->user()->id)->where('is_master_trader', true)->firstOrFail();

        return response()->json(['data' => [
            'accrued_profit_share' => (string) \App\Models\CopyProfitShareAccrual::query()->where('lead_trader_id', $trader->id)->sum('accrued_amount'),
            'pending_profit_share' => (string) \App\Models\CopyProfitShareAccrual::query()->where('lead_trader_id', $trader->id)->where('status', 'accrued')->sum('accrued_amount'),
        ]]);
    }

    public function complain(Request $request, PublicCopyTradingService $public): JsonResponse
    {
        $payload = $request->validate([
            'category' => ['required', 'string', 'in:unexpected_trade,slippage,lead_conduct,profit_share,skipped_trade,desync,liquidation,performance_display,other'],
            'message' => ['required', 'string', 'max:2000'],
            'lead_trader_id' => ['nullable', 'integer', 'exists:traders,id'],
            'copy_relationship_id' => ['nullable', 'integer', 'exists:copy_relationships,id'],
            'copy_order_id' => ['nullable', 'integer', 'exists:copy_orders,id'],
            'lead_trade_event_id' => ['nullable', 'integer', 'exists:copy_lead_trade_events,id'],
            'evidence' => ['nullable', 'array'],
        ]);

        return response()->json(['data' => $public->complaint((int) $request->user()->id, $payload)], 201);
    }
}
