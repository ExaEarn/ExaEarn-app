<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MarginAccount;
use App\Models\MarginAssetConfig;
use App\Models\MarginInterestAccrual;
use App\Models\MarginLendingPool;
use App\Models\MarginLiquidation;
use App\Models\MarginLoan;
use App\Models\MarginOrder;
use App\Services\FinancialDecimal;
use App\Services\MarginAccountService;
use App\Services\MarginBorrowService;
use App\Services\MarginHealthService;
use App\Services\MarginInterestAccrualService;
use App\Services\MarginLiquidityService;
use App\Services\MarginLiquidationService;
use App\Services\MarginOperationalReadinessService;
use App\Services\MarginOrderService;
use App\Services\MarginRealtimeService;
use App\Services\MarginReconciliationService;
use App\Services\MarginRepayService;
use App\Services\MarginTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarginController extends Controller
{
    public function __construct(
        private readonly MarginAccountService $accounts,
        private readonly MarginHealthService $health,
        private readonly MarginBorrowService $borrows,
        private readonly MarginRepayService $repayments,
        private readonly MarginTransferService $transfers,
        private readonly MarginInterestAccrualService $accruals,
        private readonly MarginLiquidityService $liquidity,
        private readonly MarginLiquidationService $liquidations,
        private readonly MarginOrderService $orders,
        private readonly MarginReconciliationService $reconciliation,
        private readonly MarginRealtimeService $realtime,
        private readonly MarginOperationalReadinessService $readiness,
    ) {
    }

    public function overview(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $accounts = MarginAccount::query()->where('user_id', $userId)->latest()->get();
        $cross = $this->accounts->getOrCreateCrossAccount($userId);
        if (!$accounts->contains('id', $cross->id)) {
            $accounts->push($cross);
        }

        return response()->json([
            'mode' => config('margin.mode'),
            'accounts' => $accounts->map(fn (MarginAccount $account): array => $this->presentAccount($account)),
            'loans' => MarginLoan::query()->where('user_id', $userId)->latest()->limit(50)->get(),
            'orders' => MarginOrder::query()->with('spotOrder')->where('user_id', $userId)->latest()->limit(50)->get(),
            'pools' => MarginLendingPool::query()->where('status', 'ENABLED')->get(),
        ]);
    }

    public function accounts(Request $request): JsonResponse
    {
        return response()->json([
            'data' => MarginAccount::query()->where('user_id', $request->user()->id)->latest()->get()->map(fn (MarginAccount $account): array => $this->presentAccount($account)),
        ]);
    }

    public function createAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:CROSS,ISOLATED,cross,isolated'],
            'market_symbol' => ['nullable', 'string', 'max:32'],
        ]);

        $account = strtoupper((string) $data['mode']) === MarginAccount::MODE_CROSS
            ? $this->accounts->getOrCreateCrossAccount((int) $request->user()->id)
            : $this->accounts->getOrCreateIsolatedAccount((int) $request->user()->id, (string) ($data['market_symbol'] ?? 'BTC/USDT'));

        return response()->json($this->presentAccount($account), 201);
    }

    public function assets(): JsonResponse
    {
        return response()->json(['data' => MarginAssetConfig::query()->orderBy('asset')->get()]);
    }

    public function pools(): JsonResponse
    {
        return response()->json(['data' => MarginLendingPool::query()->orderBy('asset')->get()]);
    }

    public function fundPool(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset' => ['required', 'string', 'max:24'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $pool = $this->liquidity->fundPool(strtoupper((string) $data['asset']), (string) $data['amount'], (string) ($data['reference'] ?? 'margin-pool-fund:' . uniqid('', true)));

        return response()->json($pool);
    }

    public function health(Request $request): JsonResponse
    {
        $account = $this->resolveAccount($request);

        return response()->json($this->health->health($account));
    }

    public function transfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_uuid' => ['required', 'string'],
            'direction' => ['required', 'in:IN,OUT,in,out'],
            'asset' => ['required', 'string', 'max:24'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'source_account' => ['nullable', 'string', 'max:64'],
            'destination_account' => ['nullable', 'string', 'max:64'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);
        $account = $this->resolveAccount($request, (string) $data['account_uuid']);
        $reference = 'margin-transfer:' . ($data['idempotency_key'] ?? uniqid('', true));

        if (strtoupper((string) $data['direction']) === 'IN') {
            $this->transfers->transferInto($account, (string) ($data['source_account'] ?? 'funding'), (string) $data['asset'], (string) $data['amount'], $reference);
        } else {
            $this->transfers->transferOut($account, (string) ($data['destination_account'] ?? 'funding'), (string) $data['asset'], (string) $data['amount'], $reference);
        }

        return response()->json($this->health->health($account->fresh()));
    }

    public function borrow(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_uuid' => ['required', 'string'],
            'asset' => ['required', 'string', 'max:24'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);
        $loan = $this->borrows->borrow(
            $this->resolveAccount($request, (string) $data['account_uuid']),
            (string) $data['asset'],
            (string) $data['amount'],
            (string) ($data['idempotency_key'] ?? uniqid('', true)),
        );

        return response()->json($loan, 201);
    }

    public function repay(Request $request, string $loanUuid): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);
        $loan = MarginLoan::query()
            ->where('loan_uuid', $loanUuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json($this->repayments->repay($loan, (string) $data['amount'], (string) ($data['idempotency_key'] ?? uniqid('', true))));
    }

    public function loans(Request $request): JsonResponse
    {
        return response()->json([
            'data' => MarginLoan::query()->where('user_id', $request->user()->id)->latest()->paginate((int) $request->integer('per_page', 25)),
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        return response()->json([
            'data' => MarginOrder::query()
                ->with('spotOrder')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->paginate((int) $request->integer('per_page', 25)),
        ]);
    }

    public function realtimeSnapshot(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->realtime->snapshot(
                (int) $request->user()->id,
                (int) $request->integer('after_sequence', 0),
                (int) $request->integer('limit', 100),
            ),
        ]);
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_uuid' => ['required', 'string'],
            'client_order_id' => ['required', 'string', 'max:120'],
            'pair' => ['required', 'string', 'max:32'],
            'side' => ['required', 'in:buy,sell,BUY,SELL'],
            'type' => ['required', 'in:limit,market,LIMIT,MARKET'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'price' => ['nullable', 'numeric', 'gt:0'],
            'borrow_mode' => ['nullable', 'in:NORMAL,AUTO_BORROW,AUTO_REPAY,normal,auto_borrow,auto_repay'],
            'time_in_force' => ['nullable', 'in:GTC,IOC,FOK,gtc,ioc,fok'],
            'post_only' => ['nullable', 'boolean'],
        ]);

        $order = $this->orders->place($this->resolveAccount($request, (string) $data['account_uuid']), $data);

        return response()->json(['data' => $order], 201);
    }

    public function cancelOrder(Request $request, string $marginOrderUuid): JsonResponse
    {
        return response()->json([
            'data' => $this->orders->cancel((int) $request->user()->id, $marginOrderUuid),
        ]);
    }

    public function accrue(Request $request, string $loanUuid): JsonResponse
    {
        $loan = MarginLoan::query()->where('loan_uuid', $loanUuid)->where('user_id', $request->user()->id)->firstOrFail();
        $accrual = $this->accruals->accrueLoan($loan);

        return response()->json(['data' => $accrual]);
    }

    public function interest(Request $request): JsonResponse
    {
        $loanIds = MarginLoan::query()->where('user_id', $request->user()->id)->pluck('id');

        return response()->json([
            'data' => MarginInterestAccrual::query()->whereIn('margin_loan_id', $loanIds)->latest()->paginate((int) $request->integer('per_page', 25)),
        ]);
    }

    public function liquidationCheck(Request $request): JsonResponse
    {
        $account = $this->resolveAccount($request);

        return response()->json(['data' => $this->liquidations->openIfUnsafe($account)]);
    }

    public function liquidations(Request $request): JsonResponse
    {
        return response()->json([
            'data' => MarginLiquidation::query()->where('user_id', $request->user()->id)->latest()->paginate((int) $request->integer('per_page', 25)),
        ]);
    }

    public function executeLiquidation(Request $request, string $liquidationId): JsonResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        $liquidation = MarginLiquidation::query()
            ->where('liquidation_id', $liquidationId)
            ->firstOrFail();

        return response()->json([
            'data' => $this->liquidations->execute($liquidation, (string) $data['idempotency_key']),
        ]);
    }

    public function reconcile(): JsonResponse
    {
        return response()->json(['findings' => $this->reconciliation->run()]);
    }

    public function readiness(): JsonResponse
    {
        return response()->json(['data' => $this->readiness->readiness()]);
    }

    public function runLoadProbe(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'iterations' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        return response()->json([
            'data' => $this->readiness->runLoadProbe((int) ($payload['iterations'] ?? 100)),
        ], 202);
    }

    public function adminOverview(): JsonResponse
    {
        $pools = MarginLendingPool::query()->orderBy('asset')->get();
        $loans = MarginLoan::query()->whereIn('status', [MarginLoan::STATUS_ACTIVE, MarginLoan::STATUS_PARTIALLY_REPAID, MarginLoan::STATUS_LIQUIDATING])->get();
        $liquidations = MarginLiquidation::query()->latest()->limit(25)->get();
        $findings = $this->reconciliation->run();

        return response()->json([
            'data' => $pools->map(fn (MarginLendingPool $pool): array => [
                'asset' => $pool->asset,
                'total_liquidity' => (string) $pool->total_liquidity,
                'available_liquidity' => (string) $pool->available_liquidity,
                'borrowed_liquidity' => (string) $pool->borrowed_liquidity,
                'reserve_balance' => (string) $pool->reserve_balance,
                'status' => $pool->status,
                'utilization' => FinancialDecimal::compare((string) $pool->total_liquidity, '0') > 0
                    ? FinancialDecimal::div((string) $pool->borrowed_liquidity, (string) $pool->total_liquidity)
                    : '0.000000000000000000',
            ])->values(),
            'stats' => [
                ['label' => 'Total pools', 'value' => (string) $pools->count()],
                ['label' => 'Active loans', 'value' => (string) $loans->count()],
                ['label' => 'Liquidations', 'value' => (string) $liquidations->count()],
                ['label' => 'Reconciliation findings', 'value' => (string) count($findings)],
            ],
            'liquidations' => $liquidations,
        ]);
    }

    private function resolveAccount(Request $request, ?string $uuid = null): MarginAccount
    {
        $uuid = $uuid ?: (string) $request->query('account_uuid', '');
        if ($uuid !== '') {
            return MarginAccount::query()->where('account_uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();
        }

        return $this->accounts->getOrCreateCrossAccount((int) $request->user()->id);
    }

    private function presentAccount(MarginAccount $account): array
    {
        return [
            'account_uuid' => $account->account_uuid,
            'mode' => $account->mode,
            'market_symbol' => $account->market_symbol,
            'status' => $account->status,
            'ledger_account_type' => $this->accounts->ledgerAccountType($account),
            'health' => $this->health->health($account),
            'created_at' => $account->created_at,
            'updated_at' => $account->updated_at,
        ];
    }
}
