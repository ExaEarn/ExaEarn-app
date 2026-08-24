<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarginAccount;
use App\Models\MarginBadDebt;
use App\Models\MarginLiquidation;
use App\Models\MarginLoan;
use App\Models\Market;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarginLiquidationService
{
    public function __construct(
        private readonly MarginHealthService $health,
        private readonly MarginRealtimeService $realtime,
        private readonly MarginAccountService $accounts,
        private readonly LedgerService $ledger,
        private readonly MarginRepayService $repayments,
        private readonly TradeService $trades,
        private readonly MarginPricingService $pricing,
    )
    {
    }

    public function openIfUnsafe(MarginAccount $account): ?MarginLiquidation
    {
        return DB::transaction(function () use ($account): ?MarginLiquidation {
            $account = MarginAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $health = $this->health->health($account);
            if (FinancialDecimal::compare($health['health_factor'], (string) config('margin.health.liquidation')) >= 0) {
                return null;
            }

            $account->status = MarginAccount::STATUS_LIQUIDATION_PENDING;
            $account->save();

            $badDebt = FinancialDecimal::compare($health['equity'], '0') < 0 ? FinancialDecimal::abs($health['equity']) : '0';
            $liquidation = MarginLiquidation::query()->create([
                'liquidation_id' => (string) Str::uuid(),
                'margin_account_id' => $account->id,
                'user_id' => $account->user_id,
                'mode' => $account->mode,
                'market_symbol' => $account->market_symbol,
                'status' => 'PENDING',
                'trigger_health_factor' => $health['health_factor'],
                'assets_sold' => [],
                'debt_repaid' => [],
                'liquidation_fee' => '0',
                'reserve_impact' => '0',
                'bad_debt_amount' => $badDebt,
                'metadata' => ['health' => $health],
            ]);

            if (FinancialDecimal::compare($badDebt, '0') > 0) {
                foreach (MarginLoan::query()->where('margin_account_id', $account->id)->whereIn('status', [MarginLoan::STATUS_ACTIVE, MarginLoan::STATUS_PARTIALLY_REPAID])->get() as $loan) {
                    MarginBadDebt::query()->create([
                        'bad_debt_id' => (string) Str::uuid(),
                        'margin_account_id' => $account->id,
                        'user_id' => $account->user_id,
                        'asset' => $loan->asset,
                        'amount' => FinancialDecimal::add((string) $loan->principal, (string) $loan->accrued_interest),
                        'covered_amount' => '0',
                        'status' => 'OPEN',
                        'metadata' => ['liquidation_id' => $liquidation->liquidation_id],
                    ]);
                }
            }

            $this->realtime->publishAccount($account, 'margin.liquidation.pending', [
                'liquidation_id' => $liquidation->liquidation_id,
                'health_factor' => $health['health_factor'],
                'bad_debt_amount' => $badDebt,
            ]);

            return $liquidation;
        });
    }

    public function execute(MarginLiquidation $liquidation, string $idempotencyKey): MarginLiquidation
    {
        return DB::transaction(function () use ($idempotencyKey, $liquidation): MarginLiquidation {
            $liquidation = MarginLiquidation::query()->whereKey($liquidation->id)->lockForUpdate()->firstOrFail();
            if ($liquidation->status === 'COMPLETED') {
                return $liquidation;
            }

            $account = MarginAccount::query()->whereKey($liquidation->margin_account_id)->lockForUpdate()->firstOrFail();
            $debtRepaid = $this->repayAvailableDebt($account, $idempotencyKey);
            $assetsSold = [];
            $errors = [];

            if (! $this->allDebtCleared($account)) {
                $health = $this->health->health($account);
                foreach ($health['assets'] as $assetRow) {
                    $asset = strtoupper((string) $assetRow['asset']);
                    $amount = (string) $assetRow['amount'];
                    if (FinancialDecimal::compare($amount, '0') <= 0 || $this->hasDebtInAsset($account, $asset)) {
                        continue;
                    }

                    foreach ($this->activeDebtAssets($account) as $debtAsset) {
                        $market = $this->marketFor($asset, $debtAsset);
                        if (! $market) {
                            continue;
                        }

                        try {
                            $limitPrice = $this->pricing->price($asset);
                            $order = $this->trades->placeOrder((int) $account->user_id, $market->symbol, 'sell', 'limit', $amount, $limitPrice, [
                                'client_order_id' => "margin-liquidation:{$liquidation->liquidation_id}:{$asset}:{$debtAsset}:{$idempotencyKey}",
                                'time_in_force' => 'IOC',
                                'account_type' => $this->accounts->ledgerAccountType($account),
                                'source' => 'MARGIN_LIQUIDATION',
                                'margin_account_id' => $account->id,
                                'liquidation_id' => $liquidation->liquidation_id,
                            ]);
                            $assetsSold[] = [
                                'asset' => $asset,
                                'amount' => $amount,
                                'market' => $market->symbol,
                                'limit_price' => $limitPrice,
                                'spot_order_uuid' => $order['order']->order_uuid ?? null,
                            ];
                            $debtRepaid = array_merge($debtRepaid, $this->repayAvailableDebt($account->fresh() ?? $account, $idempotencyKey));
                            break 2;
                        } catch (\Throwable $exception) {
                            $errors[] = [
                                'asset' => $asset,
                                'debt_asset' => $debtAsset,
                                'market' => $market->symbol,
                                'error' => $exception->getMessage(),
                            ];

                            if (str_contains($exception->getMessage(), 'Double-entry check failed')) {
                                throw $exception;
                            }
                        }
                    }
                }
            }

            $complete = $this->allDebtCleared($account);
            $liquidation->status = $complete ? 'COMPLETED' : ((count($assetsSold) || count($debtRepaid)) ? 'PARTIALLY_EXECUTED' : 'PENDING');
            $liquidation->assets_sold = array_values(array_merge($liquidation->assets_sold ?? [], $assetsSold));
            $liquidation->debt_repaid = array_values(array_merge($liquidation->debt_repaid ?? [], $debtRepaid));
            $liquidation->metadata = array_merge($liquidation->metadata ?? [], [
                'last_execution_idempotency_key' => $idempotencyKey,
                'last_execution_errors' => $errors,
                'executed_at' => now()->toIso8601String(),
            ]);
            $liquidation->save();

            if ($complete) {
                $account->status = MarginAccount::STATUS_ACTIVE;
                $account->save();
            }

            $this->realtime->publishAccount($account, $complete ? 'margin.liquidation.completed' : 'margin.liquidation.updated', [
                'liquidation_id' => $liquidation->liquidation_id,
                'status' => $liquidation->status,
                'assets_sold' => $assetsSold,
                'debt_repaid' => $debtRepaid,
            ]);

            return $liquidation->fresh() ?? $liquidation;
        });
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function repayAvailableDebt(MarginAccount $account, string $idempotencyKey): array
    {
        $records = [];
        $accountType = $this->accounts->ledgerAccountType($account);
        $loans = MarginLoan::query()
            ->where('margin_account_id', $account->id)
            ->whereIn('status', [MarginLoan::STATUS_ACTIVE, MarginLoan::STATUS_PARTIALLY_REPAID, MarginLoan::STATUS_LIQUIDATING])
            ->orderBy('opened_at')
            ->lockForUpdate()
            ->get();

        foreach ($loans as $loan) {
            $available = $this->ledger->getBalance((int) $account->user_id, (string) $loan->asset, $accountType);
            if (FinancialDecimal::compare($available, '0') <= 0) {
                continue;
            }

            $debt = FinancialDecimal::add((string) $loan->principal, (string) $loan->accrued_interest);
            $payment = FinancialDecimal::min($available, $debt);
            if (FinancialDecimal::compare($payment, '0') <= 0) {
                continue;
            }

            $repaid = $this->repayments->repay($loan, $payment, "margin-liquidation-repay:{$idempotencyKey}:{$loan->id}");
            $records[] = [
                'loan_uuid' => $loan->loan_uuid,
                'asset' => (string) $loan->asset,
                'amount' => $payment,
                'status' => (string) $repaid->status,
            ];
        }

        return $records;
    }

    private function allDebtCleared(MarginAccount $account): bool
    {
        return ! MarginLoan::query()
            ->where('margin_account_id', $account->id)
            ->whereIn('status', [MarginLoan::STATUS_ACTIVE, MarginLoan::STATUS_PARTIALLY_REPAID, MarginLoan::STATUS_LIQUIDATING])
            ->exists();
    }

    private function hasDebtInAsset(MarginAccount $account, string $asset): bool
    {
        return MarginLoan::query()
            ->where('margin_account_id', $account->id)
            ->where('asset', strtoupper($asset))
            ->whereIn('status', [MarginLoan::STATUS_ACTIVE, MarginLoan::STATUS_PARTIALLY_REPAID, MarginLoan::STATUS_LIQUIDATING])
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    private function activeDebtAssets(MarginAccount $account): array
    {
        return MarginLoan::query()
            ->where('margin_account_id', $account->id)
            ->whereIn('status', [MarginLoan::STATUS_ACTIVE, MarginLoan::STATUS_PARTIALLY_REPAID, MarginLoan::STATUS_LIQUIDATING])
            ->pluck('asset')
            ->map(fn (string $asset): string => strtoupper($asset))
            ->unique()
            ->values()
            ->all();
    }

    private function marketFor(string $baseAsset, string $quoteAsset): ?Market
    {
        return Market::query()
            ->where('symbol', strtoupper($baseAsset) . '/' . strtoupper($quoteAsset))
            ->where('status', 'active')
            ->where('trading_status', 'trading')
            ->first();
    }
}
