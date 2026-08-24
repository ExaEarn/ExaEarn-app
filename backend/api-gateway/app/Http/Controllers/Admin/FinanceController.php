<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\FinanceAdjustmentRequest;
use App\Models\FinanceClosePeriod;
use App\Models\FinanceDeadLetterEvent;
use App\Models\FinanceFinancialEvent;
use App\Models\FinanceObligation;
use App\Models\FinanceOpeningBalanceImport;
use App\Models\FinanceReconciliationBreak;
use App\Models\LedgerTransaction;
use App\Services\FinanceAccountingService;
use App\Services\FinanceAdjustmentService;
use App\Services\FinanceBackingService;
use App\Services\FinanceCloseService;
use App\Services\FinanceDataQualityService;
use App\Services\FinanceDlqService;
use App\Services\FinanceObligationService;
use App\Services\FinanceOpeningBalanceService;
use App\Services\FinanceProductReconciliationService;
use App\Services\FinanceReadinessService;
use App\Services\FinanceReportService;
use App\Services\FinanceTreasuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    public function __construct(
        private readonly FinanceAccountingService $accounting,
        private readonly FinanceBackingService $backing,
        private readonly FinanceReportService $reports,
        private readonly FinanceAdjustmentService $adjustments,
        private readonly FinanceCloseService $close,
        private readonly FinanceReadinessService $readiness,
        private readonly FinanceProductReconciliationService $productReconciliation,
        private readonly FinanceDataQualityService $dataQuality,
        private readonly FinanceObligationService $obligations,
        private readonly FinanceOpeningBalanceService $openingBalances,
        private readonly FinanceDlqService $dlqService,
        private readonly FinanceTreasuryService $treasury,
    ) {
    }

    public function overview(): JsonResponse
    {
        $trial = $this->reports->trialBalance();
        $balanceSheet = $this->reports->balanceSheet();
        $backing = $this->backing->calculate();

        return response()->json(['data' => [
            'trial_balance' => ['balanced' => $trial['balanced'], 'total_debit' => $trial['total_debit'], 'total_credit' => $trial['total_credit']],
            'balance_sheet' => $balanceSheet,
            'backing' => $backing,
            'open_breaks' => FinanceReconciliationBreak::query()->where('status', 'OPEN')->count(),
            'critical_breaks' => FinanceReconciliationBreak::query()->where('status', 'OPEN')->where('severity', 'CRITICAL')->count(),
            'financial_events' => FinanceFinancialEvent::query()->count(),
            'dlq_open' => FinanceDeadLetterEvent::query()->where('status', 'OPEN')->count(),
        ]]);
    }

    public function postLedgerEvent(Request $request, string $reference): JsonResponse
    {
        $data = $request->validate([
            'event_type' => ['required', 'string', 'max:96'],
            'metadata' => ['nullable', 'array'],
        ]);
        $transaction = LedgerTransaction::query()->where('reference', $reference)->firstOrFail();
        $event = $this->accounting->recordLedgerEvent($transaction, $data['event_type'], $data['metadata'] ?? []);

        return response()->json(['data' => $event->load('journal.lines')], 201);
    }

    public function backing(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->backing->calculate($request->query('asset'))]);
    }

    public function trialBalance(): JsonResponse
    {
        return response()->json(['data' => $this->reports->trialBalance()]);
    }

    public function balanceSheet(): JsonResponse
    {
        return response()->json(['data' => $this->reports->balanceSheet()]);
    }

    public function profitAndLoss(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->profitAndLoss($request->query('start'), $request->query('end'))]);
    }

    public function cashFlow(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->cashFlow($request->query('start'), $request->query('end'))]);
    }

    public function generalLedger(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->generalLedger($request->query('account_code'))]);
    }

    public function productReconciliation(): JsonResponse
    {
        return response()->json(['data' => $this->productReconciliation->run()]);
    }

    public function dataQuality(): JsonResponse
    {
        return response()->json(['data' => $this->dataQuality->run()]);
    }

    public function treasury(): JsonResponse
    {
        return response()->json(['data' => ['position' => $this->treasury->treasuryPosition(), 'pnl' => $this->treasury->pnl()]]);
    }

    public function snapshotReport(Request $request): JsonResponse
    {
        $admin = $this->admin($request, 'finance.export');
        $data = $request->validate([
            'report_type' => ['required', 'string', 'max:80'],
            'payload' => ['required', 'array'],
        ]);

        return response()->json(['data' => $this->reports->snapshot($data['report_type'], $data['payload'], $admin->id)], 201);
    }

    public function requestAdjustment(Request $request): JsonResponse
    {
        $admin = $this->admin($request, 'finance.adjust.request');
        $data = $request->validate([
            'asset' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'debit_account_type' => ['required', 'string', 'max:120'],
            'credit_account_type' => ['required', 'string', 'max:120'],
            'reason_code' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
            'metadata' => ['nullable', 'array'],
        ]);

        return response()->json(['data' => $this->adjustments->request($admin, $data)], 202);
    }

    public function approveAdjustment(Request $request, string $uuid): JsonResponse
    {
        $admin = $this->admin($request, 'finance.adjust.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:1000']]);
        $adjustment = FinanceAdjustmentRequest::query()->where('adjustment_uuid', $uuid)->firstOrFail();

        return response()->json(['data' => $this->adjustments->approve($admin, $adjustment, $data['reason'])], 201);
    }

    public function prepareClose(Request $request): JsonResponse
    {
        $admin = $this->admin($request, 'finance.close.prepare');
        $data = $request->validate([
            'period_type' => ['required', Rule::in(['DAILY', 'MONTHLY'])],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
        ]);

        return response()->json(['data' => $this->close->prepare($admin, $data['period_type'], $data['period_start'], $data['period_end'])], 202);
    }

    public function approveClose(Request $request, int $periodId): JsonResponse
    {
        $admin = $this->admin($request, 'finance.close.approve');
        return response()->json(['data' => $this->close->approve($admin, FinanceClosePeriod::query()->findOrFail($periodId))], 202);
    }

    public function requestReopenClose(Request $request, int $periodId): JsonResponse
    {
        $admin = $this->admin($request, 'finance.close.prepare');
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:1000']]);
        return response()->json(['data' => $this->close->requestReopen($admin, FinanceClosePeriod::query()->findOrFail($periodId), $data['reason'])], 202);
    }

    public function approveReopenClose(Request $request, int $periodId): JsonResponse
    {
        $admin = $this->admin($request, 'finance.close.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:1000']]);
        return response()->json(['data' => $this->close->approveReopen($admin, FinanceClosePeriod::query()->findOrFail($periodId), $data['reason'])], 202);
    }

    public function obligations(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $this->admin($request, 'finance.reconcile');
            $data = $request->validate([
                'obligation_type' => ['required', Rule::in(['RECEIVABLE', 'PAYABLE'])],
                'counterparty_type' => ['required', 'string', 'max:80'],
                'source_service' => ['required', 'string', 'max:96'],
                'source_reference' => ['required', 'string', 'max:180'],
                'asset' => ['required', 'string', 'max:32'],
                'amount' => ['required', 'numeric', 'gt:0'],
                'metadata' => ['nullable', 'array'],
            ]);
            return response()->json(['data' => $this->obligations->create($data['obligation_type'], $data['counterparty_type'], $data['source_service'], $data['source_reference'], $data['asset'], (string) $data['amount'], $data['metadata'] ?? [])], 201);
        }
        return response()->json(['data' => FinanceObligation::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function settleObligation(Request $request, string $uuid): JsonResponse
    {
        $this->admin($request, 'finance.reconcile');
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0']]);
        return response()->json(['data' => $this->obligations->settle(FinanceObligation::query()->where('obligation_uuid', $uuid)->firstOrFail(), (string) $data['amount'])]);
    }

    public function openingBalances(Request $request): JsonResponse
    {
        $admin = $this->admin($request, 'finance.adjust.request');
        $data = $request->validate([
            'asset' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'debit_account_type' => ['required', 'string', 'max:120'],
            'credit_account_type' => ['required', 'string', 'max:120'],
            'ownership_class' => ['required', 'string', 'max:64'],
            'evidence' => ['required', 'array'],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
        ]);
        return response()->json(['data' => $this->openingBalances->request($admin, $data)], 202);
    }

    public function approveOpeningBalance(Request $request, string $uuid): JsonResponse
    {
        $admin = $this->admin($request, 'finance.adjust.approve');
        return response()->json(['data' => $this->openingBalances->approve($admin, FinanceOpeningBalanceImport::query()->where('import_uuid', $uuid)->firstOrFail())], 201);
    }

    public function readiness(): JsonResponse
    {
        return response()->json(['data' => $this->readiness->evaluate()]);
    }

    public function breaks(Request $request): JsonResponse
    {
        return response()->json(['data' => FinanceReconciliationBreak::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function dlq(Request $request): JsonResponse
    {
        return response()->json(['data' => FinanceDeadLetterEvent::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function retryDlq(Request $request, string $uuid): JsonResponse
    {
        $this->admin($request, 'finance.reconcile');
        $data = $request->validate(['resolved' => ['required', 'boolean'], 'error' => ['nullable', 'string', 'max:2000']]);
        return response()->json(['data' => $this->dlqService->markRetried(FinanceDeadLetterEvent::query()->where('dlq_uuid', $uuid)->firstOrFail(), (bool) $data['resolved'], $data['error'] ?? null)]);
    }

    private function admin(Request $request, string $permission): Admin
    {
        $admin = $request->user();
        abort_unless($admin instanceof Admin && $admin->hasPermission($permission), 403);

        return $admin;
    }
}
