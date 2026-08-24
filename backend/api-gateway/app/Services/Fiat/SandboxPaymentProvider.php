<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use App\Services\FinancialDecimal;
use RuntimeException;

class SandboxPaymentProvider implements PaymentProviderInterface
{
    public function key(): string
    {
        return 'sandbox';
    }

    public function state(): string
    {
        return 'SANDBOX';
    }

    public function capabilities(): array
    {
        return (array) config('fiat.providers.sandbox.capabilities', []);
    }

    public function healthCheck(): array
    {
        return ['provider' => $this->key(), 'state' => 'HEALTHY', 'environment' => 'sandbox'];
    }

    public function listBanks(string $country, string $currency): array
    {
        $country = strtoupper($country);
        $currency = strtoupper($currency);

        return [
            ['bank_code' => 'SANDBOX001', 'bank_name' => 'ExaEarn Sandbox Bank', 'country' => $country, 'currency' => $currency, 'transfer_supported' => true, 'account_verification_supported' => true],
            ['bank_code' => 'SANDBOX002', 'bank_name' => 'Sandbox Trust Bank', 'country' => $country, 'currency' => $currency, 'transfer_supported' => true, 'account_verification_supported' => true],
        ];
    }

    public function verifyAccount(string $country, string $currency, string $bankCode, string $accountNumber): array
    {
        if (!preg_match('/^\d{6,20}$/', $accountNumber)) {
            throw new RuntimeException('Invalid bank account number.');
        }

        return [
            'verified' => true,
            'account_name' => 'SANDBOX VERIFIED USER',
            'provider_reference' => hash('sha256', $country.$currency.$bankCode.$accountNumber),
        ];
    }

    public function createVirtualAccount(int $userId, string $currency, string $country, string $reference): array
    {
        return [
            'provider_account_id' => 'sandbox-va-'.$reference,
            'account_number' => (string) (9000000000 + $userId),
            'account_name' => 'EXAEARN USER '.$userId,
            'bank_code' => 'SANDBOX001',
            'bank_name' => 'ExaEarn Sandbox Bank',
            'status' => 'ACTIVE',
        ];
    }

    public function verifyWebhook(array $payload, string $rawBody, array $headers): bool
    {
        $secret = (string) config('services.sandbox_payments.webhook_secret', 'sandbox-secret');
        $header = $headers['x-sandbox-signature'] ?? $headers['X-Sandbox-Signature'] ?? '';
        $signature = is_array($header) ? (string) ($header[0] ?? '') : (string) $header;

        return $signature !== '' && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    public function normalizeWebhook(array $payload): array
    {
        return [
            'event_id' => (string) ($payload['event_id'] ?? $payload['id'] ?? ''),
            'event_type' => (string) ($payload['event_type'] ?? $payload['event'] ?? 'payment.success'),
            'provider_transaction_id' => (string) ($payload['transaction_id'] ?? ''),
            'provider_reference' => (string) ($payload['reference'] ?? ''),
            'currency' => strtoupper((string) ($payload['currency'] ?? '')),
            'amount' => FinancialDecimal::normalize((string) ($payload['amount'] ?? '0')),
            'status' => strtoupper((string) ($payload['status'] ?? 'SUCCESSFUL')),
            'destination_account' => (string) ($payload['destination_account'] ?? ''),
            'sender_name' => $payload['sender_name'] ?? null,
            'sender_bank' => $payload['sender_bank'] ?? null,
        ];
    }

    public function initiateTransfer(array $payload): array
    {
        if (!app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Sandbox payment provider cannot execute production transfers.');
        }

        $reference = (string) ($payload['reference'] ?? $payload['idempotency_key'] ?? '');

        return [
            'provider_reference' => 'sandbox-transfer-'.hash('sha256', $reference),
            'status' => 'SUBMITTED',
        ];
    }

    public function verifyTransfer(string $reference): array
    {
        return ['provider_reference' => $reference, 'status' => 'SUCCESSFUL'];
    }

    public function getBalance(string $currency): string
    {
        return '0';
    }

    public function getTransferFee(string $currency, string $amount): string
    {
        return FinancialDecimal::normalize((string) config('fiat.fees.withdrawal_flat', '0'));
    }
}
