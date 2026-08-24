<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use App\Services\FinancialDecimal;
use App\Services\LedgerReversalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentRefundService
{
    public function __construct(private readonly LedgerReversalService $reversals)
    {
    }

    public function reverseLedgerReference(string $originalReference, string $currency, string $reason): array
    {
        return DB::transaction(function () use ($currency, $originalReference, $reason): array {
            $existing = DB::table('payment_refunds')->where('original_reference', $originalReference)->lockForUpdate()->first();
            if ($existing) {
                return (array) $existing;
            }
            $amount = (string) DB::table('ledger_entries')
                ->where('reference', $originalReference)
                ->where('asset', strtoupper($currency))
                ->whereNotNull('user_id')
                ->where('amount', '>', 0)
                ->sum('amount');
            if (FinancialDecimal::compare($amount, '0') <= 0) {
                throw new RuntimeException('Refundable amount could not be determined.');
            }
            $reversalReference = 'refund:'.$originalReference;
            $this->reversals->reverse($originalReference, $reversalReference, $reason, 'system', null, ['refund_reason' => $reason]);
            $pk = DB::table('payment_refunds')->insertGetId([
                'refund_id' => (string) Str::uuid(),
                'original_reference' => $originalReference,
                'currency' => strtoupper($currency),
                'amount' => $amount,
                'status' => 'COMPLETED',
                'ledger_reference' => $reversalReference,
                'metadata' => json_encode(['reason' => $reason], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (array) DB::table('payment_refunds')->where('id', $pk)->first();
        });
    }
}
