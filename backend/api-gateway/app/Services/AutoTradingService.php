<?php

namespace App\Services;

use App\Models\User;
use App\Models\AutoTradingStrategy;
use App\Models\AutoStrategyExecution;
use App\Models\TradingSignal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutoTradingService
{
    public function __construct(
        private TradingSignalService $signalService,
        private EntryExitService $entryExitService,
        private RiskAdvisorService $riskAdvisor,
    ) {
    }

    /**
     * Execute an active auto-trading strategy
     */
    public function executeStrategy(AutoTradingStrategy $strategy, array $marketData = []): ?AutoStrategyExecution
    {
        // Check if strategy is enabled and last execution timing
        if (!$strategy->is_active || !$this->isTimeToExecute($strategy)) {
            return null;
        }

        // Check risk limits
        if (!$strategy->isRiskWithinLimits()) {
            Log::warning("Strategy {$strategy->id} exceeds risk limits", [
                'max_drawdown' => $strategy->max_drawdown_percent,
                'daily_loss' => $strategy->daily_loss_limit,
                'pnl' => $strategy->total_pnl,
            ]);
            return null;
        }

        $user = $strategy->user;
        $mode = (string) ($user->tradingProfile?->ai_trade_mode ?? 'assist');
        if ($mode !== 'auto') {
            Log::info("Auto strategy blocked: user mode is not auto", [
                'strategy_id' => $strategy->id,
                'user_id' => $user->id,
                'mode' => $mode,
            ]);
            return null;
        }

        // Verify risk is acceptable for user
        $riskAnalysis = $this->riskAdvisor->analyzeRiskExposure($user);
        if ($riskAnalysis['risk_score'] > 70) {
            Log::warning("User {$user->id} has high portfolio risk", [
                'risk_score' => $riskAnalysis['risk_score'],
            ]);
            return null;
        }

        // Generate or retrieve signal
        $signal = $this->signalService->getSignalBySymbol($user, $strategy->symbol);

        if (!$signal || !$signal->is_active) {
            return null;
        }

        // Get entry/exit suggestions
        $suggestion = $this->entryExitService->suggestEntryExit($user, $signal, $marketData);

        // Execute the trade
        $execution = $this->executeTrade($strategy, $signal, $suggestion);

        return $execution;
    }

    /**
     * Check if enough time has passed since last execution
     */
    private function isTimeToExecute(AutoTradingStrategy $strategy): bool
    {
        if (!$strategy->last_executed_at) {
            return true;
        }

        $config = $strategy->config ?? [];
        $minInterval = $config['execution_interval_minutes'] ?? 60;

        return $strategy->last_executed_at->addMinutes($minInterval)->isPast();
    }

    /**
     * Execute a trade based on strategy
     */
    private function executeTrade(AutoTradingStrategy $strategy, TradingSignal $signal, array $suggestion): AutoStrategyExecution
    {
        $config = $strategy->config ?? [];
        $type = $strategy->type;

        // Generate execution plan based on strategy type
        $executionPlan = match ($type) {
            'trend_following' => $this->planTrendFollowingTrade($signal, $suggestion, $config),
            'scalping' => $this->planScalpingTrade($signal, $suggestion, $config),
            'grid_trading' => $this->planGridTradingTrade($signal, $suggestion, $config),
            default => $this->planDefaultTrade($signal, $suggestion),
        };

        // Create execution record
        $execution = $strategy->executions()->create([
            'user_id' => $strategy->user_id,
            'order_uuid' => uniqid('auto_', true),
            'side' => $executionPlan['side'],
            'quantity' => $executionPlan['quantity'],
            'entry_price' => $executionPlan['entry_price'],
            'exit_price' => null,
            'pnl' => 0,
            'status' => 'pending',
            'signal_data' => [
                'signal_id' => $signal->id,
                'confidence' => $signal->confidence,
                'reasoning' => $signal->ai_reasoning,
            ],
            'executed_at' => now(),
        ]);

        // Update strategy metrics
        $strategy->update(['last_executed_at' => now()]);

        Log::info("Auto trade executed", [
            'strategy_id' => $strategy->id,
            'execution_id' => $execution->id,
            'side' => $executionPlan['side'],
            'quantity' => $executionPlan['quantity'],
        ]);

        return $execution;
    }

    /**
     * Plan trend-following trade
     */
    private function planTrendFollowingTrade(TradingSignal $signal, array $suggestion, array $config): array
    {
        $side = $signal->signal_type === 'BUY' ? 'long' : 'short';
        
        return [
            'side' => $side,
            'quantity' => $suggestion['position_size'],
            'entry_price' => $suggestion['entry_price'],
            'stop_loss' => $suggestion['stop_loss'],
            'take_profit' => $suggestion['take_profit'],
        ];
    }

    /**
     * Plan scalping trade (smaller position, tighter stops)
     */
    private function planScalpingTrade(TradingSignal $signal, array $suggestion, array $config): array
    {
        $side = $signal->signal_type === 'BUY' ? 'long' : 'short';
        
        // Scalping uses smaller positions
        $quantity = $suggestion['position_size'] * 0.5;

        // Scalping uses tighter stops
        $stopDistance = abs($suggestion['entry_price'] - $suggestion['stop_loss']);
        $stopLoss = $side === 'long' 
            ? $suggestion['entry_price'] - ($stopDistance * 0.5)
            : $suggestion['entry_price'] + ($stopDistance * 0.5);

        // Scalping targets smaller profits
        $tpDistance = abs($suggestion['take_profit'] - $suggestion['entry_price']);
        $takeProfit = $side === 'long'
            ? $suggestion['entry_price'] + ($tpDistance * 0.3)
            : $suggestion['entry_price'] - ($tpDistance * 0.3);

        return [
            'side' => $side,
            'quantity' => $quantity,
            'entry_price' => $suggestion['entry_price'],
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
        ];
    }

    /**
     * Plan grid trading (multiple entry/exit points)
     */
    private function planGridTradingTrade(TradingSignal $signal, array $suggestion, array $config): array
    {
        $gridLevels = $config['grid_levels'] ?? 3;
        $side = $signal->signal_type === 'BUY' ? 'long' : 'short';

        // Grid trading splits position across levels
        $quantityPerLevel = $suggestion['position_size'] / $gridLevels;

        return [
            'side' => $side,
            'quantity' => $suggestion['position_size'],
            'entry_price' => $suggestion['entry_price'],
            'stop_loss' => $suggestion['stop_loss'],
            'take_profit' => $suggestion['take_profit'],
            'grid_levels' => $gridLevels,
            'quantity_per_level' => $quantityPerLevel,
        ];
    }

    /**
     * Plan default trade
     */
    private function planDefaultTrade(TradingSignal $signal, array $suggestion): array
    {
        $side = $signal->signal_type === 'BUY' ? 'long' : 'short';

        return [
            'side' => $side,
            'quantity' => $suggestion['position_size'],
            'entry_price' => $suggestion['entry_price'],
            'stop_loss' => $suggestion['stop_loss'],
            'take_profit' => $suggestion['take_profit'],
        ];
    }

    /**
     * Close an active execution
     */
    public function closeExecution(AutoStrategyExecution $execution, float $exitPrice): void
    {
        $pnl = $this->calculatePnL($execution, $exitPrice);

        $execution->update([
            'exit_price' => $exitPrice,
            'pnl' => $pnl,
            'status' => 'completed',
            'closed_at' => now(),
        ]);

        // Update strategy metrics
        $strategy = $execution->strategy;
        $strategy->increment('trades_executed');
        $strategy->increment('total_pnl', $pnl);

        if ($strategy->trades_executed > 0) {
            $winCount = $strategy->executions()
                ->where('status', 'completed')
                ->where('pnl', '>', 0)
                ->count();

            $winRate = ($winCount / $strategy->trades_executed) * 100;
            $strategy->update(['win_rate' => (int)$winRate]);
        }

        Log::info("Auto trade closed", [
            'execution_id' => $execution->id,
            'exit_price' => $exitPrice,
            'pnl' => $pnl,
        ]);
    }

    /**
     * Mark execution as failed
     */
    public function failExecution(AutoStrategyExecution $execution, string $reason): void
    {
        $execution->update([
            'status' => 'failed',
            'reason' => $reason,
        ]);

        Log::warning("Auto trade failed", [
            'execution_id' => $execution->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Get all active strategies for a user
     */
    public function getUserStrategies(User $user, bool $activeOnly = true): \Illuminate\Database\Eloquent\Collection
    {
        $query = $user->autoTradingStrategies();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    /**
     * Get performance metrics for a strategy
     */
    public function getStrategyMetrics(AutoTradingStrategy $strategy): array
    {
        $executions = $strategy->executions()
            ->where('status', 'completed')
            ->get();

        if ($executions->isEmpty()) {
            return [
                'trades' => 0,
                'wins' => 0,
                'losses' => 0,
                'win_rate' => 0,
                'avg_win' => 0,
                'avg_loss' => 0,
                'total_pnl' => 0,
                'profit_factor' => 0,
            ];
        }

        $wins = $executions->filter(fn($e) => $e->pnl > 0);
        $losses = $executions->filter(fn($e) => $e->pnl < 0);

        $totalWins = $wins->sum('pnl');
        $totalLosses = $losses->sum('pnl');
        $totalPnL = $executions->sum('pnl');

        return [
            'trades' => $executions->count(),
            'wins' => $wins->count(),
            'losses' => $losses->count(),
            'win_rate' => ($wins->count() / $executions->count()) * 100,
            'avg_win' => $wins->count() > 0 ? $totalWins / $wins->count() : 0,
            'avg_loss' => $losses->count() > 0 ? $totalLosses / $losses->count() : 0,
            'total_pnl' => $totalPnL,
            'profit_factor' => $totalLosses !== 0 ? abs($totalWins / $totalLosses) : 0,
        ];
    }

    private function calculatePnL(AutoStrategyExecution $execution, float $exitPrice): float
    {
        $quantity = $execution->quantity;
        $entryPrice = $execution->entry_price;

        if ($execution->side === 'long') {
            return ($exitPrice - $entryPrice) * $quantity;
        }

        return ($entryPrice - $exitPrice) * $quantity;
    }
}
