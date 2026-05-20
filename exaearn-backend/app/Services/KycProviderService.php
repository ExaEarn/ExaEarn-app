<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Kyc\Providers\KycProviderDriver;
use App\Services\Kyc\Providers\SmileKycProvider;
use App\Services\Kyc\Providers\SumsubKycProvider;

class KycProviderService
{
    public function verifyDocument(array $payload): array { return $this->execute('verifyDocument', $payload); }
    public function verifyFace(array $payload): array { return $this->execute('verifyFace', $payload); }
    public function checkDuplicate(array $payload): array { return $this->execute('checkDuplicate', $payload); }
    public function checkCountry(array $payload): array { return $this->execute('checkCountry', $payload); }

    private function execute(string $method, array $payload): array
    {
        $provider = (string) config('kyc.provider', 'smile');
        $fallback = (string) config('kyc.fallback', 'sumsub');

        try {
            $response = $this->driver($provider)->{$method}($payload);
            $response['provider'] = $provider;
            return $response;
        } catch (\Throwable) {
            $response = $this->driver($fallback)->{$method}($payload);
            $response['provider'] = $fallback;
            return $response;
        }
    }

    private function driver(string $name): KycProviderDriver
    {
        return match ($name) {
            'sumsub' => new SumsubKycProvider(),
            default => new SmileKycProvider(),
        };
    }
}
