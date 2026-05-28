<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PaymentIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentGatewayService
{
    public function __construct(private readonly TransactionService $transactionService)
    {
    }

    public function createIntent(int $userId, string $provider, string $currency, string $amount): PaymentIntent
    {
        return PaymentIntent::create([
            'intent_id' => (string) Str::uuid(),
            'user_id' => $userId,
            'provider' => strtolower($provider),
            'currency' => strtoupper($currency),
            'amount' => $amount,
            'status' => 'pending',
            'metadata' => [
                'payment_instructions' => 'Complete transfer to virtual account and submit reference.',
                'provider_reference' => null,
            ],
        ]);
    }

    public function resolveProvider(string $countryCode, ?string $requestedProvider = null): string
    {
        $countryCode = strtoupper($countryCode);

        if ($requestedProvider !== null && $requestedProvider !== '') {
            return strtolower($requestedProvider);
        }

        $nombaCountries = array_map(
            static fn (mixed $code): string => strtoupper((string) $code),
            (array) config('payments.nomba_supported_countries', ['NG'])
        );

        if (in_array($countryCode, $nombaCountries, true)) {
            return 'nomba';
        }

        return 'flutterwave';
    }

    public function processWebhook(string $provider, array $payload, string $rawBody, array $headers): PaymentIntent
    {
        return DB::transaction(function () use ($provider, $payload, $rawBody, $headers): PaymentIntent {
            $provider = strtolower($provider);
            $reference = $this->extractIntentReference($provider, $payload);

            if ($reference === '') {
                throw new RuntimeException('Missing payment reference.');
            }

            $intent = PaymentIntent::query()->where('intent_id', $reference)->lockForUpdate()->first();
            if (!$intent) {
                throw new RuntimeException('Payment intent not found.');
            }

            if ($intent->status === 'completed') {
                return $intent;
            }

            if ($intent->provider !== $provider) {
                throw new RuntimeException('Provider mismatch for payment intent.');
            }

            if (!$this->isValidSignature($provider, $payload, $rawBody, $headers)) {
                throw new RuntimeException('Invalid provider signature.');
            }

            $amount = $this->extractAmount($provider, $payload);
            if (bccomp($amount, (string) $intent->amount, 8) !== 0) {
                throw new RuntimeException('Amount mismatch.');
            }

            $providerReference = $this->extractProviderReference($provider, $payload);
            if ($providerReference !== null) {
                $duplicateReference = PaymentIntent::query()
                    ->where('provider', $provider)
                    ->where('provider_reference', $providerReference)
                    ->where('status', 'completed')
                    ->where('id', '!=', $intent->id)
                    ->exists();
                if ($duplicateReference) {
                    throw new RuntimeException('Webhook replay detected.');
                }
            }

            $this->transactionService->recordDeposit(
                $intent->user_id,
                $intent->currency,
                $amount,
                $reference,
                null,
                [
                    'provider' => $provider,
                    'provider_reference' => $providerReference,
                    'bank_reference' => $this->extractBankReference($provider, $payload),
                ]
            );

            $intent->status = 'completed';
            $intent->provider_reference = $providerReference ?? $intent->provider_reference;
            $intent->bank_reference = $this->extractBankReference($provider, $payload) ?? $intent->bank_reference;
            $intent->metadata = array_merge($intent->metadata ?? [], ['webhook_payload' => $payload]);
            $intent->completed_at = now();
            $intent->save();

            return $intent;
        });
    }

    private function isValidSignature(string $provider, array $payload, string $rawBody, array $headers): bool
    {
        return match ($provider) {
            'flutterwave' => $this->isValidFlutterwaveSignature($rawBody, $headers),
            'nomba' => $this->isValidNombaSignature($payload, $headers),
            default => false,
        };
    }

    private function isValidFlutterwaveSignature(string $rawBody, array $headers): bool
    {
        $secret = (string) config('services.flutterwave.webhook_secret', '');
        if ($secret === '') {
            return false;
        }

        $verifHash = (string) ($headers['verif-hash'] ?? '');
        if ($verifHash !== '') {
            return hash_equals($secret, $verifHash);
        }

        $signature = (string) ($headers['flutterwave-signature'] ?? '');
        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals(strtolower($expected), strtolower($signature));
    }

    private function isValidNombaSignature(array $payload, array $headers): bool
    {
        $secret = (string) config('services.nomba.webhook_secret', '');
        $signature = (string) ($headers['nomba-signature'] ?? $headers['x-nomba-signature'] ?? '');
        $timestamp = (string) ($headers['x-nomba-timestamp'] ?? '');
        if ($secret === '' || $signature === '' || $timestamp === '') {
            return false;
        }

        $amount = (string) data_get($payload, 'data.transaction.amount');
        $merchantRef = (string) data_get($payload, 'data.transaction.merchant_ref');
        $event = (string) ($payload['event'] ?? '');

        if ($amount === '' || $merchantRef === '' || $event === '') {
            return false;
        }

        $message = "{$timestamp}{$event}{$amount}{$merchantRef}";
        $expected = hash_hmac('sha256', $message, $secret);
        return hash_equals(strtolower($expected), strtolower($signature));
    }

    private function extractIntentReference(string $provider, array $payload): string
    {
        return match ($provider) {
            'flutterwave' => (string) (data_get($payload, 'data.tx_ref') ?? $payload['reference'] ?? ''),
            'nomba' => (string) (data_get($payload, 'data.transaction.merchant_ref') ?? $payload['reference'] ?? ''),
            default => '',
        };
    }

    private function extractAmount(string $provider, array $payload): string
    {
        return match ($provider) {
            'flutterwave' => (string) (data_get($payload, 'data.amount') ?? $payload['amount'] ?? '0'),
            'nomba' => (string) (data_get($payload, 'data.transaction.amount') ?? $payload['amount'] ?? '0'),
            default => '0',
        };
    }

    private function extractProviderReference(string $provider, array $payload): ?string
    {
        $value = match ($provider) {
            'flutterwave' => (string) (data_get($payload, 'data.id') ?? data_get($payload, 'data.flw_ref') ?? ''),
            'nomba' => (string) (data_get($payload, 'data.transaction.reference') ?? data_get($payload, 'data.transaction.id') ?? ''),
            default => '',
        };

        return $value !== '' ? $value : null;
    }

    private function extractBankReference(string $provider, array $payload): ?string
    {
        $value = match ($provider) {
            'flutterwave' => (string) (data_get($payload, 'data.payment_type') ?? ''),
            'nomba' => (string) (data_get($payload, 'data.transaction.payment_method') ?? ''),
            default => '',
        };

        return $value !== '' ? $value : null;
    }
}
