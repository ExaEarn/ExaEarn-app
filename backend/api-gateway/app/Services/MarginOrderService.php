<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarginAccount;
use App\Models\MarginLoan;
use App\Models\MarginOrder;
use App\Models\Market;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MarginOrderService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly MarginAccountService $accounts,
        private readonly MarginBorrowService $borrowService,
        private readonly MarginRepayService $repayService,
        private readonly MarginHealthService $health,
        private readonly TradeService $trades,
        private readonly MarginRealtimeService $realtime,
    ) {
    }

    public function place(MarginAccount $account, array $command): MarginOrder
    {
        $this->accounts->assertActive($account);

        $clientOrderId = trim((string) ($command['client_order_id'] ?? ''));
        if ($clientOrderId === '') {
            throw new RuntimeException('client_order_id is required for Margin orders.');
        }

        $existing = MarginOrder::query()
            ->where('user_id', $account->user_id)
            ->where('client_order_id', $clientOrderId)
            ->first();
        if ($existing) {
            return $existing->fresh(['spotOrder']) ?? $existing;
        }

        return DB::transaction(function () use ($account, $clientOrderId, $command): MarginOrder {
            /** @var Market $market */
            $market = Market::query()
                ->where('symbol', $this->normalizePair((string) $command['pair']))
                ->lockForUpdate()
                ->firstOrFail();

            $side = strtolower((string) $command['side']);
            $type = strtolower((string) $command['type']);
            $amount = FinancialDecimal::normalize((string) $command['amount']);
            $price = isset($command['price']) && $command['price'] !== null ? FinancialDecimal::normalize((string) $command['price']) : null;
            $borrowMode = strtoupper((string) ($command['borrow_mode'] ?? 'NORMAL'));
            if (! in_array($borrowMode, ['NORMAL', 'AUTO_BORROW', 'AUTO_REPAY'], true)) {
                throw new RuntimeException('Unsupported Margin borrow mode.');
            }

            $lock = $this->requiredFunding($market, $side, $type, $amount, $price);
            $accountType = $this->accounts->ledgerAccountType($account);
            $available = $this->ledger->getBalance((int) $account->user_id, $lock['asset'], $accountType);
            $borrowAmount = '0';
            $borrowReference = null;
            $autoBorrowLoan = null;

            app(TradingRiskEngine::class)->assertOrderAllowed((int) $account->user_id, 'margin', $market, [
                'pair' => $market->symbol,
                'side' => $side,
                'type' => $type,
                'amount' => $amount,
                'price' => $price,
                'auto_borrow_asset' => $lock['asset'],
                'auto_borrow_amount' => FinancialDecimal::compare($available, $lock['amount']) < 0
                    ? FinancialDecimal::sub($lock['amount'], $available)
                    : '0',
            ]);

            if (FinancialDecimal::compare($available, $lock['amount']) < 0) {
                if ($borrowMode !== 'AUTO_BORROW') {
                    throw new RuntimeException('Insufficient Margin balance. Enable Auto Borrow or transfer more collateral.');
                }

                $borrowAmount = FinancialDecimal::sub($lock['amount'], $available);
                $borrowReference = 'margin-order-auto-borrow:' . $clientOrderId;
                $autoBorrowLoan = $this->borrowService->borrow($account, $lock['asset'], $borrowAmount, $borrowReference);
            }

            $riskSnapshot = $this->health->health($account->fresh() ?? $account);
            if (in_array($riskSnapshot['status'], ['BORROW_DISABLED', 'LIQUIDATION_PENDING'], true)) {
                throw new RuntimeException('Margin account risk state does not allow new orders.');
            }

            $marginOrder = MarginOrder::query()->create([
                'margin_order_uuid' => (string) Str::uuid(),
                'user_id' => $account->user_id,
                'margin_account_id' => $account->id,
                'client_order_id' => $clientOrderId,
                'pair' => $market->symbol,
                'side' => $side,
                'type' => $type,
                'borrow_mode' => $borrowMode,
                'auto_borrow_asset' => FinancialDecimal::compare($borrowAmount, '0') > 0 ? $lock['asset'] : null,
                'auto_borrow_amount' => $borrowAmount,
                'auto_borrow_reference' => $borrowReference,
                'amount' => $amount,
                'price' => $price,
                'status' => MarginOrder::STATUS_PENDING,
                'risk_snapshot' => $riskSnapshot,
                'metadata' => [
                    'source' => 'MARGIN',
                    'margin_account_type' => $accountType,
                    'lock_asset' => $lock['asset'],
                    'lock_amount' => $lock['amount'],
                ],
            ]);

            try {
                $spot = $this->trades->placeOrder((int) $account->user_id, $market->symbol, $side, $type, $amount, $price, [
                    'client_order_id' => 'margin:' . $clientOrderId,
                    'time_in_force' => $command['time_in_force'] ?? 'GTC',
                    'post_only' => (bool) ($command['post_only'] ?? false),
                    'account_type' => $accountType,
                    'source' => 'MARGIN',
                    'margin_order_uuid' => $marginOrder->margin_order_uuid,
                    'margin_account_id' => $account->id,
                    'margin_mode' => $account->mode,
                    'borrow_mode' => $borrowMode,
                    'auto_borrow_reference' => $borrowReference,
                ]);
            } catch (\Throwable $exception) {
                $this->unwindAutoBorrow($autoBorrowLoan, $borrowAmount, $clientOrderId);

                $marginOrder->update([
                    'status' => MarginOrder::STATUS_REJECTED,
                    'metadata' => array_merge($marginOrder->metadata ?? [], ['reject_reason' => $exception->getMessage()]),
                ]);

                throw $exception;
            }

            /** @var Order $spotOrder */
            $spotOrder = $spot['order'];
            $marginOrder->update([
                'spot_order_id' => $spotOrder->id,
                'status' => MarginOrder::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'metadata' => array_merge($marginOrder->metadata ?? [], [
                    'spot_order_uuid' => $spotOrder->order_uuid,
                    'spot_status' => $spotOrder->status,
                ]),
            ]);

            $marginOrder = $marginOrder->fresh(['spotOrder']) ?? $marginOrder;
            if ($borrowMode === 'AUTO_REPAY') {
                $marginOrder = $this->syncAutoRepayForSpotOrder($spotOrder->fresh() ?? $spotOrder) ?? $marginOrder;
            }

            $this->realtime->publishAccount($account, 'margin.order.submitted', [
                'margin_order_uuid' => $marginOrder->margin_order_uuid,
                'spot_order_uuid' => $spotOrder->order_uuid,
                'pair' => $market->symbol,
                'side' => $side,
                'type' => $type,
                'amount' => $amount,
                'price' => $price,
                'borrow_mode' => $borrowMode,
                'status' => $marginOrder->status,
                'auto_borrow_asset' => $marginOrder->auto_borrow_asset,
                'auto_borrow_amount' => (string) $marginOrder->auto_borrow_amount,
                'auto_repay_asset' => $marginOrder->auto_repay_asset,
                'auto_repay_amount' => (string) $marginOrder->auto_repay_amount,
            ]);

            return $marginOrder;
        });
    }

    public function cancel(int $userId, string $marginOrderUuid): MarginOrder
    {
        return DB::transaction(function () use ($marginOrderUuid, $userId): MarginOrder {
            $marginOrder = MarginOrder::query()
                ->where('margin_order_uuid', $marginOrderUuid)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($marginOrder->status === MarginOrder::STATUS_CANCELLED) {
                return $marginOrder;
            }

            if (! $marginOrder->spotOrder) {
                $marginOrder->update(['status' => MarginOrder::STATUS_CANCELLED, 'cancelled_at' => now()]);
                return $marginOrder->fresh() ?? $marginOrder;
            }

            $this->trades->cancelOrder($userId, (string) $marginOrder->spotOrder->order_uuid);
            $autoBorrowRelease = $this->releaseUnusedAutoBorrow($marginOrder->fresh(['marginAccount', 'spotOrder']) ?? $marginOrder, 'cancel');
            $marginOrder->update([
                'status' => MarginOrder::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'metadata' => array_merge($marginOrder->metadata ?? [], [
                    'cancelled_spot_order_uuid' => $marginOrder->spotOrder->order_uuid,
                    'auto_borrow_release' => $autoBorrowRelease,
                ]),
            ]);

            if ($marginOrder->marginAccount) {
                $this->realtime->publishAccount($marginOrder->marginAccount, 'margin.order.cancelled', [
                    'margin_order_uuid' => $marginOrder->margin_order_uuid,
                    'spot_order_uuid' => $marginOrder->spotOrder->order_uuid,
                ]);
            }

            return $marginOrder->fresh(['spotOrder']) ?? $marginOrder;
        });
    }

    public function syncAutoBorrowForSpotOrder(Order $spotOrder): ?MarginOrder
    {
        /** @var MarginOrder|null $marginOrder */
        $marginOrder = MarginOrder::query()
            ->where('spot_order_id', $spotOrder->id)
            ->where('borrow_mode', 'AUTO_BORROW')
            ->lockForUpdate()
            ->first();

        if (! $marginOrder || ! $marginOrder->marginAccount) {
            return $marginOrder;
        }

        if (! in_array((string) $spotOrder->status, ['filled', 'cancelled', 'rejected', 'expired'], true)) {
            return $marginOrder;
        }

        $release = $this->releaseUnusedAutoBorrow($marginOrder, 'spot_sync');
        if ($release['asset']) {
            $marginOrder->update([
                'metadata' => array_merge($marginOrder->metadata ?? [], [
                    'auto_borrow_release_sync' => array_merge($release, [
                        'spot_order_uuid' => $spotOrder->order_uuid,
                        'spot_status' => $spotOrder->status,
                    ]),
                ]),
            ]);
        }

        return $marginOrder->fresh(['spotOrder']);
    }

    public function syncAutoRepayForSpotOrder(Order $spotOrder): ?MarginOrder
    {
        /** @var MarginOrder|null $marginOrder */
        $marginOrder = MarginOrder::query()
            ->where('spot_order_id', $spotOrder->id)
            ->where('borrow_mode', 'AUTO_REPAY')
            ->lockForUpdate()
            ->first();

        if (! $marginOrder || ! $marginOrder->marginAccount) {
            return $marginOrder;
        }

        $market = $spotOrder->market ?: Market::query()->where('symbol', $spotOrder->pair)->first();
        if (! $market) {
            return $marginOrder;
        }

        $autoRepay = $this->applyAutoRepay(
            $marginOrder->marginAccount,
            $this->receivedAsset($market, strtolower((string) $spotOrder->side)),
            $marginOrder->client_order_id . ':sync:' . $spotOrder->id,
        );

        if ($autoRepay['asset']) {
            $marginOrder->update([
                'auto_repay_asset' => $autoRepay['asset'],
                'auto_repay_amount' => FinancialDecimal::add((string) $marginOrder->auto_repay_amount, $autoRepay['amount']),
                'metadata' => array_merge($marginOrder->metadata ?? [], [
                    'auto_repay_sync' => array_merge($autoRepay, [
                        'spot_order_uuid' => $spotOrder->order_uuid,
                        'spot_status' => $spotOrder->status,
                    ]),
                ]),
            ]);

            $this->realtime->publishAccount($marginOrder->marginAccount, 'margin.order.auto_repaid', [
                'margin_order_uuid' => $marginOrder->margin_order_uuid,
                'spot_order_uuid' => $spotOrder->order_uuid,
                'asset' => $autoRepay['asset'],
                'amount' => $autoRepay['amount'],
            ]);
        }

        return $marginOrder->fresh(['spotOrder']);
    }

    /**
     * @return array{asset:?string,amount:string,loan_id:?int}
     */
    private function releaseUnusedAutoBorrow(MarginOrder $marginOrder, string $reason): array
    {
        if (
            $marginOrder->borrow_mode !== 'AUTO_BORROW'
            || ! $marginOrder->marginAccount
            || ! $marginOrder->auto_borrow_asset
            || ! $marginOrder->auto_borrow_reference
            || FinancialDecimal::compare((string) $marginOrder->auto_borrow_amount, '0') <= 0
        ) {
            return ['asset' => null, 'amount' => '0', 'loan_id' => null];
        }

        $asset = strtoupper((string) $marginOrder->auto_borrow_asset);
        $loan = MarginLoan::query()
            ->where('margin_account_id', $marginOrder->margin_account_id)
            ->where('asset', $asset)
            ->where('idempotency_key', $marginOrder->auto_borrow_reference)
            ->whereIn('status', [MarginLoan::STATUS_ACTIVE, MarginLoan::STATUS_PARTIALLY_REPAID])
            ->lockForUpdate()
            ->first();

        if (! $loan) {
            return ['asset' => null, 'amount' => '0', 'loan_id' => null];
        }

        $accountType = $this->accounts->ledgerAccountType($marginOrder->marginAccount);
        $available = $this->ledger->getBalance((int) $marginOrder->user_id, $asset, $accountType);
        $debt = FinancialDecimal::add((string) $loan->principal, (string) $loan->accrued_interest);
        $payment = FinancialDecimal::min($available, $debt);
        if (FinancialDecimal::compare($payment, '0') <= 0) {
            return ['asset' => null, 'amount' => '0', 'loan_id' => $loan->id];
        }

        $this->repayService->repay(
            $loan,
            $payment,
            'margin-order-auto-borrow-release:' . $marginOrder->client_order_id . ':' . $reason . ':' . $loan->id,
        );

        $this->realtime->publishAccount($marginOrder->marginAccount, 'margin.order.auto_borrow_released', [
            'margin_order_uuid' => $marginOrder->margin_order_uuid,
            'spot_order_uuid' => $marginOrder->spotOrder?->order_uuid,
            'asset' => $asset,
            'amount' => $payment,
            'reason' => $reason,
        ]);

        return ['asset' => $asset, 'amount' => $payment, 'loan_id' => $loan->id];
    }

    /**
     * @return array{asset:string,amount:string}
     */
    private function requiredFunding(Market $market, string $side, string $type, string $amount, ?string $price): array
    {
        if ($side === 'sell') {
            return ['asset' => strtoupper((string) $market->base_currency), 'amount' => $amount];
        }

        if ($type !== 'limit' || $price === null) {
            throw new RuntimeException('Margin market buy requires explicit maximum price protection in this checkpoint.');
        }

        return [
            'asset' => strtoupper((string) $market->quote_currency),
            'amount' => FinancialDecimal::mul($amount, $price),
        ];
    }

    private function normalizePair(string $pair): string
    {
        $pair = strtoupper(trim($pair));
        if (str_contains($pair, '/')) {
            return $pair;
        }

        foreach (['USDT', 'USDC', 'BTC', 'ETH'] as $quote) {
            if (str_ends_with($pair, $quote) && strlen($pair) > strlen($quote)) {
                return substr($pair, 0, -strlen($quote)) . '/' . $quote;
            }
        }

        return $pair;
    }

    private function unwindAutoBorrow(?MarginLoan $loan, string $amount, string $clientOrderId): void
    {
        if (! $loan || FinancialDecimal::compare($amount, '0') <= 0) {
            return;
        }

        try {
            $this->repayService->repay($loan, $amount, 'margin-order-auto-borrow-unwind:' . $clientOrderId);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return array{asset:?string,amount:string}
     */
    private function applyAutoRepay(MarginAccount $account, string $asset, string $clientOrderId): array
    {
        $asset = strtoupper($asset);
        $accountType = $this->accounts->ledgerAccountType($account);
        $available = $this->ledger->getBalance((int) $account->user_id, $asset, $accountType);
        if (FinancialDecimal::compare($available, '0') <= 0) {
            return ['asset' => null, 'amount' => '0'];
        }

        $totalRepaid = '0';
        $loans = MarginLoan::query()
            ->where('margin_account_id', $account->id)
            ->where('asset', $asset)
            ->whereIn('status', [MarginLoan::STATUS_ACTIVE, MarginLoan::STATUS_PARTIALLY_REPAID])
            ->orderBy('opened_at')
            ->lockForUpdate()
            ->get();

        foreach ($loans as $loan) {
            if (FinancialDecimal::compare($available, '0') <= 0) {
                break;
            }

            $debt = FinancialDecimal::add((string) $loan->principal, (string) $loan->accrued_interest);
            $payment = FinancialDecimal::min($available, $debt);
            if (FinancialDecimal::compare($payment, '0') <= 0) {
                continue;
            }

            $this->repayService->repay($loan, $payment, 'margin-order-auto-repay:' . $clientOrderId . ':' . $loan->id);
            $available = FinancialDecimal::sub($available, $payment);
            $totalRepaid = FinancialDecimal::add($totalRepaid, $payment);
        }

        return FinancialDecimal::compare($totalRepaid, '0') > 0
            ? ['asset' => $asset, 'amount' => $totalRepaid]
            : ['asset' => null, 'amount' => '0'];
    }

    private function receivedAsset(Market $market, string $side): string
    {
        return $side === 'sell'
            ? strtoupper((string) $market->quote_currency)
            : strtoupper((string) $market->base_currency);
    }
}
