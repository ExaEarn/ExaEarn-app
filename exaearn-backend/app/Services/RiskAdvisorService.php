<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserTradingProfile;
use App\Models\TradingSignal;
use Illuminate\Support\Collection;

class RiskAdvisorService
{
    public function __construct(private TradingSignalService $signalService)
    {
    }

    /**
     * Analyze overall risk exposure for a user
     */
    public function analyzeRiskExposure(User $user, array $openPositions = []): array
    {
        $profile = $user->tradingProfile;
        
        if (!$profile) {
            return ['warnings' => [], 'risk_score' => 0, 'is_safe' => true];
        }

        $warnings = [];
        $riskScore = 0;

        // Check leverage risk
        $leverageWarning = $this->checkLeverageRisk($profile, $openPositions);
        if ($leverageWarning) {
            $warnings[] = $leverageWarning;
            $riskScore += 30;
        }

        // Check position size risk
        $positionWarning = $this->checkPositionSizeRisk($profile, $openPositions);
        if ($positionWarning) {
            $warnings[] = $positionWarning;
            $riskScore += 25;
        }

        // Check daily loss risk
        $lossWarning = $this->checkDailyLossRisk($profile, $openPositions);
        if ($lossWarning) {
            $warnings[] = $lossWarning;
            $riskScore += 35;
        }

        // Check correlation risk
        $correlationWarning = $this->checkCorrelationRisk($openPositions);
        if ($correlationWarning) {
            $warnings[] = $correlationWarning;
            $riskScore += 20;
        }

        // Check concentration risk
        $concentrationWarning = $this->checkConcentrationRisk($openPositions);
        if ($concentrationWarning) {
            $warnings[] = $concentrationWarning;
            $riskScore += 15;
        }

        // Check signal alignment
        $signalWarning = $this->checkSignalAlignment($user, $openPositions);
        if ($signalWarning) {
            $warnings[] = $signalWarning;
            $riskScore += 10;
        }

        $riskScore = min(100, $riskScore);

        return [
            'warnings' => $warnings,
            'risk_score' => $riskScore,
            'is_safe' => $riskScore < 50,
            'profile' => [
                'skill_level' => $profile->skill_level,
                'risk_tolerance' => $profile->risk_tolerance,
                'preferred_leverage' => $profile->preferred_leverage,
                'max_position_size' => $profile->max_position_size,
            ],
        ];
    }

    /**
     * Check if position leverage is too high
     */
    private function checkLeverageRisk(UserTradingProfile $profile, array $openPositions): ?array
    {
        if (empty($openPositions)) {
            return null;
        }

        $maxLeverage = $profile->preferred_leverage;
        $currentLeverage = 0;

        foreach ($openPositions as $position) {
            $positionLeverage = $position['leverage'] ?? 1;
            if ($positionLeverage > $currentLeverage) {
                $currentLeverage = $positionLeverage;
            }
        }

        if ($currentLeverage > $maxLeverage) {
            return [
                'type' => 'leverage_risk',
                'severity' => $currentLeverage > $maxLeverage * 1.5 ? 'critical' : 'warning',
                'message' => "Current leverage ({$currentLeverage}x) exceeds your preference ({$maxLeverage}x). Consider reducing leverage to manage risk better.",
                'current' => $currentLeverage,
                'limit' => $maxLeverage,
            ];
        }

        return null;
    }

    /**
     * Check if position sizes are too large
     */
    private function checkPositionSizeRisk(UserTradingProfile $profile, array $openPositions): ?array
    {
        if (empty($openPositions) || !$profile->max_position_size) {
            return null;
        }

        $totalExposure = 0;
        $maxExposure = 0;

        foreach ($openPositions as $position) {
            $size = $position['size'] ?? 0;
            $totalExposure += $size;
            if ($size > $maxExposure) {
                $maxExposure = $size;
            }
        }

        if ($maxExposure > $profile->max_position_size) {
            return [
                'type' => 'position_size_risk',
                'severity' => $maxExposure > $profile->max_position_size * 1.5 ? 'critical' : 'warning',
                'message' => "Largest position ({$maxExposure}) exceeds your limit ({$profile->max_position_size}). Reduce position size to stay within risk parameters.",
                'current' => $maxExposure,
                'limit' => $profile->max_position_size,
            ];
        }

        return null;
    }

