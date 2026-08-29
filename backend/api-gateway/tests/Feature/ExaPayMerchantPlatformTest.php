<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Models\Merchant;
use App\Models\User;
use App\Services\Fiat\ExaPayMerchantService;
use App\Services\FinancialDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExaPayMerchantPlatformTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $payer;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('fiat.fees.merchant_percent', '0.02000000');
        $this->owner = User::factory()->create();
        $this->payer = User::factory()->create();
        Account::query()->create([
            'owner_type' => 'user',
            'owner_id' => $this->payer->id,
            'user_id' => $this->payer->id,
            'account_type' => 'funding',
            'asset' => 'NGN',
            'balance' => '50000',
            'status' => 'active',
        ]);
    }

    public function test_merchant_application_kyb_approval_team_and_api_key(): void
    {
        $service = app(ExaPayMerchantService::class);
        $merchant = $this->approvedMerchant();
        $key = $service->createApiKey($merchant, [
            'name' => 'Checkout server',
            'scopes' => ['payments.read', 'payments.create'],
        ]);

        $this->assertSame('ACTIVE', $merchant->status);
        $this->assertSame('APPROVED', $merchant->kyb_status);
        $this->assertDatabaseHas('merchant_team_members', ['merchant_id' => $merchant->id, 'user_id' => $this->owner->id, 'role' => 'OWNER']);
        $this->assertStringStartsWith('exapay_sk_', $key['secret']);
        $this->assertSame('ACTIVE', $key['api_key']->status);

        $revoked = $service->revokeApiKey($key['api_key']);
        $this->assertSame('REVOKED', $revoked->status);
    }

    public function test_restricted_merchant_cannot_create_payment_intent(): void
    {
        $service = app(ExaPayMerchantService::class);
        $merchant = $this->approvedMerchant();
        $service->restrict($merchant, 'RESTRICTED', 'risk hold');

        $this->expectException(\RuntimeException::class);
        $service->createIntent($merchant->fresh(), $this->intentPayload('restricted-idem'));
    }

    public function test_payment_intent_idempotency_hosted_checkout_capture_and_webhook_event(): void
    {
        $service = app(ExaPayMerchantService::class);
        $merchant = $this->approvedMerchant();

        $first = $service->createIntent($merchant, $this->intentPayload('intent-idem-1'));
        $second = $service->createIntent($merchant, $this->intentPayload('intent-idem-1'));
        $checkout = $service->checkout($first['checkout_token']);
        $captured = $service->capture($first['pay_intent_id']);
        $capturedAgain = $service->capture($first['pay_intent_id']);

        $this->assertSame($first['pay_intent_id'], $second['pay_intent_id']);
        $this->assertSame($first['pay_intent_id'], $checkout['payment']['pay_intent_id']);
        $this->assertSame('CAPTURED', $captured['status']);
        $this->assertSame($captured['ledger_reference'], $capturedAgain['ledger_reference']);
        $this->assertSame(1, LedgerTransaction::query()->where('reference', $captured['ledger_reference'])->count());
        $this->assertDatabaseHas('merchant_webhook_events', ['merchant_id' => $merchant->id, 'event_type' => 'payment.captured']);
    }

    public function test_payment_link_creates_real_payment_intent_and_enforces_usage_limit(): void
    {
        $service = app(ExaPayMerchantService::class);
        $merchant = $this->approvedMerchant();
        $link = $service->createPaymentLink($merchant, [
            'title' => 'Starter invoice',
            'amount_mode' => 'FIXED',
            'amount' => '2500',
            'currency' => 'NGN',
            'maximum_uses' => 1,
        ]);

        $intent = $service->createIntentFromLink($link, [
            'payer_user_id' => $this->payer->id,
            'idempotency_key' => 'link-idem-1',
        ]);

        $this->assertSame('Starter invoice', $intent['description']);
        $this->assertSame(1, $link->fresh()->uses_count);

        $this->expectException(\RuntimeException::class);
        $service->createIntentFromLink($link->fresh(), [
            'payer_user_id' => $this->payer->id,
            'idempotency_key' => 'link-idem-2',
        ]);
    }

    public function test_refund_is_idempotent_and_ledger_reverses_payment(): void
    {
        $service = app(ExaPayMerchantService::class);
        $merchant = $this->approvedMerchant();
        $intent = $service->createIntent($merchant, $this->intentPayload('refund-idem-1'));
        $captured = $service->capture($intent['pay_intent_id']);
        $refund = $service->refund($merchant, $captured['pay_intent_id'], 'NGN', 'customer_request');
        $refundAgain = $service->refund($merchant, $captured['pay_intent_id'], 'NGN', 'customer_retry');

        $this->assertSame('COMPLETED', $refund['status']);
        $this->assertSame($refund['refund_id'], $refundAgain['refund_id']);
        $this->assertSame('50000.000000000000000000', (string) Account::query()->where('user_id', $this->payer->id)->where('asset', 'NGN')->value('balance'));
    }

    public function test_dispute_settlement_and_reconciliation_are_recorded(): void
    {
        $service = app(ExaPayMerchantService::class);
        $merchant = $this->approvedMerchant();
        $intent = $service->createIntent($merchant, $this->intentPayload('settle-idem-1'));
        $service->capture($intent['pay_intent_id']);

        $dispute = $service->openDispute($merchant, [
            'provider' => 'sandbox',
            'provider_reference' => $intent['pay_intent_id'],
            'currency' => 'NGN',
            'amount' => '1000',
            'metadata' => ['reason' => 'duplicate'],
        ]);
        $settlement = $service->settlement($merchant, 'NGN');
        $reconciliation = $service->reconcile($merchant);

        $this->assertSame('OPEN', $dispute['status']);
        $this->assertSame('PENDING', $settlement['status']);
        $this->assertSame('PASS', $reconciliation['status']);
        $this->assertDatabaseHas('merchant_webhook_events', ['merchant_id' => $merchant->id, 'event_type' => 'settlement.created']);
    }

    public function test_exapay_financial_invariants_hold(): void
    {
        $service = app(ExaPayMerchantService::class);
        $merchant = $this->approvedMerchant();
        $intent = $service->createIntent($merchant, $this->intentPayload('invariant-idem-1'));
        $captured = $service->capture($intent['pay_intent_id']);

        $transaction = LedgerTransaction::query()->where('reference', $captured['ledger_reference'])->firstOrFail();
        $sum = '0';
        foreach ($transaction->entries as $entry) {
            $sum = FinancialDecimal::add($sum, (string) $entry->amount);
        }

        $this->assertSame(0, FinancialDecimal::compare($sum, '0'));
        $this->assertSame(1, DB::table('exaearn_pay_intents')->where('idempotency_key', 'invariant-idem-1')->count());
    }

    private function approvedMerchant(): Merchant
    {
        $service = app(ExaPayMerchantService::class);
        $merchant = $service->apply($this->owner, [
            'business_name' => 'Ada Commerce',
            'country' => 'NG',
            'business_type' => 'DIGITAL_GOODS',
            'settlement_currency' => 'NGN',
            'environment' => 'SANDBOX',
            'expected_monthly_volume' => '100000',
        ]);

        return $service->approve($merchant, 1, 'KYB documents accepted for sandbox test.');
    }

    private function intentPayload(string $idempotency): array
    {
        return [
            'payer_user_id' => $this->payer->id,
            'amount' => '1000',
            'currency' => 'NGN',
            'description' => 'Invoice',
            'merchant_reference' => 'INV-'.$idempotency,
            'customer_reference' => 'CUS-1',
            'environment' => 'SANDBOX',
            'payment_method' => 'EXAEARN_BALANCE',
            'idempotency_key' => $idempotency,
        ];
    }
}
