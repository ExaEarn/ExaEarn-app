<?php

declare(strict_types=1);

namespace App\Services\FiatPayout;

use App\Models\FiatWithdrawalIntent;

class SandboxFiatPayoutProvider implements FiatPayoutProviderInterface
{
    public function key(): string
    {
        return 'sandbox';
    }

    public function banks(string $country, string $currency): array
    {
        $country = strtoupper($country);
        $currency = strtoupper($currency);

        $banks = [
            ['code' => '044', 'name' => 'Access Bank', 'country' => 'NG', 'currency' => 'NGN'],
            ['code' => '058', 'name' => 'GTBank', 'country' => 'NG', 'currency' => 'NGN'],
            ['code' => '033', 'name' => 'United Bank for Africa', 'country' => 'NG', 'currency' => 'NGN'],
            ['code' => '057', 'name' => 'Zenith Bank', 'country' => 'NG', 'currency' => 'NGN'],
            ['code' => '035A', 'name' => 'Kuda Bank', 'country' => 'NG', 'currency' => 'NGN'],
            ['code' => '011', 'name' => 'First Bank of Nigeria', 'country' => 'NG', 'currency' => 'NGN'],
            ['code' => '232', 'name' => 'Sterling Bank', 'country' => 'NG', 'currency' => 'NGN'],
            ['code' => '999991', 'name' => 'PalmPay', 'country' => 'NG', 'currency' => 'NGN'],
        ];

        return array_values(array_filter($banks, fn (array $bank): bool => $bank['country'] === $country && $bank['currency'] === $currency));
    }

    public function resolveAccount(string $country, string $currency, string $bankCode, string $accountNumber): array
    {
        if (!preg_match('/^\d{10}$/', $accountNumber)) {
            throw new \RuntimeException('Enter a valid 10 digit account number.');
        }

        $bank = collect($this->banks($country, $currency))->firstWhere('code', $bankCode);
        if (!$bank) {
            throw new \RuntimeException('Selected bank is unavailable for this currency.');
        }

        return [
            'account_name' => 'Verified ExaEarn User ' . substr($accountNumber, -4),
            'bank_code' => $bankCode,
            'account_number' => $accountNumber,
        ];
    }

    public function submit(FiatWithdrawalIntent $intent): array
    {
        return [
            'provider_reference' => 'SBX-' . $intent->reference,
            'status' => 'processing',
            'estimated_arrival' => $intent->estimated_arrival ?: '5-15 minutes',
            'raw' => [
                'sandbox' => true,
                'reference' => $intent->reference,
                'message' => 'Sandbox payout accepted for processing.',
            ],
        ];
    }

    public function parseWebhook(array $payload, array $headers = []): array
    {
        $secret = (string) config('services.fiat_gateway.sandbox_webhook_secret', '');
        $signature = (string) ($headers['x-exaearn-sandbox-signature'][0] ?? $headers['X-ExaEarn-Sandbox-Signature'][0] ?? '');
        $expected = $secret !== '' ? hash_hmac('sha256', json_encode($payload), $secret) : '';
        $valid = !app()->isProduction() || ($secret !== '' && hash_equals($expected, $signature));

        return [
            'valid' => $valid,
            'event_id' => (string) ($payload['event_id'] ?? $payload['id'] ?? uniqid('sbx_', true)),
            'event_type' => (string) ($payload['event'] ?? 'fiat_withdrawal.status'),
            'reference' => (string) ($payload['reference'] ?? ''),
            'status' => (string) ($payload['status'] ?? 'processing'),
            'payload' => $payload,
        ];
    }
}
