<?php

declare(strict_types=1);

namespace App\Services\FiatPayout;

use App\Models\FiatWithdrawalIntent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LegacyFiatPayoutProvider implements FiatPayoutProviderInterface
{
    private string $gateway = 'flutterwave';

    public function forGateway(string $gateway): self
    {
        $clone = clone $this;
        $clone->gateway = strtolower($gateway);

        return $clone;
    }

    public function key(): string
    {
        return $this->gateway;
    }

    public function banks(string $country, string $currency): array
    {
        if ($this->gateway !== 'flutterwave' || strtoupper($country) !== 'NG') {
            return app(SandboxFiatPayoutProvider::class)->banks($country, $currency);
        }

        $secret = (string) config('services.flutterwave.secret_key');
        if ($secret === '') {
            return app(SandboxFiatPayoutProvider::class)->banks($country, $currency);
        }

        return Cache::remember('fiat_withdrawal_banks_flutterwave_' . strtoupper($country), 86400, function () use ($country, $currency, $secret): array {
            $response = Http::withToken($secret)->timeout(8)->get('https://api.flutterwave.com/v3/banks/' . strtoupper($country))->throw();

            return collect($response->json('data', []))
                ->map(fn (array $bank): array => [
                    'code' => (string) ($bank['code'] ?? ''),
                    'name' => (string) ($bank['name'] ?? ''),
                    'country' => strtoupper($country),
                    'currency' => strtoupper($currency),
                ])
                ->filter(fn (array $bank): bool => $bank['code'] !== '' && $bank['name'] !== '')
                ->values()
                ->all();
        });
    }

    public function resolveAccount(string $country, string $currency, string $bankCode, string $accountNumber): array
    {
        $secret = (string) config('services.flutterwave.secret_key');
        if ($this->gateway !== 'flutterwave' || $secret === '') {
            return app(SandboxFiatPayoutProvider::class)->resolveAccount($country, $currency, $bankCode, $accountNumber);
        }

        $response = Http::withToken($secret)
            ->timeout(8)
            ->post('https://api.flutterwave.com/v3/accounts/resolve', [
                'account_bank' => $bankCode,
                'account_number' => $accountNumber,
            ])
            ->throw();

        $data = $response->json();
        if (($data['status'] ?? null) !== 'success' || empty($data['data']['account_name'])) {
            throw new \RuntimeException('Unable to verify bank account.');
        }

        return [
            'account_name' => (string) $data['data']['account_name'],
            'bank_code' => $bankCode,
            'account_number' => $accountNumber,
        ];
    }

    public function submit(FiatWithdrawalIntent $intent): array
    {
        if ($this->gateway === 'flutterwave') {
            return $this->submitFlutterwave($intent);
        }

        if ($this->gateway === 'nomba') {
            return $this->submitNomba($intent);
        }

        return app(SandboxFiatPayoutProvider::class)->submit($intent);
    }

    public function parseWebhook(array $payload, array $headers = []): array
    {
        $valid = match ($this->gateway) {
            'flutterwave' => $this->verifyFlutterwaveWebhook($headers),
            'nomba' => $this->verifyNombaWebhook($payload, $headers),
            default => false,
        };

        return [
            'valid' => $valid,
            'event_id' => (string) ($payload['event_id'] ?? $payload['data']['id'] ?? $payload['id'] ?? uniqid($this->gateway . '_', true)),
            'event_type' => (string) ($payload['event'] ?? $payload['eventType'] ?? 'fiat_withdrawal.status'),
            'reference' => (string) ($payload['data']['reference'] ?? $payload['data']['transactionReference'] ?? $payload['reference'] ?? ''),
            'status' => (string) ($payload['data']['status'] ?? $payload['status'] ?? 'processing'),
            'payload' => $payload,
        ];
    }

    private function submitFlutterwave(FiatWithdrawalIntent $intent): array
    {
        $secret = (string) config('services.flutterwave.secret_key');
        if ($secret === '') {
            return app(SandboxFiatPayoutProvider::class)->submit($intent);
        }

        $response = Http::withToken($secret)
            ->timeout(15)
            ->post('https://api.flutterwave.com/v3/transfers', [
                'account_bank' => $intent->bank_code,
                'account_number' => $intent->metadata['account_number'] ?? '',
                'amount' => (float) $intent->recipient_receives,
                'currency' => $intent->currency,
                'reference' => $intent->reference,
                'narration' => $intent->narration,
                'meta' => [
                    'user_id' => $intent->user_id,
                    'intent_id' => $intent->uuid,
                ],
            ])
            ->throw();

        $data = $response->json();
        if (($data['status'] ?? null) !== 'success') {
            throw new \RuntimeException('Fiat payout provider rejected the transfer.');
        }

        return [
            'provider_reference' => (string) ($data['data']['reference'] ?? $intent->reference),
            'status' => 'processing',
            'estimated_arrival' => '1-3 business days',
            'raw' => $data,
        ];
    }

    private function submitNomba(FiatWithdrawalIntent $intent): array
    {
        $apiKey = (string) config('services.nomba.api_key');
        if ($apiKey === '') {
            return app(SandboxFiatPayoutProvider::class)->submit($intent);
        }

        $response = Http::withToken($apiKey)
            ->timeout(15)
            ->post('https://api.nomba.com/v1/bank-transfers', [
                'account' => [
                    'accountNumber' => $intent->metadata['account_number'] ?? '',
                    'bankCode' => $intent->bank_code,
                ],
                'amount' => (float) $intent->recipient_receives,
                'currency' => $intent->currency,
                'reference' => $intent->reference,
                'description' => $intent->narration,
            ])
            ->throw();

        $data = $response->json();
        if (!in_array(($data['status'] ?? null), ['success', 'pending'], true)) {
            throw new \RuntimeException('Fiat payout provider rejected the transfer.');
        }

        return [
            'provider_reference' => (string) ($data['data']['transactionReference'] ?? $data['data']['id'] ?? $intent->reference),
            'status' => 'processing',
            'estimated_arrival' => '1-3 business days',
            'raw' => $data,
        ];
    }

    private function verifyFlutterwaveWebhook(array $headers): bool
    {
        $secretHash = (string) config('services.flutterwave.secret_hash', '');
        if ($secretHash === '') {
            return !app()->isProduction();
        }

        $signature = (string) ($headers['verif-hash'][0] ?? $headers['Verif-Hash'][0] ?? '');

        return $signature !== '' && hash_equals($secretHash, $signature);
    }

    private function verifyNombaWebhook(array $payload, array $headers): bool
    {
        $secret = (string) config('services.nomba.webhook_secret', '');
        if ($secret === '') {
            return !app()->isProduction();
        }

        $signature = (string) ($headers['x-nomba-signature'][0] ?? $headers['X-Nomba-Signature'][0] ?? '');
        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', json_encode($payload), $secret);

        return hash_equals($expected, $signature);
    }
}
