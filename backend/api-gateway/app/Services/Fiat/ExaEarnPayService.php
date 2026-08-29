<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use App\Models\Merchant;
use App\Models\User;
use App\Services\FinancialDecimal;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ExaEarnPayService
{
    public function __construct(private readonly SettlementService $settlement)
    {
    }

    public function createIntent(User $payer, int $recipientUserId, string $currency, string $amount, ?string $description = null): array
    {
        $currency = strtoupper($currency);
        $fee = FinancialDecimal::mul($amount, (string) config('fiat.fees.merchant_percent', '0'));
        $pk = DB::table('exaearn_pay_intents')->insertGetId([
            'pay_intent_id' => (string) Str::uuid(),
            'payer_user_id' => $payer->id,
            'recipient_user_id' => $recipientUserId,
            'public_reference' => 'PAY-'.strtoupper(Str::random(14)),
            'currency' => $currency,
            'amount' => FinancialDecimal::normalize($amount),
            'fee_amount' => FinancialDecimal::normalize($fee),
            'description' => $description,
            'status' => 'CREATED',
            'expires_at' => now()->addMinutes(30),
            'metadata' => json_encode(['source' => 'exaearn_pay'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (array) DB::table('exaearn_pay_intents')->where('id', $pk)->first();
    }

    public function createMerchantIntent(Merchant $merchant, array $payload): array
    {
        $currency = strtoupper((string) $payload['currency']);
        $amount = FinancialDecimal::normalize((string) $payload['amount']);
        $pricing = (array) ($payload['pricing_snapshot'] ?? []);
        $fee = FinancialDecimal::normalize((string) ($pricing['fee_amount'] ?? '0'));
        $providerFee = FinancialDecimal::normalize((string) ($pricing['provider_fee_amount'] ?? '0'));
        $netMerchant = FinancialDecimal::normalize((string) ($pricing['net_amount'] ?? FinancialDecimal::sub($amount, $fee)));

        $pk = DB::table('exaearn_pay_intents')->insertGetId([
            'pay_intent_id' => (string) Str::uuid(),
            'merchant_id' => $merchant->id,
            'payer_user_id' => $payload['payer_user_id'] ?? null,
            'recipient_user_id' => $merchant->user_id,
            'public_reference' => 'EPAY-'.strtoupper(Str::random(16)),
            'merchant_reference' => $payload['merchant_reference'] ?? null,
            'customer_reference' => $payload['customer_reference'] ?? null,
            'environment' => strtoupper((string) ($payload['environment'] ?? $merchant->environment)),
            'capture_mode' => strtoupper((string) ($payload['capture_mode'] ?? 'AUTOMATIC')),
            'payment_method' => strtoupper((string) ($payload['payment_method'] ?? 'EXAEARN_BALANCE')),
            'provider' => $payload['provider'] ?? null,
            'provider_reference' => $payload['provider_reference'] ?? null,
            'currency' => $currency,
            'amount' => $amount,
            'fee_amount' => $fee,
            'provider_fee_amount' => $providerFee,
            'net_merchant_amount' => $netMerchant,
            'description' => $payload['description'] ?? null,
            'status' => 'CREATED',
            'expires_at' => $payload['expires_at'] ?? now()->addMinutes(30),
            'pricing_snapshot' => json_encode($pricing, JSON_THROW_ON_ERROR),
            'checkout_token_hash' => $payload['checkout_token_hash'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
            'metadata' => json_encode($payload['metadata'] ?? ['source' => 'exapay_merchant'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (array) DB::table('exaearn_pay_intents')->where('id', $pk)->first();
    }

    public function capture(string $payIntentId): array
    {
        return DB::transaction(function () use ($payIntentId): array {
            $intent = DB::table('exaearn_pay_intents')
                ->where('pay_intent_id', $payIntentId)
                ->orWhere('public_reference', $payIntentId)
                ->lockForUpdate()
                ->first();
            if (!$intent) {
                throw new RuntimeException('Payment intent not found.');
            }
            if ($intent->status === 'CAPTURED') {
                return (array) $intent;
            }
            if ($intent->status !== 'CREATED') {
                throw new RuntimeException('Payment intent cannot be captured.');
            }
            if ($intent->expires_at !== null && $intent->expires_at < now()) {
                throw new RuntimeException('Payment intent expired.');
            }
            if (! empty($intent->merchant_id)) {
                $merchant = DB::table('merchants')->where('id', $intent->merchant_id)->lockForUpdate()->first();
                if (! $merchant || $merchant->status !== 'ACTIVE' || $merchant->kyb_status !== 'APPROVED' || $merchant->risk_status === 'RESTRICTED') {
                    throw new RuntimeException('Merchant is not active for capture.');
                }
            }
            $reference = 'exaearn-pay:'.$intent->pay_intent_id;
            $this->settlement->exaearnPayTransfer((int) $intent->payer_user_id, (int) $intent->recipient_user_id, (string) $intent->currency, (string) $intent->amount, (string) $intent->fee_amount, $reference, [
                'pay_intent_id' => $intent->pay_intent_id,
                'merchant_id' => $intent->merchant_id ?? null,
                'pricing_snapshot' => json_decode((string) ($intent->pricing_snapshot ?? '{}'), true) ?: [],
            ]);
            DB::table('exaearn_pay_intents')->where('id', $intent->id)->update([
                'status' => 'CAPTURED',
                'ledger_reference' => $reference,
                'captured_at' => now(),
                'updated_at' => now(),
            ]);

            return (array) DB::table('exaearn_pay_intents')->where('id', $intent->id)->first();
        });
    }
}
