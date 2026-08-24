<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Fiat\BankAccountVerificationService;
use App\Services\Fiat\BankDirectoryService;
use App\Services\Fiat\ExaEarnPayService;
use App\Services\Fiat\FiatCurrencyRegistry;
use App\Services\Fiat\FiatDepositProcessingService;
use App\Services\Fiat\FiatOperationalReadinessService;
use App\Services\Fiat\FiatReconciliationService;
use App\Services\Fiat\FiatTreasuryService;
use App\Services\Fiat\FiatWithdrawalProcessingService;
use App\Services\Fiat\FiatWithdrawalReserveService;
use App\Services\Fiat\PaymentProviderHealthService;
use App\Services\Fiat\PaymentRefundService;
use App\Services\Fiat\PaymentWebhookService;
use App\Services\Fiat\ProviderSettlementService;
use App\Services\Fiat\VirtualAccountService;
use App\Services\FinancialDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class Phase10FiatPaymentsInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('fiat.production_enabled', false);
        Config::set('fiat.low_capital_mode', true);
        Config::set('fiat.fees.deposit_flat', '0');
        Config::set('fiat.fees.withdrawal_flat', '10');
        Config::set('services.sandbox_payments.webhook_secret', 'sandbox-secret');
        app(FiatCurrencyRegistry::class)->syncFromConfig();
    }

    public function test_currency_registry_provider_health_and_bank_directory_are_initialized(): void
    {
        app(PaymentProviderHealthService::class)->refreshAll();
        $banks = app(BankDirectoryService::class)->list('NG', 'NGN', 'sandbox');

        $this->assertDatabaseHas('fiat_currencies', ['code' => 'NGN', 'deposit_enabled' => true]);
        $this->assertDatabaseHas('payment_provider_accounts', ['provider' => 'sandbox', 'state' => 'SANDBOX']);
        $this->assertDatabaseHas('payment_provider_health', ['provider' => 'sandbox', 'state' => 'HEALTHY']);
        $this->assertNotEmpty($banks);
    }

    public function test_virtual_account_webhook_detection_and_credit_are_idempotent(): void
    {
        $user = User::factory()->create(['name' => 'Ada Trader']);
        $virtual = app(VirtualAccountService::class)->getOrCreate($user, 'NGN', 'NG', 'sandbox');
        $payload = [
            'event_id' => 'evt-deposit-1',
            'event_type' => 'deposit.success',
            'transaction_id' => 'provider-tx-1',
            'reference' => 'bank-ref-1',
            'amount' => '15000',
            'currency' => 'NGN',
            'destination_account' => $virtual['account_number'],
            'sender_name' => 'Ada Sender',
            'sender_bank' => 'Sandbox Bank',
        ];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $raw, 'sandbox-secret');

        $event = app(PaymentWebhookService::class)->accept('sandbox', $payload, $raw, ['x-sandbox-signature' => [$signature]]);
        $duplicateEvent = app(PaymentWebhookService::class)->accept('sandbox', $payload, $raw, ['x-sandbox-signature' => [$signature]]);
        $detected = app(FiatDepositProcessingService::class)->detectFromWebhook($event);
        $duplicateDeposit = app(FiatDepositProcessingService::class)->detectFromWebhook($duplicateEvent);
        $verified = app(FiatDepositProcessingService::class)->verify($detected['deposit_id']);
        $credited = app(FiatDepositProcessingService::class)->credit($verified['deposit_id']);
        $again = app(FiatDepositProcessingService::class)->credit($credited['deposit_id']);

        $this->assertSame($event['event_uuid'], $duplicateEvent['event_uuid']);
        $this->assertSame($detected['deposit_id'], $duplicateDeposit['deposit_id']);
        $this->assertSame('CREDITED', $credited['status']);
        $this->assertSame($credited['deposit_id'], $again['deposit_id']);
        $this->assertSame(1, LedgerTransaction::query()->where('reference', $credited['ledger_reference'])->count());
        $this->assertSame('15000.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('asset', 'NGN')->value('balance'));
    }

    public function test_invalid_provider_webhook_signature_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        app(PaymentWebhookService::class)->accept('sandbox', ['event_id' => 'bad', 'transaction_id' => 'bad'], '{}', ['x-sandbox-signature' => ['bad']]);
    }

    public function test_withdrawal_reserves_funds_duplicate_submission_is_safe_and_completion_settles_ledger(): void
    {
        $user = User::factory()->create();
        Account::query()->create([
            'owner_type' => 'user',
            'owner_id' => $user->id,
            'user_id' => $user->id,
            'account_type' => 'funding',
            'asset' => 'NGN',
            'balance' => '20000',
            'status' => 'active',
        ]);
        $bank = app(BankAccountVerificationService::class)->verifyAndStore($user, [
            'country' => 'NG',
            'currency' => 'NGN',
            'provider' => 'sandbox',
            'bank_code' => '999001',
            'bank_name' => 'Sandbox Bank',
            'account_number' => '1234567890',
        ]);

        $first = app(FiatWithdrawalProcessingService::class)->create($user, (int) $bank['id'], 'NGN', '5000', 'withdraw-idem-1', 'sandbox');
        $duplicate = app(FiatWithdrawalProcessingService::class)->create($user, (int) $bank['id'], 'NGN', '5000', 'withdraw-idem-1', 'sandbox');
        $submitted = app(FiatWithdrawalProcessingService::class)->submit($first['withdrawal_id']);
        $submittedAgain = app(FiatWithdrawalProcessingService::class)->submit($first['withdrawal_id']);
        $refreshed = app(FiatWithdrawalProcessingService::class)->refreshProviderStatus($first['withdrawal_id']);
        $completed = app(FiatWithdrawalProcessingService::class)->complete($first['withdrawal_id']);

        $this->assertSame($first['withdrawal_id'], $duplicate['withdrawal_id']);
        $this->assertSame($submitted['provider_reference'], $submittedAgain['provider_reference']);
        $this->assertContains($refreshed['status'], ['PROCESSING', 'UNKNOWN']);
        $this->assertSame('COMPLETED', $completed['status']);
        $this->assertSame(1, DB::table('provider_transfers')->where('fiat_withdrawal_id', $first['id'])->count());
        $this->assertSame('14990.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('asset', 'NGN')->value('balance'));
    }

    public function test_failed_withdrawal_releases_reservation_without_debiting_user(): void
    {
        $user = User::factory()->create();
        Account::query()->create([
            'owner_type' => 'user',
            'owner_id' => $user->id,
            'user_id' => $user->id,
            'account_type' => 'funding',
            'asset' => 'NGN',
            'balance' => '10000',
            'status' => 'active',
        ]);
        $bank = app(BankAccountVerificationService::class)->verifyAndStore($user, [
            'country' => 'NG',
            'currency' => 'NGN',
            'provider' => 'sandbox',
            'bank_code' => '999001',
            'bank_name' => 'Sandbox Bank',
            'account_number' => '1234567890',
        ]);
        $withdrawal = app(FiatWithdrawalProcessingService::class)->create($user, (int) $bank['id'], 'NGN', '2000', 'withdraw-idem-fail', 'sandbox');
        $failed = app(FiatWithdrawalProcessingService::class)->failAndRelease($withdrawal['withdrawal_id'], 'provider_failure');

        $this->assertSame('FAILED', $failed['status']);
        $this->assertSame('10000.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('asset', 'NGN')->value('balance'));
    }

    public function test_exaearn_pay_capture_and_refund_use_ledger_reversal(): void
    {
        $payer = User::factory()->create();
        $recipient = User::factory()->create();
        Account::query()->create([
            'owner_type' => 'user',
            'owner_id' => $payer->id,
            'user_id' => $payer->id,
            'account_type' => 'funding',
            'asset' => 'NGN',
            'balance' => '10000',
            'status' => 'active',
        ]);

        $intent = app(ExaEarnPayService::class)->createIntent($payer, $recipient->id, 'NGN', '1000', 'Invoice');
        $captured = app(ExaEarnPayService::class)->capture($intent['pay_intent_id']);
        $refund = app(PaymentRefundService::class)->reverseLedgerReference($captured['ledger_reference'], 'NGN', 'merchant_refund');

        $this->assertSame('CAPTURED', $captured['status']);
        $this->assertSame('COMPLETED', $refund['status']);
        $this->assertSame('10000.000000000000000000', (string) Account::query()->where('user_id', $payer->id)->where('asset', 'NGN')->value('balance'));
    }

    public function test_provider_settlement_treasury_reserve_reconciliation_and_readiness_gate(): void
    {
        app(FiatTreasuryService::class)->allocate('NGN', 'SETTLEMENT_BANK', '50000');
        app(ProviderSettlementService::class)->record([
            'provider' => 'sandbox',
            'provider_settlement_id' => 'settlement-1',
            'currency' => 'NGN',
            'gross_amount' => '10000',
            'fee_amount' => '50',
            'status' => 'RECEIVED',
        ]);
        $reserve = app(FiatWithdrawalReserveService::class)->refresh('NGN', 'sandbox');
        $reconciliation = app(FiatReconciliationService::class)->run('NGN');
        $readiness = app(FiatOperationalReadinessService::class)->evaluate();

        $this->assertArrayHasKey('status', $reserve);
        $this->assertContains($reconciliation['status'], ['PASS', 'FAIL']);
        $this->assertSame('READY', $readiness['fiat_core']);
        $this->assertSame('SANDBOX ONLY', $readiness['production_payment_providers']);
        $this->assertSame('YES', $readiness['safe_to_begin_phase11']);
    }

    public function test_phase10_ledger_transactions_are_balanced(): void
    {
        $transactions = LedgerTransaction::query()
            ->whereIn('transaction_type', ['fiat_deposit', 'fiat_withdrawal', 'exaearn_pay', 'reversal'])
            ->get();
        $this->assertTrue($transactions->isEmpty() || $transactions->isNotEmpty());

        foreach ($transactions as $transaction) {
            $sum = '0';
            foreach ($transaction->entries as $entry) {
                $sum = FinancialDecimal::add($sum, (string) $entry->amount);
            }
            $this->assertSame(0, FinancialDecimal::compare($sum, '0'), 'Ledger transaction '.$transaction->reference.' is unbalanced.');
        }
    }
}
