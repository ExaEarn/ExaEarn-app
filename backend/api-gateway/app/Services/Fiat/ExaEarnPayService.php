<?php

declare(strict_types=1);

namespace App\Services\Fiat;

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
            $reference = 'exaearn-pay:'.$intent->pay_intent_id;
            $this->settlement->exaearnPayTransfer((int) $intent->payer_user_id, (int) $intent->recipient_user_id, (string) $intent->currency, (string) $intent->amount, (string) $intent->fee_amount, $reference, [
                'pay_intent_id' => $intent->pay_intent_id,
            ]);
            DB::table('exaearn_pay_intents')->where('id', $intent->id)->update([
                'status' => 'CAPTURED',
                'ledger_reference' => $reference,
                'updated_at' => now(),
            ]);

            return (array) DB::table('exaearn_pay_intents')->where('id', $intent->id)->first();
        });
    }
}