    /**
     * Check if daily loss limit is being approached
     */
    private function checkDailyLossRisk(UserTradingProfile $profile, array $openPositions): ?array
    {
        if (!$profile->daily_loss_limit) {
            return null;
        }

        // Calculate unrealized PnL
        $unrealizedPnL = 0;
        foreach ($openPositions as $position) {
            $unrealizedPnL += $position['unrealized_pnl'] ?? 0;
        }

        $remainingDaily = $profile->daily_loss_limit + $unrealizedPnL;

        if ($unrealizedPnL < 0) {
            $percentUsed = abs($unrealizedPnL) / $profile->daily_loss_limit * 100;

            if ($percentUsed > 75) {
                return [
                    'type' => 'daily_loss_limit',
                    'severity' => $percentUsed > 90 ? 'critical' : 'warning',
                    'message' => "Daily loss limit is {$percentUsed}% utilized. Unrealized losses: ${unrealizedPnL}. Consider closing positions or stopping new trades.",
                    'current_loss' => $unrealizedPnL,
                    'daily_limit' => $profile->daily_loss_limit,
                    'percent_used' => (int)$percentUsed,
                ];
            }
        }

        return null;
    }

    /**
     * Check if positions are highly correlated
     */
    private function checkCorrelationRisk(array $openPositions): ?array
    {
        if (count($openPositions) < 2) {
            return null;
        }

        // Simple check: count positions in same asset class
        $btcPositions = 0;
        $altPositions = 0;
        $forexPositions = 0;

        foreach ($openPositions as $position) {
            $symbol = $position['symbol'] ?? '';
            if (strpos($symbol, 'BTC') !== false) {
                $btcPositions++;
            } elseif (strpos($symbol, 'ETH') !== false || strpos($symbol, 'ALT') !== false) {
                $altPositions++;
            } else {
                $forexPositions++;
            }
        }

        $maxCategoryPositions = max($btcPositions, $altPositions, $forexPositions);

        if ($maxCategoryPositions / count($openPositions) > 0.7) {
            return [
                'type' => 'correlation_risk',
                'severity' => 'warning',
                'message' => "You have " . $maxCategoryPositions . " correlated positions. Consider diversifying to reduce portfolio correlation.",
                'correlated_count' => $maxCategoryPositions,
                'total_positions' => count($openPositions),
            ];
        }

        return null;
    }

    /**
     * Check for over-concentration in single symbol
     */
    private function checkConcentrationRisk(array $openPositions): ?array
    {
        if (empty($openPositions)) {
            return null;
        }

        $symbolExposure = [];
        $totalExposure = 0;

        foreach ($openPositions as $position) {
            $symbol = $position['symbol'] ?? 'unknown';
            $size = $position['size'] ?? 0;
            $symbolExposure[$symbol] = ($symbolExposure[$symbol] ?? 0) + $size;
            $totalExposure += $size;
        }

        if ($totalExposure > 0) {
            $maxConcentration = max($symbolExposure) / $totalExposure;

            if ($maxConcentration > 0.6) {
                $topSymbol = array_search(max($symbolExposure), $symbolExposure);
                $concentrationPercent = (int)($maxConcentration * 100);
                return [
                    'type' => 'concentration_risk',
                    'severity' => $maxConcentration > 0.8 ? 'critical' : 'warning',
                    'message' => "Your portfolio is {$concentrationPercent}% concentrated in {$topSymbol}. Consider diversifying.",
                    'concentration_symbol' => $topSymbol,
                    'concentration_percent' => $concentrationPercent,
                ];
            }
        }

        return null;
    }

