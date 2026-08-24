<?php

declare(strict_types=1);

namespace App\Services;

class FuturesInsuranceFundService
{
    public function __construct(private readonly LedgerService $ledger)
    {
    }

    public function account(string $asset = 'USDT')
    {
        return $this->ledger->getOrCreateAccount(null, 'futures_insurance_fund', strtoupper($asset));
    }

    public function balance(string $asset = 'USDT'): string
    {
        return (string) $this->account($asset)->balance;
    }

    public function credit(string $asset, string $amount, string $reference, array $metadata = [])
    {
        $treasury = $this->ledger->getOrCreateAccount(null, 'futures_clearing', strtoupper($asset));
        $insurance = $this->account($asset);

        return $this->ledger->postDoubleEntry($reference, 'Futures insurance fund credit', [
            ['account_id' => $treasury->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => strtoupper($asset), 'metadata' => $metadata],
            ['account_id' => $insurance->id, 'amount' => $amount, 'asset' => strtoupper($asset), 'metadata' => $metadata],
        ], 'futures_insurance_credit', array_merge($metadata, ['source_service' => 'futures_insurance']));
    }

    public function debit(string $asset, string $amount, string $reference, array $metadata = [])
    {
        $insurance = $this->account($asset);
        if (FinancialDecimal::compare((string) $insurance->balance, $amount) < 0) {
            throw new \RuntimeException('Futures insurance fund is insufficient.');
        }

        $clearing = $this->ledger->getOrCreateAccount(null, 'futures_bankruptcy_deficit', strtoupper($asset));

        return $this->ledger->postDoubleEntry($reference, 'Futures insurance fund deficit coverage', [
            ['account_id' => $insurance->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => strtoupper($asset), 'metadata' => $metadata],
            ['account_id' => $clearing->id, 'amount' => $amount, 'asset' => strtoupper($asset), 'metadata' => $metadata],
        ], 'futures_insurance_debit', array_merge($metadata, ['source_service' => 'futures_insurance']));
    }

    public function coverDeficitOrFail(string $asset, string $amount, string $reference, array $metadata = []): bool
    {
        if (FinancialDecimal::compare($amount, '0') <= 0) {
            return true;
        }

        $this->debit($asset, $amount, $reference, $metadata);

        return true;
    }
}
