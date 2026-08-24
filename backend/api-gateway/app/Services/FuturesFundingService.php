<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesFundingPayment;
use App\Models\FuturesFundingRate;
use App\Models\FuturesMarket;
use App\Models\FuturesPosition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FuturesFundingService
{
    public function __construct(private readonly SettlementService $settlements)
    {
    }

    public function calculateRate(FuturesMarket $market, string $markPrice, string $indexPrice): string
    {
        if (FinancialDecimal::compare($indexPrice, '0') <= 0) {
            throw new \RuntimeException('Index price must be positive for funding.');
        }

        $premium = FinancialDecimal::div(FinancialDecimal::sub($markPrice, $indexPrice), $indexPrice, 10);
        $interest = (string) config('futures.funding.interest_rate', '0.0001');
        $rate = FinancialDecimal::add($premium, $interest, 10);
        $max = (string) config('futures.funding.max_rate', '0.0075');
        $min = (string) config('futures.funding.min_rate', '-0.0075');

        if (FinancialDecimal::compare($rate, $max, 10) > 0) {
            return $max;
        }
        if (FinancialDecimal::compare($rate, $min, 10) < 0) {
            return $min;
        }

        return $rate;
    }

    public function recordRate(FuturesMarket $market, string $markPrice, string $indexPrice, Carbon $fundingTime): FuturesFundingRate
    {
        $rate = $this->calculateRate($market, $markPrice, $indexPrice);

        return FuturesFundingRate::query()->firstOrCreate(
            ['symbol' => $market->symbol, 'funding_time' => $fundingTime],
            [
                'futures_market_id' => $market->id,
                'index_price' => $indexPrice,
                'mark_price' => $markPrice,
                'funding_rate' => $rate,
                'metadata' => ['source_service' => 'futures_funding'],
            ]
        );
    }

    public function settlePosition(FuturesPosition $position, string $fundingRate, Carbon $fundingTime): FuturesFundingPayment
    {
        return DB::transaction(function () use ($fundingRate, $fundingTime, $position): FuturesFundingPayment {
            $position = FuturesPosition::query()->lockForUpdate()->findOrFail($position->id);
            $notional = FinancialDecimal::mul((string) $position->mark_price, (string) $position->quantity);
            $amount = FinancialDecimal::mul($notional, FinancialDecimal::abs($fundingRate, 10));
            $direction = FinancialDecimal::compare($fundingRate, '0', 10) >= 0
                ? ($position->side === 'long' ? 'pay' : 'receive')
                : ($position->side === 'long' ? 'receive' : 'pay');
            $reference = "futures-funding:{$position->id}:{$fundingTime->timestamp}";

            $payment = FuturesFundingPayment::query()->firstOrCreate(
                ['reference' => $reference],
                [
                    'user_id' => $position->user_id,
                    'futures_position_id' => $position->id,
                    'futures_market_id' => $position->futures_market_id,
                    'symbol' => $position->symbol,
                    'funding_rate' => $fundingRate,
                    'payment_amount' => $amount,
                    'direction' => $direction,
                    'funding_time' => $fundingTime,
                    'metadata' => ['source_service' => 'futures_funding'],
                ]
            );

            if ($payment->wasRecentlyCreated) {
                $this->settlements->futuresFundingPayment([
                    'user_id' => $position->user_id,
                    'asset' => 'USDT',
                    'amount' => $amount,
                    'direction' => $direction,
                    'metadata' => [
                        'symbol' => $position->symbol,
                        'position_id' => $position->id,
                        'funding_time' => $fundingTime->toISOString(),
                        'funding_rate' => $fundingRate,
                    ],
                ], $reference);

                $signed = $direction === 'pay' ? FinancialDecimal::sub('0', $amount) : $amount;
                $position->accumulated_funding = FinancialDecimal::add((string) ($position->accumulated_funding ?? '0'), $signed);
                $position->save();
            }

            return $payment;
        });
    }
}