    /**
     * Check if current positions align with active signals
     */
    private function checkSignalAlignment(User $user, array $openPositions): ?array
    {
        if (empty($openPositions)) {
            return null;
        }

        $misalignedPositions = [];

        foreach ($openPositions as $position) {
            $symbol = $position['symbol'] ?? null;
            $side = $position['side'] ?? null;

            if (!$symbol || !$side) {
                continue;
            }

            $signal = $this->signalService->getSignalBySymbol($user, $symbol);

            if ($signal && !$this->isPositionAlignedWithSignal($side, $signal->signal_type)) {
                $misalignedPositions[] = [
                    'symbol' => $symbol,
                    'position_side' => $side,
                    'signal_type' => $signal->signal_type,
                ];
            }
        }

        if (count($misalignedPositions) > 0) {
            return [
                'type' => 'signal_misalignment',
                'severity' => 'info',
                'message' => "You have " . count($misalignedPositions) . " position(s) not aligned with latest signals. Review for potential exits.",
                'misaligned_positions' => $misalignedPositions,
            ];
        }

        return null;
    }

    private function isPositionAlignedWithSignal(string $positionSide, string $signalType): bool
    {
        if ($signalType === 'HOLD') {
            return true;
        }

        if ($signalType === 'BUY') {
            return $positionSide === 'long';
        }

        if ($signalType === 'SELL') {
            return $positionSide === 'short';
        }

        return false;
    }

    /**
     * Validate if a proposed trade is safe given current profile and positions
     */
    public function validateProposedTrade(User $user, array $tradeProposal): array
    {
        $profile = $user->tradingProfile;
        
        if (!$profile) {
            return ['valid' => false, 'errors' => ['User trading profile not configured']];
        }

        $errors = [];

        // Check proposed leverage
        $proposedLeverage = $tradeProposal['leverage'] ?? 1;
        if ($proposedLeverage > $profile->preferred_leverage) {
            $errors[] = "Proposed leverage ({$proposedLeverage}x) exceeds your preference ({$profile->preferred_leverage}x)";
        }

        // Check proposed position size
        if ($profile->max_position_size && ($tradeProposal['size'] ?? 0) > $profile->max_position_size) {
            $errors[] = "Proposed position size exceeds your limit";
        }

        // Check if skill level matches trade complexity
        if ($profile->skill_level === 'beginner' && $proposedLeverage > 2) {
            $errors[] = "Leverage trading not recommended for beginners. Start with spot trading.";
        }

        // Calculate position PnL impact
        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        return ['valid' => true, 'errors' => []];
    }

    /**
     * Get personalized risk recommendations
     */
    public function getRiskRecommendations(User $user): Collection
    {
        $profile = $user->tradingProfile;
        $recommendations = collect();

        if (!$profile) {
            return $recommendations;
        }

        if ($profile->skill_level === 'beginner') {
            $recommendations->push([
                'id' => 'beginner_leverage',
                'title' => 'Start Conservative',
                'description' => 'As a beginner, start with 1x leverage to learn without excessive risk.',
                'priority' => 'high',
            ]);
            $recommendations->push([
                'id' => 'beginner_education',
                'title' => 'Risk Management First',
                'description' => 'Master position sizing and stop-loss placement before increasing leverage.',
                'priority' => 'high',
            ]);
        }

        if ($profile->risk_tolerance === 'low') {
            $recommendations->push([
                'id' => 'low_risk_diversify',
                'title' => 'Diversify Holdings',
                'description' => 'Spread capital across multiple symbols to reduce single-asset risk.',
                'priority' => 'medium',
            ]);
        }

        if ($profile->daily_loss_limit) {
            $recommendations->push([
                'id' => 'daily_limit_set',
                'title' => 'Daily Loss Limit Active',
                'description' => "You have a ${$profile->daily_loss_limit} daily loss limit. Monitor it closely.",
                'priority' => 'medium',
            ]);
        }

        return $recommendations;
    }
}
