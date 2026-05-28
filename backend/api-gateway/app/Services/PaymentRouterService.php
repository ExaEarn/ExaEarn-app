<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PaymentProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use RuntimeException;

class PaymentRouterService
{
    private const AFRICAN_COUNTRY_CODES = [
        'DZ', 'AO', 'BJ', 'BW', 'BF', 'BI', 'CM', 'CV', 'CF', 'TD', 'KM', 'CG', 'CD',
        'CI', 'DJ', 'EG', 'GQ', 'ER', 'SZ', 'ET', 'GA', 'GM', 'GH', 'GN', 'GW', 'KE',
        'LS', 'LR', 'LY', 'MG', 'MW', 'ML', 'MR', 'MU', 'MA', 'MZ', 'NA', 'NE', 'NG',
        'RW', 'ST', 'SN', 'SC', 'SL', 'SO', 'ZA', 'SS', 'SD', 'TZ', 'TG', 'TN', 'UG',
        'ZM', 'ZW', 'EH', 'CV', 'KM', 'BW', 'TD', 'CG', 'CF', 'CV', 'EH',
    ];

    public function getSupportedProviders(): array
    {
        return array_keys($this->getProviderDefinitions());
    }

    public function selectBestProvider(string $countryCode, string $currency, string $amount, ?string $requestedProvider = null): array
    {
        $country = strtoupper(trim($countryCode ?: 'NG'));
        $providers = $this->getActiveProviders();

        if ($requestedProvider !== null && $requestedProvider !== '') {
            $providerKey = strtolower($requestedProvider);
            $provider = $providers->get($providerKey);
            if (!$provider) {
                throw new RuntimeException(sprintf('Requested payment provider "%s" is not available.', $requestedProvider));
            }

            return $this->buildQuote($provider, $currency, $amount, $country, $providers);
        }

        $eligibleProviders = $this->getEligibleProvidersForCountry($country, $currency, $providers);

        if ($eligibleProviders->isEmpty()) {
            $eligibleProviders = $providers;
        }

        $quotes = $eligibleProviders->map(fn (array $provider): array => $this->buildQuote($provider, $currency, $amount, $country, $providers));
        $selected = $quotes->sortBy([['total_fee', 'asc'], ['reliability', 'desc'], ['priority', 'asc']])->values()->first();

        if ($selected === null) {
            throw new RuntimeException('No payment provider is available.');
        }

        return $selected;
    }

    public function getFallbackProviders(string $countryCode, string $currency): array
    {
        $providers = $this->getActiveProviders();
        $eligible = $this->getEligibleProvidersForCountry(strtoupper(trim($countryCode ?: 'NG')), $currency, $providers);
        if ($eligible->isEmpty()) {
            $eligible = $providers;
        }

        return $eligible->sortBy([['priority', 'asc'], ['reliability', 'desc']])->keys()->all();
    }

    private function getActiveProviders(): Collection
    {
        $dbProviders = PaymentProvider::query()->where('status', 'active')->get();
        if ($dbProviders->isNotEmpty()) {
            return $dbProviders->mapWithKeys(fn (PaymentProvider $provider) => [strtolower($provider->code) => array_merge($provider->toArray(), ['code' => strtolower($provider->code))])]);
        }

        return collect($this->getProviderDefinitions())->filter(fn (array $provider) => $provider['status'] === 'active');
    }

    private function buildQuote(array $provider, string $currency, string $amount, string $country, Collection $providers): array
    {
        $providerFee = $this->calculateProviderFee($provider, $amount);
        $markupFee = $this->calculateMarkupFee($amount);
        $totalFee = $this->calculateTotalFee($providerFee, $markupFee);
        $netAmount = $this->calculateNetAmount($amount, $totalFee);

        return [
            'provider' => strtolower($provider['code']),
            'provider_name' => $provider['name'],
            'currency' => strtoupper($currency),
            'country' => $country,
            'amount' => $this->normalizeAmount($amount),
            'provider_fee' => $providerFee,
            'markup_fee' => $markupFee,
            'total_fee' => $totalFee,
            'net_amount' => $netAmount,
            'priority' => $provider['priority'],
            'reliability' => $provider['reliability'],
            'fallback_sequence' => $this->getFallbackProviders($country, $currency),
            'metadata' => $provider['metadata'] ?? [],
        ];
    }

    private function getEligibleProvidersForCountry(string $country, string $currency, Collection $providers): Collection
    {
        if ($country === 'ZA') {
            return $providers->filter(fn (array $provider): bool => in_array(strtolower($provider['code']), ['ozow', 'paystack'], true));
        }

        if ($country === 'NG') {
            return $providers->filter(fn (array $provider): bool => in_array(strtolower($provider['code']), ['nomba', 'monnify'], true));
        }

        if ($this->isAfricanCountry($country)) {
            return $providers->filter(fn (array $provider): bool => strtolower($provider['code']) === 'flutterwave');
        }

        return $providers->filter(fn (array $provider): bool => strtolower($provider['code']) === 'paystack');
    }

    private function isAfricanCountry(string $country): bool
    {
        return in_array($country, self::AFRICAN_COUNTRY_CODES, true);
    }

    private function getProviderDefinitions(): array
    {
        $defaults = config('payments.providers', []);
        return array_map(
            static fn (string $code, array $provider): array => array_merge([
                'code' => strtolower($code),
                'name' => ucfirst($code),
                'countries' => [],
                'currencies' => [],
                'fee_percentage' => 0.0,
                'flat_fee' => 0.0,
                'status' => 'inactive',
                'priority' => 100,
                'reliability' => 0.90,
                'global' => false,
                'metadata' => [],
            ], $provider),
            array_keys($defaults),
            $defaults
        );
    }

    private function calculateProviderFee(array $provider, string $amount): string
    {
        return bcadd(bcmul($this->normalizeAmount($amount), (string) $provider['fee_percentage'], 8), (string) $provider['flat_fee'], 8);
    }

    private function calculateMarkupFee(string $amount): string
    {
        $extraFeePercent = (string) (config('payments.extra_fee_percentage', 0.005));
        $fixedMarkup = (string) (config('payments.fixed_markup', 0));

        return bcadd(bcmul($this->normalizeAmount($amount), $extraFeePercent, 8), $fixedMarkup, 8);
    }

    private function calculateTotalFee(string $providerFee, string $markupFee): string
    {
        return bcadd($providerFee, $markupFee, 8);
    }

    private function calculateNetAmount(string $amount, string $totalFee): string
    {
        return bcsub($this->normalizeAmount($amount), $totalFee, 8);
    }

    private function normalizeAmount(string $amount): string
    {
        return trim((string) $amount) === '' ? '0' : (string) bcmul(trim((string) $amount), '1', 8);
    }
}
