<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\FinanceAssetSource;
use App\Models\FinanceBackingSnapshot;
use App\Models\FinanceClosePeriod;
use App\Models\FinanceDeadLetterEvent;
use App\Models\FinanceFinancialEvent;
use App\Models\FinanceJournal;
use App\Models\FinanceObligation;
use App\Models\FinanceOpeningBalanceImport;
use App\Models\FinanceReconciliationBreak;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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
use App\Services\FinancialDecimal;
use App\Services\LedgerService;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class Phase17FinanceAccountingReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security-ratelimit.enabled' => false, 'finance.backing.warning_ratio' => '1.00']);
    }

    public function test_ledger_event_posts_idempotent_balanced_finance_journal_without_parallel_balances(): void
    {
        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $transaction = $ledger->cryptoDeposit($user->id, '100', 'USDT', 'DEP-P17-1');

        $event = app(FinanceAccountingService::class)->recordLedgerEvent($transaction, 'CRYPTO_DEPOSIT_CONFIRMED');
        $again = app(FinanceAccountingService::class)->recordLedgerEvent($transaction, 'CRYPTO_DEPOSIT_CONFIRMED');

        $this->assertSame($event->id, $again->id);
        $this->assertSame(1, FinanceFinancialEvent::query()->count());
        $this->assertSame(1, FinanceJournal::query()->count());
        $journal = FinanceJournal::query()->with('lines')->firstOrFail();
        $this->assertSame('POSTED', $journal->status);
        $this->assertCount(2, $journal->lines);
        $this->assertSame('100.000000000000000000', FinancialDecimal::normalize((string) $journal->lines->sum('debit')));
        $this->assertSame('100.000000000000000000', FinancialDecimal::normalize((string) $journal->lines->sum('credit')));
        $this->assertTrue($journal->lines->contains('ownership_class', 'CUSTOMER'));
        $this->assertTrue($journal->lines->contains('ownership_class', 'CORPORATE_TREASURY'));
    }

    public function test_customer_liability_backing_restricted_assets_and_deficit_breaks_are_calculated_from_real_sources(): void
    {
        $user = User::factory()->create();
        $transaction = app(LedgerService::class)->cryptoDeposit($user->id, '100', 'USDT', 'DEP-P17-2');
        app(FinanceAccountingService::class)->recordLedgerEvent($transaction, 'CRYPTO_DEPOSIT_CONFIRMED');
        $this->assetSource('hot_wallet', 'hot-usdt', 'USDT', '60', true, false, 'FRESH');
        $this->assetSource('cold_wallet', 'cold-usdt', 'USDT', '50', true, false, 'FRESH');
        $this->assetSource('restricted', 'restricted-usdt', 'USDT', '1000', true, true, 'FRESH');

        $snapshot = app(FinanceBackingService::class)->calculate('USDT')['USDT'];
        $this->assertSame('100.000000000000000000', $snapshot['liability']);
        $this->assertSame('1110.000000000000000000', $snapshot['gross_assets']);
        $this->assertSame('1000.000000000000000000', $snapshot['restricted_assets']);
        $this->assertSame('110.000000000000000000', $snapshot['eligible_backing']);
        $this->assertSame('HEALTHY', $snapshot['status']);

        FinanceAssetSource::query()->where('source_reference', 'cold-usdt')->update(['amount' => '20']);
        $deficit = app(FinanceBackingService::class)->calculate('USDT')['USDT'];
        $this->assertSame('CRITICAL', $deficit['status']);
        $this->assertTrue(FinanceReconciliationBreak::query()->where('code', 'BACKING_CRITICAL')->where('subject_reference', 'USDT')->exists());
        $this->assertGreaterThanOrEqual(2, FinanceBackingSnapshot::query()->where('asset', 'USDT')->count());
    }

    public function test_trial_balance_balance_sheet_report_snapshot_and_readiness_are_generated_from_journals(): void
    {
        $user = User::factory()->create();
        $transaction = app(LedgerService::class)->fiatDeposit($user->id, '250', 'USD', 'DEP-P17-3');
        app(FinanceAccountingService::class)->recordLedgerEvent($transaction, 'FIAT_DEPOSIT_CONFIRMED');
        $this->assetSource('bank', 'bank-usd', 'USD', '300', true, false, 'FRESH');

        $reports = app(FinanceReportService::class);
        $trial = $reports->trialBalance();
        $this->assertTrue($trial['balanced']);
        $this->assertSame($trial['total_debit'], $trial['total_credit']);

        $balance = $reports->balanceSheet();
        $this->assertTrue($balance['equation_balanced']);
        $snapshot = $reports->snapshot('TRIAL_BALANCE', $trial);
        $this->assertSame('TRIAL_BALANCE', $snapshot->report_type);

        $readiness = app(FinanceReadinessService::class)->evaluate();
        $this->assertSame('READY', $readiness['status']);
    }

    public function test_financial_adjustments_require_maker_checker_and_create_new_ledger_and_journal_entries(): void
    {
        $maker = $this->admin('maker17@example.com');
        $checker = $this->admin('checker17@example.com');
        $service = app(FinanceAdjustmentService::class);

        $request = $service->request($maker, [
            'asset' => 'USDT',
            'amount' => '12.5',
            'debit_account_type' => 'suspense',
            'credit_account_type' => 'finance_adjustment_revenue',
            'reason_code' => 'RECONCILIATION_CORRECTION',
            'reason' => 'Finance correction for reconciliation test.',
        ]);

        $this->expectException(RuntimeException::class);
        try {
            $service->approve($maker, $request, 'same actor blocked');
        } finally {
            $posted = $service->approve($checker, $request->fresh(), 'Independent approval granted.');
            $this->assertSame('POSTED', $posted->status);
            $this->assertNotNull($posted->ledger_reference);
            $this->assertTrue(FinanceFinancialEvent::query()->where('event_type', 'ADMIN_ADJUSTMENT')->exists());
            $journal = FinanceJournal::query()->whereHas('lines', fn ($q) => $q->where('asset', 'USDT'))->latest()->firstOrFail();
            app(FinanceAccountingService::class)->assertJournalBalanced($journal->load('lines'));
        }
    }

    public function test_daily_close_uses_segregation_of_duties_and_admin_finance_routes_are_available(): void
    {
        $maker = $this->admin('close-maker@example.com');
        $checker = $this->admin('close-checker@example.com');
        $user = User::factory()->create();
        $transaction = app(LedgerService::class)->cryptoDeposit($user->id, '10', 'BTC', 'DEP-P17-4');
        app(FinanceAccountingService::class)->recordLedgerEvent($transaction, 'CRYPTO_DEPOSIT_CONFIRMED');
        $this->assetSource('cold_wallet', 'cold-btc', 'BTC', '11', true, false, 'FRESH');

        $period = app(FinanceCloseService::class)->prepare($maker, 'DAILY', '2026-08-23', '2026-08-23');
        $this->assertSame('PREPARED', $period->status);
        $this->expectException(RuntimeException::class);
        try {
            app(FinanceCloseService::class)->approve($maker, $period);
        } finally {
            $approved = app(FinanceCloseService::class)->approve($checker, $period->fresh());
            $this->assertSame('APPROVED_LOCKED', $approved->status);
        }

        $this->actingAs($checker, 'sanctum')->getJson('/api/admin/v1/finance/overview')->assertOk()->assertJsonPath('data.trial_balance.balanced', true);
        $this->actingAs($checker, 'sanctum')->getJson('/api/admin/v1/finance/backing?asset=BTC')->assertOk()->assertJsonPath('data.BTC.status', 'HEALTHY');
        FinanceDeadLetterEvent::query()->create([
            'dlq_uuid' => (string) Str::uuid(),
            'event_type' => 'TEST_FAILURE',
            'source_service' => 'finance_test',
            'status' => 'OPEN',
            'attempts' => 1,
            'error_message' => 'Synthetic test DLQ item.',
        ]);
        $this->actingAs($checker, 'sanctum')->getJson('/api/admin/v1/finance/dlq')->assertOk();
    }

    public function test_period_locking_blocks_backdated_finance_posting_and_reopen_requires_checker(): void
    {
        $maker = $this->admin('lock-maker@example.com');
        $checker = $this->admin('lock-checker@example.com');
        $user = User::factory()->create();
        $transaction = app(LedgerService::class)->fiatDeposit($user->id, '25', 'USD', 'DEP-P17-LOCK');
        app(FinanceAccountingService::class)->recordLedgerEvent($transaction, 'FIAT_DEPOSIT_CONFIRMED');
        $period = app(FinanceCloseService::class)->prepare($maker, 'DAILY', now()->toDateString(), now()->toDateString());
        $locked = app(FinanceCloseService::class)->approve($checker, $period);
        $this->assertSame('APPROVED_LOCKED', $locked->status);

        $next = app(LedgerService::class)->fiatDeposit($user->id, '1', 'USD', 'DEP-P17-LOCKED');
        $this->expectException(RuntimeException::class);
        try {
            app(FinanceAccountingService::class)->recordLedgerEvent($next, 'FIAT_DEPOSIT_CONFIRMED');
        } finally {
            $requested = app(FinanceCloseService::class)->requestReopen($maker, $locked->fresh(), 'Investigation requires controlled reopening.');
            $this->assertSame('REOPEN_REQUESTED', $requested->status);
            $reopened = app(FinanceCloseService::class)->approveReopen($checker, $requested->fresh(), 'Independent approval for reopen.');
            $this->assertSame('REOPENED', $reopened->status);
        }
    }

    public function test_monthly_close_is_idempotent_generates_reports_and_general_ledger_cashflow_pnl_are_available(): void
    {
        $admin = $this->admin('monthly-admin@example.com');
        $checker = $this->admin('monthly-checker@example.com');
        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $deposit = $ledger->cryptoDeposit($user->id, '40', 'USDT', 'DEP-P17-MONTH');
        $userFunding = $ledger->getOrCreateAccount($user->id, 'funding', 'USDT');
        $feeRevenue = $ledger->getOrCreateAccount(null, 'trading_fee_revenue', 'USDT');
        $fee = $ledger->postDoubleEntry('FEE-P17-MONTH', 'Trading fee revenue', [
            ['account_id' => $userFunding->id, 'amount' => '-2', 'asset' => 'USDT', 'user_id' => $user->id],
            ['account_id' => $feeRevenue->id, 'amount' => '2', 'asset' => 'USDT'],
        ], 'fee');
        app(FinanceAccountingService::class)->recordLedgerEvent($deposit, 'CRYPTO_DEPOSIT_CONFIRMED');
        app(FinanceAccountingService::class)->recordLedgerEvent($fee, 'TRADING_FEE_COLLECTED');
        $this->assetSource('hot_wallet', 'month-usdt', 'USDT', '50', true, false, 'FRESH');

        $service = app(FinanceCloseService::class);
        $first = $service->prepare($admin, 'MONTHLY', '2026-08-01', '2026-08-31');
        $second = $service->prepare($admin, 'MONTHLY', '2026-08-01', '2026-08-31');
        $this->assertSame($first->id, $second->id);
        $this->assertCount(4, $second->summary['report_snapshots']);
        $this->assertSame('APPROVED_LOCKED', $service->approve($checker, $second)->status);

        $reports = app(FinanceReportService::class);
        $this->assertNotEmpty($reports->generalLedger());
        $this->assertSame('2.000000000000000000', $reports->profitAndLoss()['revenue']);
        $this->assertNotEmpty($reports->cashFlow()['rows']);
        $this->actingAs($checker, 'sanctum')->getJson('/api/admin/v1/finance/general-ledger')->assertOk();
        $this->actingAs($checker, 'sanctum')->getJson('/api/admin/v1/finance/profit-and-loss')->assertOk();
        $this->actingAs($checker, 'sanctum')->getJson('/api/admin/v1/finance/cash-flow')->assertOk();
    }

    public function test_receivables_payables_opening_balances_dlq_data_quality_product_reconciliation_and_treasury_pnl(): void
    {
        $maker = $this->admin('hardening-maker@example.com');
        $checker = $this->admin('hardening-checker@example.com');
        $obligations = app(FinanceObligationService::class);
        $receivable = $obligations->create('RECEIVABLE', 'PROVIDER', 'otc', 'otc-rx-1', 'USDT', '100');
        $this->assertSame('OPEN', $receivable->status);
        $this->assertSame('PARTIALLY_SETTLED', $obligations->settle($receivable, '25')->status);
        $this->assertSame('SETTLED', $obligations->settle($receivable->fresh(), '75')->status);
        $payable = $obligations->create('PAYABLE', 'VENDOR', 'network_fee', 'nf-1', 'USDT', '5');
        $this->assertSame('DISPUTED', $obligations->mark($payable, 'DISPUTED')->status);

        $opening = app(FinanceOpeningBalanceService::class)->request($maker, [
            'asset' => 'USDT',
            'amount' => '10',
            'debit_account_type' => 'opening_assets',
            'credit_account_type' => 'opening_liabilities',
            'ownership_class' => 'CUSTOMER',
            'evidence' => ['source' => 'legacy migration worksheet', 'checksum' => 'phase17-test'],
            'reason' => 'Opening balance import for controlled migration.',
        ]);
        $this->assertSame('PENDING_APPROVAL', $opening->status);
        $posted = app(FinanceOpeningBalanceService::class)->approve($checker, $opening);
        $this->assertSame('POSTED', $posted->status);
        $this->assertTrue(FinanceOpeningBalanceImport::query()->where('status', 'POSTED')->exists());

        $dlq = app(FinanceDlqService::class)->record('VALUATION_FAILURE', 'finance_test', 'valuation-1', 'Reference price unavailable.', ['asset' => 'XYZ']);
        $this->assertSame('OPEN', $dlq->status);
        $this->assertSame('RESOLVED', app(FinanceDlqService::class)->markRetried($dlq, true)->status);
        $this->assertSame('PASS', app(FinanceDataQualityService::class)->run()['status']);
        $productRecon = app(FinanceProductReconciliationService::class)->run();
        $this->assertArrayHasKey('spot', $productRecon);
        $this->assertArrayHasKey('otc', $productRecon);
        $this->assertIsArray(app(FinanceTreasuryService::class)->treasuryPosition());
        $this->assertIsArray(app(FinanceTreasuryService::class)->pnl());

        $this->actingAs($checker, 'sanctum')->getJson('/api/admin/v1/finance/obligations')->assertOk();
        $this->actingAs($checker, 'sanctum')->getJson('/api/admin/v1/finance/product-reconciliation')->assertOk();
        $this->actingAs($checker, 'sanctum')->getJson('/api/admin/v1/finance/data-quality')->assertOk();
        $this->actingAs($checker, 'sanctum')->getJson('/api/admin/v1/finance/treasury')->assertOk();
    }

    private function assetSource(string $type, string $reference, string $asset, string $amount, bool $eligible, bool $restricted, string $freshness): FinanceAssetSource
    {
        return FinanceAssetSource::query()->create([
            'source_uuid' => (string) Str::uuid(),
            'source_type' => $type,
            'source_reference' => $reference,
            'asset' => $asset,
            'amount' => $amount,
            'location' => $reference,
            'ownership_class' => 'CUSTOMER',
            'eligible_for_backing' => $eligible,
            'restricted' => $restricted,
            'freshness' => $freshness,
            'status' => 'ACTIVE',
            'verified_at' => now(),
        ]);
    }

    private function admin(string $email): Admin
    {
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);
        $permissions = ['finance.view', 'finance.reconcile', 'finance.adjust.request', 'finance.adjust.approve', 'finance.close.prepare', 'finance.close.approve', 'finance.export', 'finance.audit'];
        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission]);
        }
        app(PermissionService::class)->syncRolePermissions($role, $permissions);

        return Admin::query()->create([
            'name' => 'Finance Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }
}
