<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesAdlEvent;
use App\Models\FuturesPosition;
use Illuminate\Support\Str;

class FuturesAdlService
{
    public function __construct(private readonly FuturesPositionService $positions)
    {
    }

    public function rankQueue(string $symbol, string $opposingSide): array
    {
        return FuturesPosition::query()
            ->where('symbol', strtoupper($symbol))
            ->where('side', $opposingSide)
            ->where('status', 'open')
            ->get()
            ->map(function (FuturesPosition $position): array {
                $notional = FinancialDecimal::mul((string) $position->mark_price, (string) $position->quantity);
                $margin = FinancialDecimal::compare((string) $position->margin, '0') > 0 ? (string) $position->margin : '1';
                $roe = FinancialDecimal::div(FinancialDecimal::abs((string) $position->unrealized_pnl), $margin);
                $score = FinancialDecimal::mul($roe, (string) $position->leverage);

                return ['position' => $position, 'rank_score' => $score, 'notional' => $notional];
            })
            ->sortByDesc('rank_score')
            ->values()
            ->all();
    }

    public function queueEvent(FuturesPosition $position, string $quantity, string $rankScore): FuturesAdlEvent
    {
        return FuturesAdlEvent::query()->create([
            'adl_id' => (string) Str::uuid(),
            'symbol' => $position->symbol,
            'futures_position_id' => $position->id,
            'user_id' => $position->user_id,
            'rank_score' => $rankScore,
            'quantity' => $quantity,
            'status' => 'queued',
            'metadata' => ['policy' => 'profitability_x_leverage'],
        ]);
    }

    public function executeReduction(FuturesAdlEvent $event, string $price): FuturesAdlEvent
    {
        return \DB::transaction(function () use ($event, $price): FuturesAdlEvent {
            $event = FuturesAdlEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($event->status === 'executed') {
                return $event;
            }

            $position = FuturesPosition::query()->lockForUpdate()->findOrFail($event->futures_position_id);
            $quantity = FinancialDecimal::min((string) $event->quantity, (string) $position->quantity);
            $proportion = FinancialDecimal::div($quantity, (string) $position->quantity);
            $realized = FinancialDecimal::mul((string) $position->unrealized_pnl, $proportion);
            $marginRelease = FinancialDecimal::mul((string) $position->margin, $proportion);

            $position->realized_pnl = FinancialDecimal::add((string) $position->realized_pnl, $realized);
            $position->quantity = FinancialDecimal::sub((string) $position->quantity, $quantity);
            $position->margin = FinancialDecimal::sub((string) $position->margin, $marginRelease);
            if (FinancialDecimal::compare((string) $position->quantity, '0') <= 0) {
                $position->quantity = '0.00000000';
                $position->margin = '0.00000000';
                $position->status = 'closed_by_adl';
            }
            $position->metadata = array_merge($position->metadata ?? [], ['last_adl_event_id' => $event->adl_id, 'last_adl_price' => $price]);
            $position->save();

            $event->status = 'executed';
            $event->metadata = array_merge($event->metadata ?? [], [
                'executed_price' => $price,
                'executed_quantity' => $quantity,
                'realized_pnl' => $realized,
            ]);
            $event->save();

            $this->positions->refreshUnrealizedPnl($position->fresh(), $price);

            return $event->fresh();
        });
    }
}
