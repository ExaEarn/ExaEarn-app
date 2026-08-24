<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Models\Card;
use App\Models\CardCustomer;
use Illuminate\Support\Str;
use RuntimeException;

class FakeCardProvider implements CardProviderInterface
{
    public function name(): string
    {
        return 'fake';
    }

    public function capabilities(): array
    {
        return (array) config('exacard.fake_provider.capabilities', []);
    }

    public function createCustomer(array $payload): array
    {
        return [
            'provider_customer_id' => 'fake_cus_'.Str::lower((string) Str::uuid()),
            'provider_status' => 'ACTIVE',
            'kyc_status' => $payload['kyc_status'] ?? 'VERIFIED',
        ];
    }

    public function issueCard(CardCustomer $customer, array $payload): array
    {
        $type = strtoupper((string) ($payload['type'] ?? 'VIRTUAL'));
        if ($type === 'PHYSICAL' && ! ($this->capabilities()['physical_cards'] ?? false)) {
            throw new RuntimeException('Physical card issuance is not supported by the active provider.');
        }

        return [
            'provider_card_id' => 'fake_card_'.Str::lower((string) Str::uuid()),
            'network' => 'VISA',
            'last_four' => (string) random_int(1000, 9999),
            'expiry_month' => now()->format('m'),
            'expiry_year' => now()->addYears(3)->format('Y'),
            'status' => 'ACTIVE',
            'provider_status' => 'ACTIVE',
        ];
    }

    public function fundCard(Card $card, array $payload): array
    {
        $behavior = strtoupper((string) ($payload['test_behavior'] ?? 'COMPLETED'));
        if ($behavior === 'FAILED') {
            return ['status' => 'FAILED', 'provider_reference' => 'fake_fund_fail_'.Str::uuid(), 'reason' => 'SANDBOX_DECLINE'];
        }
        if ($behavior === 'PENDING') {
            return ['status' => 'PENDING_PROVIDER', 'provider_reference' => 'fake_fund_pending_'.Str::uuid()];
        }
        if (in_array($behavior, ['UNKNOWN', 'TIMEOUT'], true)) {
            return ['status' => 'PROVIDER_UNKNOWN', 'provider_reference' => 'fake_fund_unknown_'.Str::uuid(), 'reason' => 'SANDBOX_UNCERTAIN_STATE'];
        }

        return ['status' => 'COMPLETED', 'provider_reference' => 'fake_fund_'.Str::uuid()];
    }

    public function unloadCard(Card $card, array $payload): array
    {
        return ['status' => 'COMPLETED', 'provider_reference' => 'fake_unload_'.Str::uuid()];
    }

    public function freeze(Card $card, string $reason): array
    {
        return ['status' => 'FROZEN', 'reason' => $reason];
    }

    public function unfreeze(Card $card, string $reason): array
    {
        return ['status' => 'ACTIVE', 'reason' => $reason];
    }

    public function terminate(Card $card, string $reason): array
    {
        return ['status' => 'TERMINATED', 'reason' => $reason];
    }

    public function updateLimits(Card $card, array $limits): array
    {
        return ['status' => 'UPDATED', 'limits' => $limits];
    }

    public function updateControls(Card $card, array $controls): array
    {
        return ['status' => 'UPDATED', 'controls' => $controls];
    }

    public function sensitiveDetailsToken(Card $card): array
    {
        return [
            'token' => 'fake_details_'.Str::uuid(),
            'expires_at' => now()->addMinutes(3)->toISOString(),
            'render_url' => '/api/cards/'.$card->card_uuid.'/secure-details',
        ];
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $signature = $headers['x-exacard-signature'][0] ?? $headers['X-ExaCard-Signature'][0] ?? $headers['x-exacard-signature'] ?? null;
        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, (string) config('exacard.webhook_secret'));
        return hash_equals($expected, $signature);
    }

    public function parseWebhook(string $rawBody, array $headers): array
    {
        $payload = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
        return [
            'event_id' => (string) ($payload['event_id'] ?? $payload['id'] ?? Str::uuid()),
            'event_type' => strtoupper((string) ($payload['event_type'] ?? $payload['type'] ?? 'UNKNOWN')),
            'payload' => $payload,
        ];
    }

    public function health(): array
    {
        return [
            'provider' => $this->name(),
            'mode' => 'SANDBOX',
            'status' => 'HEALTHY',
            'production_issuance' => false,
            'capabilities' => $this->capabilities(),
        ];
    }
}
