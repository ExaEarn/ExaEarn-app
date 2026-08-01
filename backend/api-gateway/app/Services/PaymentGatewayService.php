<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FiatDepositIntent;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentGatewayService
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly FeeTreasuryService $feeTreasuryService,
    ) {
    }

    public function createIntent(int $userId, string $provider, string $currency, string $amount, ?string $intentId = null, array $metadata = []): PaymentIntent
    {
        return PaymentIntent::updateOrCreate(
            ['intent_id' => $intentId ?: (string) Str::uuid()],
            [
                'user_id' => $userId,
                'provider' => strtolower($provider),
                'currency' => strtoupper($currency),
                'amount' => $amount,
                'status' => 'pending',
                'metadata' => array_merge([
                    'payment_instructions' => 'Complete transfer to virtual account and submit reference.',
                    'provider_reference' => null,
                ], $metadata),
            ]
        );
    }

    public function createHostedCheckoutIntent(
        int $userId,
        string $provider,
        string $currency,
        string $amount,
        string $reference,
        array $customer,
        string $redirectUrl,
        array $paymentOptions = ['card'],
        array $metadata = []
    ): array {
        $provider = strtolower($provider);
        $intent = $this->createIntent(
            $userId,
            $provider,
            $currency,
            $amount,
            null,
            array_merge($metadata, [
                'reference' => $reference,
                'fiat_intent_reference' => (string) ($metadata['fiat_intent_reference'] ?? $reference),
            ])
        );

        if (app()->environment(['local', 'testing'])) {
            return [
                'intent' => $intent,
                'provider' => $provider,
                'checkout_url' => rtrim((string) config('app.url', 'http://localhost'), '/') . '/payments/demo/' . $reference,
                'redirect_url' => $redirectUrl,
                'expires_at' => now()->addMinutes(30)->toISOString(),
            ];
        }

        return match ($provider) {
            'flutterwave' => $this->createFlutterwaveCheckout($intent, $customer, $redirectUrl, $paymentOptions),
            default => throw new RuntimeException('Unsupported hosted checkout provider.'),
        };
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

            $intent = $this->resolveIntentByReference($reference);
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

            $bankReference = $this->extractBankReference($provider, $payload);
            $fiatIntentReference = (string) data_get($intent->metadata, 'fiat_intent_reference', '');

            if ($fiatIntentReference !== '') {
                $this->creditFiatDepositIntent($intent, $fiatIntentReference, $provider, $providerReference, $bankReference);
            } else {
                $this->transactionService->recordDeposit(
                    $intent->user_id,
                    $intent->currency,
                    $amount,
                    $reference,
                    null,
                    [
                        'provider' => $provider,
                        'provider_reference' => $providerReference,
                        'bank_reference' => $bankReference,
                    ]
                );
            }

            $intent->status = 'completed';
            $intent->provider_reference = $providerReference ?? $intent->provider_reference;
            $intent->bank_reference = $bankReference ?? $intent->bank_reference;
            $intent->metadata = array_merge($intent->metadata ?? [], ['webhook_payload' => $payload]);
            $intent->completed_at = now();
            $intent->save();

            return $intent;
        });
    }


    private function resolveIntentByReference(string $reference): ?PaymentIntent
    {
        if (Str::isUuid($reference)) {
            return PaymentIntent::query()->where('intent_id', $reference)->lockForUpdate()->first();
        }

        return PaymentIntent::query()
            ->where('metadata->reference', $reference)
            ->orWhere('metadata->fiat_intent_reference', $reference)
            ->lockForUpdate()
            ->first();
    }

    private function createFlutterwaveCheckout(PaymentIntent $intent, array $customer, string $redirectUrl, array $paymentOptions): array
    {
        $secretKey = (string) config('services.flutterwave.secret_key', '');
        $paymentUrl = (string) config('services.flutterwave.payment_url', 'https://api.flutterwave.com/v3/payments');

        if ($secretKey === '') {
            throw new RuntimeException('Flutterwave secret key is not configured.');
        }

        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->post($paymentUrl, [
                'tx_ref' => $intent->intent_id,
                'amount' => (string) $intent->amount,
                'currency' => strtoupper((string) $intent->currency),
                'redirect_url' => $redirectUrl,
                'payment_options' => implode(', ', array_values(array_filter($paymentOptions))),
                'customer' => [
                    'email' => (string) ($customer['email'] ?? ''),
                    'name' => (string) ($customer['name'] ?? 'ExaEarn User'),
                    'phone_number' => (string) ($customer['phone'] ?? ''),
                ],
                'customizations' => [
                    'title' => 'ExaEarn Fiat Deposit',
                    'description' => 'Fund your ExaEarn wallet securely.',
                ],
                'meta' => [
                    'source' => 'fiat_deposit',
                    'intent_id' => $intent->intent_id,
                    'user_id' => $intent->user_id,
                ],
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Flutterwave checkout initialization failed.');
        }

        $link = (string) data_get($response->json(), 'data.link', '');
        if ($link === '') {
            throw new RuntimeException('Flutterwave checkout link was not returned.');
        }

        $intent->metadata = array_merge($intent->metadata ?? [], [
            'checkout_response' => $response->json(),
            'checkout_url' => $link,
        ]);
        $intent->save();

        return [
            'intent' => $intent,
            'provider' => 'flutterwave',
            'checkout_url' => $link,
            'redirect_url' => $redirectUrl,
            'expires_at' => now()->addMinutes(30)->toISOString(),
        ];
    }

    private function creditFiatDepositIntent(PaymentIntent $intent, string $fiatIntentReference, string $provider, ?string $providerReference, ?string $bankReference): void
    {
        $fiatIntent = FiatDepositIntent::query()->where('reference', $fiatIntentReference)->lockForUpdate()->first();

        if (!$fiatIntent) {
            $this->transactionService->recordDeposit(
                $intent->user_id,
                $intent->currency,
                (string) $intent->amount,
                $intent->intent_id,
                null,
                [
                    'provider' => $provider,
                    'provider_reference' => $providerReference,
                    'bank_reference' => $bankReference,
                ]
            );
            return;
        }

        if ((string) $fiatIntent->status === 'credited') {
            return;
        }

        if (in_array((string) $fiatIntent->status, ['cancelled', 'failed', 'expired'], true)) {
            throw new RuntimeException('This fiat deposit intent cannot be credited in its current state.');
        }

        $result = $this->feeTreasuryService->collectFiatDeposit(
            (int) $fiatIntent->user_id,
            (string) $fiatIntent->gross_amount,
            (string) $fiatIntent->currency,
            (string) $fiatIntent->reference,
            [
                'source' => 'payment_gateway_webhook',
                'fiat_intent_reference' => (string) $fiatIntent->reference,
                'method_id' => (string) $fiatIntent->method_id,
                'provider' => $provider,
                'provider_reference' => $providerReference,
                'bank_reference' => $bankReference,
                'payment_intent_id' => $intent->intent_id,
            ]
        );

        Transaction::query()->updateOrCreate(
            ['reference' => (string) $fiatIntent->reference],
            [
                'transaction_id' => (string) $fiatIntent->reference,
                'user_id' => (int) $fiatIntent->user_id,
                'type' => 'deposit',
                'currency' => (string) $fiatIntent->currency,
                'amount' => (string) $fiatIntent->net_amount,
                'fee' => (string) $fiatIntent->fee_amount,
                'status' => 'completed',
                'metadata' => [
                    'source' => 'payment_gateway_webhook',
                    'fiat_intent_reference' => (string) $fiatIntent->reference,
                    'gross_amount' => (string) $fiatIntent->gross_amount,
                    'fee_amount' => (string) $fiatIntent->fee_amount,
                    'method_id' => (string) $fiatIntent->method_id,
                    'provider' => $provider,
                    'provider_reference' => $providerReference,
                    'bank_reference' => $bankReference,
                    'ledger_reference' => $result['ledger_transaction']->reference,
                ],
            ]
        );

        $fiatIntent->status = 'credited';
        $fiatIntent->paid_at = $fiatIntent->paid_at ?: now();
        $fiatIntent->settled_at = now();
        $fiatIntent->metadata = array_merge($fiatIntent->metadata ?? [], [
            'provider' => $provider,
            'provider_reference' => $providerReference,
            'bank_reference' => $bankReference,
            'payment_intent_id' => $intent->intent_id,
        ]);
        $fiatIntent->save();
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
