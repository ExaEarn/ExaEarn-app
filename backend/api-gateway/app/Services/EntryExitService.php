<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserTradingProfile;
use App\Models\TradingSignal;

class EntryExitService
{
    public function __construct(private TradingSignalService $signalService)
    {
    }

    /**
     * Calculate optimal entry and exit points based on user profile and signal
     */
    public function suggestEntryExit(User $user, TradingSignal $signal, array $marketData = []): array
    {
        $profile = $user->tradingProfile;

        if (!$profile) {
            return $this->getDefaultEntryExit($signal);
        }

        $suggestion = [
            'entry_price' => $signal->suggested_entry,
            'stop_loss' => $signal->suggested_stop_loss,
            'take_profit' => $signal->suggested_take_profit,
            'position_size' => $this->calculatePositionSize($profile, $signal),
            'leverage' => $this->calculateRecommendedLeverage($profile, $signal),
            'risk_per_trade' => $this->calculateRiskPerTrade($profile, $signal),
            'risk_reward_ratio' => $signal->risk_reward_ratio,
            'entry_strategy' => $this->getEntryStrategy($profile, $signal),
            'exit_strategy' => $this->getExitStrategy($profile, $signal),
        ];

        // Adjust based on market conditions
        if (!empty($marketData)) {
            $suggestion = $this->adjustForMarketConditions($suggestion, $marketData);
        }

        return $suggestion;
    }

    /**
     * Calculate optimal position size based on risk management rules
     */
    private function calculatePositionSize(UserTradingProfile $profile, TradingSignal $signal): float
    {
        $accountBalance = $profile->account_balance ?? 10000;
        $riskPercentage = $this->getRiskPercentage($profile);
        
        // Calculate maximum risk in dollars
        $maxRiskAmount = $accountBalance * ($riskPercentage / 100);

        // Calculate distance from entry to stop loss
        $stopDistance = abs($signal->suggested_entry - $signal->suggested_stop_loss);

        if ($stopDistance == 0) {
            return $accountBalance * 0.01; // Default to 1% of account
        }

        // Position size = Risk Amount / Stop Distance
        $positionSize = $maxRiskAmount / $stopDistance;

        // Cap at maximum position size if set
        if ($profile->max_position_size) {
            $positionSize = min($positionSize, $profile->max_position_size);
        }

        return round($positionSize, 8);
    }

    /**
     * Determine risk percentage based on skill level and risk tolerance
     */
    private function getRiskPercentage(UserTradingProfile $profile): float
    {
        $baseRisk = match ($profile->skill_level) {
            'beginner' => 0.5,  // 0.5% per trade
            'intermediate' => 1.0,  // 1% per trade
            'advanced' => 2.0,  // 2% per trade
            default => 1.0,
        };

        $riskMultiplier = match ($profile->risk_tolerance) {
            'low' => 0.5,
            'medium' => 1.0,
            'high' => 1.5,
            default => 1.0,
        };

        return $baseRisk * $riskMultiplier;
    }

    /**
     * Calculate recommended leverage based on profile and signal
     */
    private function calculateRecommendedLeverage(UserTradingProfile $profile, TradingSignal $signal): float
    {
        $baseLeverage = match ($profile->skill_level) {
            'beginner' => 1.0,
            'intermediate' => 2.0,
            'advanced' => 5.0,
            default => 1.0,
        };

        // Reduce leverage if signal confidence is low
        if ($signal->confidence < 50) {
            $baseLeverage *= 0.5;
        } elseif ($signal->confidence < 70) {
            $baseLeverage *= 0.75;
        }

        // Cap at user preference
        return min($baseLeverage, $profile->preferred_leverage);
    }

    /**
     * Calculate risk amount in dollars for this trade
     */
    private function calculateRiskPerTrade(UserTradingProfile $profile, TradingSignal $signal): float
    {
        $accountBalance = $profile->account_balance ?? 10000;
        $riskPercentage = $this->getRiskPercentage($profile);
        $maxRisk = $accountBalance * ($riskPercentage / 100);

        // Scale based on signal confidence
        $confidenceMultiplier = min(1.0, $signal->confidence / 50);

        return round($maxRisk * $confidenceMultiplier, 2);
    }

    /**
     * Determine entry strategy based on user skill and market conditions
     */
    private function getEntryStrategy(UserTradingProfile $profile, TradingSignal $signal): array
    {
        $strategy = [
            'type' => 'market',  // market, limit, grid, dca
            'levels' => 1,
            'description' => '',
        ];

        if ($profile->skill_level === 'beginner') {
            $strategy['type'] = 'market';
            $strategy['description'] = 'Enter at market price for immediate execution';
        } elseif ($profile->skill_level === 'intermediate') {
            if ($signal->confidence > 75) {
                $strategy['type'] = 'limit';
                $strategy['levels'] = 1;
                $strategy['description'] = 'Enter at market price with limit order';
            } else {
                $strategy['type'] = 'grid';
                $strategy['levels'] = 3;
                $strategy['description'] = 'Scale in with 3 entry points';
            }
        } else {
            // Advanced
            if ($signal->confidence > 85) {
                $strategy['type'] = 'market';
                $strategy['description'] = 'High confidence signal - enter immediately';
            } elseif ($signal->confidence > 65) {
                $strategy['type'] = 'grid';
                $strategy['levels'] = 5;
                $strategy['description'] = 'Scale in with 5 entry points';
            } else {
                $strategy['type'] = 'dca';
                $strategy['levels'] = 10;
                $strategy['description'] = 'Dollar-cost average over 10 entries';
            }
        }

        return $strategy;
    }

