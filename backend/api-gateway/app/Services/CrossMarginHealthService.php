<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesPosition;
use App\Models\Reservation;

class CrossMarginHealthService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly BalanceProjectionService $balances,
        private readonly FuturesMarginService $marginService,
    ) {
    }

    public function health(int $userId, string $asset = 'USDT'): array
    {
        $asset = strtoupper($asset);
        $account = $this->ledger->getOrCreateAccount($userId, 'futures', $asset);
        $projection = $this->balances->accountProjection($account);
        $cash = (string) $projection['total'];
        $reserved = $this->activeReservations($userId, $asset);
        $unrealized = '0';
        $realized = '0';
        $funding = '0';
        $positionInitial = '0';
        $maintenance = '0';

        FuturesPosition::query()
            ->where('user_id', $userId)
            ->where('margin_type', 'cross')
            ->where('status', 'open')
            ->with('market')
            ->get()
            ->each(function (FuturesPosition $position) use (&$funding, &$maintenance, &$positionInitial, &$realized, &$unrealized): void {
                $market = $position->market;
                $mark = (string) ($position->mark_price ?: $market?->mark_price ?: $market?->last_price ?: $position->entry_price);
                $unrealized = FinancialDecimal::add($unrealized, $this->marginService->unrealizedPnl((string) $position->side, (string) $position->entry_price, $mark, (string) $position->quantity));
                $realized = FinancialDecimal::add($realized, (string) ($position->realized_pnl ?? '0'));
                $funding = FinancialDecimal::add($funding, (string) ($position->accumulated_funding ?? '0'));
                $positionInitial = FinancialDecimal::add($positionInitial, (string) ($position->margin ?? '0'));
                if ($market) {
                    $maintenance = FinancialDecimal::add(
                        $maintenance,
                        $this->marginService->maintenanceMargin($market, $this->marginService->notional($mark, (string) $position->quantity))
                    );
                } else {
                    $maintenance = FinancialDecimal::add($maintenance, (string) ($position->maintenance_margin ?? '0'));
                }
            });

        $feesDue = '0';
        $equity = FinancialDecimal::sub(FinancialDecimal::add(FinancialDecimal::add($cash, $realized), FinancialDecimal::add($unrealized, $funding)), $feesDue);
        $initialMargin = FinancialDecimal::add($positionInitial, $reserved);
        $available = FinancialDecimal::sub(FinancialDecimal::sub($equity, $positionInitial), $reserved);
        $marginRatio = FinancialDecimal::compare($maintenance, '0') > 0
            ? FinancialDecimal::div($equity, $maintenance)
            : '999999.000000000000000000';

        return [
            'asset' => $asset,
            'cash_balance' => $cash,
            'realized_pnl' => $realized,
            'unrealized_pnl' => $unrealized,
            'funding_accrual' => $funding,
            'fees_due' => $feesDue,
            'reserved_initial_margin' => $reserved,
            'position_initial_margin' => $positionInitial,
            'initial_margin' => $initialMargin,
            'maintenance_margin' => $maintenance,
            'equity' => $equity,
            'available_margin' => $available,
            'margin_ratio' => $marginRatio,
            'risk_status' => $this->riskStatus($equity, $maintenance),
        ];
    }

    public function assertCanReserve(int $userId, string $asset, string $additionalMargin): void
    {
        $health = $this->health($userId, $asset);
        if (FinancialDecimal::compare((string) $health['available_margin'], $additionalMargin) < 0) {
            throw new \RuntimeException('Projected cross-margin account health is insufficient.');
        }
    }

    public function assertCanTransferOut(int $userId, string $asset, string $amount): void
    {
        $health = $this->health($userId, $asset);
        $projectedEquity = FinancialDecimal::sub((string) $health['equity'], $amount);
        if (FinancialDecimal::compare($projectedEquity, (string) $health['maintenance_margin']) <= 0) {
            throw new \RuntimeException('Transfer would breach futures cross-margin maintenance requirements.');
        }
    }

    private function activeReservations(int $userId, string $asset): string
    {
        $sum = '0';
        Reservation::query()
            ->where('user_id', $userId)
            ->where('asset', strtoupper($asset))
            ->where('purpose', 'futures_initial_margin')
            ->whereIn('status', [Reservation::STATUS_ACTIVE, Reservation::STATUS_PARTIALLY_CONSUMED])
            ->get()
            ->each(function (Reservation $reservation) use (&$sum): void {
                $sum = FinancialDecimal::add($sum, (string) $reservation->remaining_amount);
            });

        return $sum;
    }

    private function riskStatus(string $equity, string $maintenance): string
    {
        if (FinancialDecimal::compare($equity, '0') <= 0) {
            return 'BANKRUPT';
        }
        if (FinancialDecimal::compare($equity, $maintenance) <= 0) {
            return 'LIQUIDATION_PENDING';
        }
        $warning = FinancialDecimal::mul($maintenance, (string) config('futures.cross.warning_ratio', '1.25'));
        if (FinancialDecimal::compare($equity, $warning) <= 0) {
            return 'WARNING';
        }

        return 'HEALTHY';
    }
}
