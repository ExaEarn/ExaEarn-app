<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InsuranceFundAccount;
use App\Models\InsuranceFundTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InsuranceFundService
{
    public function account(string $product, string $asset): InsuranceFundAccount
    {
        return InsuranceFundAccount::query()->firstOrCreate([
            'product' => strtolower($product),
            'asset' => strtoupper($asset),
        ], [
            'fund_id' => (string) Str::uuid(),
            'balance' => '0',
            'reserved_balance' => '0',
            'status' => 'ACTIVE',
        ]);
    }

    public function credit(string $product, string $asset, string $amount, string $reference, array $metadata = []): InsuranceFundAccount
    {
        return DB::transaction(function () use ($amount, $asset, $metadata, $product, $reference): InsuranceFundAccount {
            $fund = InsuranceFundAccount::query()->whereKey($this->account($product, $asset)->id)->lockForUpdate()->firstOrFail();
            if (InsuranceFundTransaction::query()->where('reference', $reference)->exists()) {
                return $fund;
            }

            $fund->balance = FinancialDecimal::add((string) $fund->balance, $amount);
            $fund->save();
            InsuranceFundTransaction::query()->create([
                'transaction_id' => (string) Str::uuid(),
                'insurance_fund_account_id' => $fund->id,
                'type' => 'CREDIT',
                'amount' => $amount,
                'reference' => $reference,
                'metadata' => $metadata,
            ]);

            return $fund->fresh();
        });
    }

    public function useFund(string $product, string $asset, string $amount, string $reference, array $metadata = []): InsuranceFundAccount
    {
        return DB::transaction(function () use ($amount, $asset, $metadata, $product, $reference): InsuranceFundAccount {
            $fund = InsuranceFundAccount::query()->whereKey($this->account($product, $asset)->id)->lockForUpdate()->firstOrFail();
            if (InsuranceFundTransaction::query()->where('reference', $reference)->exists()) {
                return $fund;
            }
            if (FinancialDecimal::compare((string) $fund->balance, $amount) < 0) {
                throw new RuntimeException('Insurance fund has insufficient balance.');
            }

            $fund->balance = FinancialDecimal::sub((string) $fund->balance, $amount);
            $fund->save();
            InsuranceFundTransaction::query()->create([
                'transaction_id' => (string) Str::uuid(),
                'insurance_fund_account_id' => $fund->id,
                'type' => 'DEBIT',
                'amount' => $amount,
                'reference' => $reference,
                'metadata' => $metadata,
            ]);

            return $fund->fresh();
        });
    }
}