    /**
     * Determine exit strategy
     */
    private function getExitStrategy(UserTradingProfile $profile, TradingSignal $signal): array
    {
        $strategy = [
            'type' => 'tp_sl',  // tp_sl, trailing_stop, time_exit, partial
            'take_profit_levels' => 1,
            'stop_loss_percent' => 2,
            'description' => '',
        ];

        if ($profile->skill_level === 'beginner') {
            $strategy['type'] = 'tp_sl';
            $strategy['take_profit_levels'] = 1;
            $strategy['stop_loss_percent'] = 2;
            $strategy['description'] = 'Simple take-profit and stop-loss';
        } elseif ($profile->skill_level === 'intermediate') {
            $strategy['type'] = 'partial';
            $strategy['take_profit_levels'] = 2;
            $strategy['stop_loss_percent'] = 1.5;
            $strategy['description'] = 'Exit in 2 parts: take profit at 50%, then scale out';
        } else {
            // Advanced
            if ($signal->confidence > 80) {
                $strategy['type'] = 'trailing_stop';
                $strategy['take_profit_levels'] = 3;
                $strategy['stop_loss_percent'] = 1;
                $strategy['description'] = 'Trail stop-loss while riding winners';
            } else {
                $strategy['type'] = 'partial';
                $strategy['take_profit_levels'] = 3;
                $strategy['stop_loss_percent'] = 1.5;
                $strategy['description'] = 'Exit in 3 parts for better risk management';
            }
        }

        return $strategy;
    }

    /**
     * Adjust entry/exit suggestions based on real-time market conditions
     */
    private function adjustForMarketConditions(array $suggestion, array $marketData): array
    {
        $volatility = $marketData['volatility'] ?? 'medium';
        $trend = $marketData['trend'] ?? 'neutral';

        // In high volatility, widen stops
        if ($volatility === 'high') {
            $entryStopDistance = abs($suggestion['entry_price'] - $suggestion['stop_loss']);
            $suggestion['stop_loss'] = $suggestion['entry_price'] - ($entryStopDistance * 1.3);
        }

        // In strong trend, relax take profits to give winners room
        if ($trend === 'strong_uptrend' || $trend === 'strong_downtrend') {
            $entryTPDistance = abs($suggestion['take_profit'] - $suggestion['entry_price']);
            $suggestion['take_profit'] = $suggestion['entry_price'] + ($entryTPDistance * 1.2);
        }

        // In low liquidity, increase position size slightly less
        if ($marketData['liquidity'] === 'low') {
            $suggestion['position_size'] *= 0.8;
        }

        return $suggestion;
    }

    /**
     * Calculate multiple exit targets for scaling out
     */
    public function getScaledExitTargets(TradingSignal $signal, int $levels = 3): array
    {
        $entryPrice = $signal->suggested_entry;
        $takeProfit = $signal->suggested_take_profit;
        $distance = $takeProfit - $entryPrice;

        $targets = [];
        for ($i = 1; $i <= $levels; $i++) {
            $targets[] = [
                'level' => $i,
                'price' => $entryPrice + ($distance * ($i / $levels)),
                'percent_to_exit' => 100 / $levels,
            ];
        }

        return $targets;
    }

    /**
     * Calculate trailing stop distance
     */
    public function getTrailingStopDistance(TradingSignal $signal, UserTradingProfile $profile): float
    {
        $riskRewardRatio = $signal->risk_reward_ratio;
        $baseTrail = 1.0; // 1%

        if ($riskRewardRatio > 2) {
            $baseTrail = 1.5; // Higher risk reward = more trail
        } elseif ($riskRewardRatio < 1) {
            $baseTrail = 0.5; // Lower risk reward = tighter trail
        }

        // Adjust for volatility level
        if ($signal->volatility_level === 'high') {
            $baseTrail *= 1.5;
        } elseif ($signal->volatility_level === 'low') {
            $baseTrail *= 0.7;
        }

        return round($baseTrail, 2);
    }

    /**
     * Get default entry/exit if no profile
     */
    private function getDefaultEntryExit(TradingSignal $signal): array
    {
        return [
            'entry_price' => $signal->suggested_entry,
            'stop_loss' => $signal->suggested_stop_loss,
            'take_profit' => $signal->suggested_take_profit,
            'position_size' => 0,
            'leverage' => 1,
            'risk_per_trade' => 0,
            'risk_reward_ratio' => $signal->risk_reward_ratio,
            'entry_strategy' => ['type' => 'market', 'description' => 'No profile configured'],
            'exit_strategy' => ['type' => 'tp_sl', 'description' => 'No profile configured'],
        ];
    }
}
