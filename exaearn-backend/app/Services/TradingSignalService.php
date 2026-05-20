<?php

namespace App\Services;

use App\Models\PriceFeed;
use App\Models\TradingSignal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TradingSignalService
{
    /**
     * Generate trading signals based on technical analysis
     */
    public function generateSignal(User $user, string $symbol, array $marketData): TradingSignal
    {
        $indicators = $this->calculateTechnicalIndicators($symbol, $marketData);
        $signal = $this->analyzeIndicators($indicators, $marketData);
        
        $tradingSignal = $user->tradingSignals()->create([
            'symbol' => $symbol,
            'signal_type' => $signal['type'],
            'confidence' => $signal['confidence'],
            'reason' => $signal['reason'],
            'technical_indicators' => $indicators,
            'suggested_entry' => $signal['entry'],
            'suggested_stop_loss' => $signal['stop_loss'],
            'suggested_take_profit' => $signal['take_profit'],
            'market_condition' => $signal['market_condition'],
            'volatility_level' => $signal['volatility'],
            'trend_strength' => $signal['trend_strength'],
            'risk_reward_ratio' => $signal['risk_reward_ratio'],
            'ai_reasoning' => $signal['reasoning'],
            'is_active' => true,
            'expires_at' => now()->addHours(24),
        ]);

        return $tradingSignal;
    }

    /**
     * Calculate technical indicators
     */
    private function calculateTechnicalIndicators(string $symbol, array $marketData): array
    {
        $price = $marketData['current_price'] ?? 0;
        $high = $marketData['high_24h'] ?? $price;
        $low = $marketData['low_24h'] ?? $price;
        $volume = $marketData['volume_24h'] ?? 0;
        
        // RSI - Relative Strength Index
        $rsi = $this->calculateRSI($marketData['prices'] ?? [$price]);
        
        // MACD
        $macd = $this->calculateMACD($marketData['prices'] ?? [$price]);
        
        // Bollinger Bands
        $bb = $this->calculateBollingerBands($marketData['prices'] ?? [$price], $price);
        
        // Support/Resistance
        $sr = $this->calculateSupportResistance($high, $low, $price);
        
        // Volume Analysis
        $volumeStrength = $this->analyzeVolumeStrength($volume, $marketData['avg_volume'] ?? $volume);
        
        return [
            'rsi' => $rsi,
            'macd' => $macd,
            'bollinger_bands' => $bb,
            'support_resistance' => $sr,
            'volume_strength' => $volumeStrength,
            'price' => $price,
            'high_24h' => $high,
            'low_24h' => $low,
            'volume_24h' => $volume,
        ];
    }

    /**
     * Analyze indicators to generate signal
     */
    private function analyzeIndicators(array $indicators, array $marketData): array
    {
        $rsi = $indicators['rsi'];
        $macd = $indicators['macd'];
        $bb = $indicators['bollinger_bands'];
        $volume = $indicators['volume_strength'];
        
        $buySignals = 0;
        $sellSignals = 0;
        $confidence = 50;
        
        // RSI Analysis
        if ($rsi < 30) {
            $buySignals++;
            $confidence += 10;
        } elseif ($rsi > 70) {
            $sellSignals++;
            $confidence += 10;
        }
        
        // MACD Analysis
        if ($macd['signal'] === 'bullish') {
            $buySignals++;
            $confidence += 15;
        } elseif ($macd['signal'] === 'bearish') {
            $sellSignals++;
            $confidence += 15;
        }
        
        // Bollinger Bands Analysis
        if ($bb['position'] === 'lower_band') {
            $buySignals++;
            $confidence += 10;
        } elseif ($bb['position'] === 'upper_band') {
            $sellSignals++;
            $confidence += 10;
        }
        
        // Volume Analysis
        if ($volume > 1.2) {
            if ($buySignals > $sellSignals) {
                $confidence += 10;
            } elseif ($sellSignals > $buySignals) {
                $confidence += 10;
            }
        }
        
        $signalType = 'HOLD';
        if ($buySignals > $sellSignals) {
            $signalType = 'BUY';
        } elseif ($sellSignals > $buySignals) {
            $signalType = 'SELL';
        }
        
        $confidence = min(100, max(0, $confidence));
        
        return [
            'type' => $signalType,
            'confidence' => (int)$confidence,
            'entry' => $this->calculateEntryPrice($indicators),
            'stop_loss' => $this->calculateStopLoss($signalType, $indicators),
            'take_profit' => $this->calculateTakeProfit($signalType, $indicators),
            'market_condition' => $this->getMarketCondition($rsi),
            'volatility' => $this->getVolatilityLevel($indicators),
            'trend_strength' => $this->calculateTrendStrength($indicators),
            'risk_reward_ratio' => $this->calculateRiskRewardRatio($indicators),
            'reasoning' => $this->generateReasoning($buySignals, $sellSignals, $indicators),
        ];
    }

    private function calculateRSI(array $prices, int $period = 14): float
    {
        if (count($prices) < $period + 1) {
            return 50;
        }

        $gains = 0;
        $losses = 0;

        for ($i = count($prices) - $period; $i < count($prices); $i++) {
            $change = $prices[$i] - $prices[$i - 1];
            if ($change > 0) {
                $gains += $change;
            } else {
                $losses += abs($change);
            }
        }

        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;

        if ($avgLoss == 0) {
            return 100;
        }

        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }

    private function calculateMACD(array $prices): array
    {
        $ema12 = $this->calculateEMA($prices, 12);
        $ema26 = $this->calculateEMA($prices, 26);
        $macdLine = $ema12 - $ema26;
        $signalLine = $this->calculateEMA([$macdLine], 9);
        
        return [
            'macd' => $macdLine,
            'signal' => $macdLine > $signalLine ? 'bullish' : 'bearish',
            'histogram' => $macdLine - $signalLine,
        ];
    }

    private function calculateEMA(array $prices, int $period): float
    {
        $multiplier = 2 / ($period + 1);
        $ema = array_sum(array_slice($prices, 0, $period)) / $period;

        for ($i = $period; $i < count($prices); $i++) {
            $ema = ($prices[$i] * $multiplier) + ($ema * (1 - $multiplier));
        }

        return $ema;
    }

    private function calculateBollingerBands(array $prices, float $currentPrice): array
    {
        $sma = array_sum(array_slice($prices, -20)) / 20;
        $variance = 0;

        foreach (array_slice($prices, -20) as $price) {
            $variance += pow($price - $sma, 2);
        }

        $stdDev = sqrt($variance / 20);
        $upperBand = $sma + (2 * $stdDev);
        $lowerBand = $sma - (2 * $stdDev);

        $position = 'middle';
        if ($currentPrice <= $lowerBand) {
            $position = 'lower_band';
        } elseif ($currentPrice >= $upperBand) {
            $position = 'upper_band';
        }

        return [
            'upper' => $upperBand,
            'middle' => $sma,
            'lower' => $lowerBand,
            'position' => $position,
        ];
    }

    private function calculateSupportResistance(float $high, float $low, float $current): array
    {
        $resistance = $high;
        $support = $low;
        
        return [
            'resistance' => $resistance,
            'support' => $support,
            'distance_to_resistance' => (($resistance - $current) / $current) * 100,
            'distance_to_support' => (($current - $support) / $current) * 100,
        ];
    }

    private function analyzeVolumeStrength(float $volume, float $avgVolume): float
    {
        if ($avgVolume == 0) {
            return 1.0;
        }
        return $volume / $avgVolume;
    }

    private function calculateEntryPrice(array $indicators): float
    {
        $bb = $indicators['bollinger_bands'];
        $price = $indicators['price'];

        if ($bb['position'] === 'lower_band') {
            return $bb['middle'];
        }

        return $price;
    }

    private function calculateStopLoss(string $signal, array $indicators): float
    {
        $sr = $indicators['support_resistance'];

        if ($signal === 'BUY') {
            return $sr['support'] * 0.98;
        }

        return $sr['resistance'] * 1.02;
    }

    private function calculateTakeProfit(string $signal, array $indicators): float
    {
        $sr = $indicators['support_resistance'];
        $price = $indicators['price'];

        if ($signal === 'BUY') {
            return $sr['resistance'] * 1.05;
        }

        return $sr['support'] * 0.95;
    }

    private function getMarketCondition(float $rsi): string
    {
        if ($rsi < 30) {
            return 'oversold';
        } elseif ($rsi > 70) {
            return 'overbought';
        }

        return 'neutral';
    }

    private function getVolatilityLevel(array $indicators): string
    {
        $bb = $indicators['bollinger_bands'];
        $width = ($bb['upper'] - $bb['lower']) / $bb['middle'];

        if ($width > 0.1) {
            return 'high';
        } elseif ($width < 0.03) {
            return 'low';
        }

        return 'medium';
    }

    private function calculateTrendStrength(array $indicators): float
    {
        $rsi = $indicators['rsi'];
        $strength = 0;

        if ($rsi > 50) {
            $strength = ($rsi - 50) / 50;
        } else {
            $strength = (50 - $rsi) / 50;
        }

        return round($strength, 2);
    }

    private function calculateRiskRewardRatio(array $indicators): float
    {
        $price = $indicators['price'];
        $stop = $indicators['support_resistance']['support'] * 0.98;
        $target = $indicators['support_resistance']['resistance'] * 1.05;

        $risk = abs($price - $stop);
        $reward = abs($target - $price);

        if ($risk == 0) {
            return 0;
        }

        return round($reward / $risk, 2);
    }

    private function generateReasoning(int $buySignals, int $sellSignals, array $indicators): string
    {
        $reasons = [];

        if ($indicators['rsi'] < 30) {
            $reasons[] = "RSI shows oversold conditions (${indicators['rsi']})";
        } elseif ($indicators['rsi'] > 70) {
            $reasons[] = "RSI shows overbought conditions (${indicators['rsi']})";
        }

        if ($indicators['macd']['signal'] === 'bullish') {
            $reasons[] = "MACD shows bullish crossover";
        }

        if ($indicators['volume_strength'] > 1.5) {
            $reasons[] = "Volume is ${indicators['volume_strength']}x average";
        }

        return implode(". ", $reasons) ?: "Market shows mixed signals";
    }

    public function getUserSignals(User $user, int $limit = 20): Collection
    {
        return $user->tradingSignals()
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function getSignalBySymbol(User $user, string $symbol): ?TradingSignal
    {
        return $user->tradingSignals()
            ->where('symbol', $symbol)
            ->where('is_active', true)
            ->latest()
            ->first();
    }
}
