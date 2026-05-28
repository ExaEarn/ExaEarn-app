<?php

declare(strict_types=1);

namespace App\Services\Kyc\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmileKycProvider implements KycProviderDriver
{
    public function verifyDocument(array $payload): array
    {
        $data = $this->post('/v1/kyc/document', $payload);

        return [
            'success' => $this->bool($data, ['success', 'status', 'ok']),
            'provider_id' => $this->string($data, ['provider_id', 'id', 'job_id']),
            'valid_id' => $this->bool($data, ['valid_id', 'is_valid', 'document_valid']),
            'age_passed' => $this->bool($data, ['age_passed', 'is_of_age', 'age_check_passed']),
        ];
    }

    public function verifyFace(array $payload): array
    {
        $data = $this->post('/v1/kyc/face', $payload);

        return [
            'success' => $this->bool($data, ['success', 'status', 'ok']),
            'face_match' => $this->bool($data, ['face_match', 'selfie_match', 'matched']),
        ];
    }

    public function checkDuplicate(array $payload): array
    {
        $data = $this->post('/v1/kyc/duplicate', $payload);

        return [
            'success' => $this->bool($data, ['success', 'status', 'ok']),
            'duplicate' => $this->bool($data, ['duplicate', 'is_duplicate', 'matched_existing']),
        ];
    }

    public function checkCountry(array $payload): array
    {
        $data = $this->post('/v1/kyc/country', $payload);

        return [
            'success' => $this->bool($data, ['success', 'status', 'ok']),
            'country' => strtoupper($this->string($data, ['country', 'country_code'])),
            'blacklisted' => $this->bool($data, ['blacklisted', 'is_blacklisted', 'blocked_country']),
        ];
    }

    private function post(string $path, array $payload): array
    {
        $cfg = (array) config('kyc.providers.smile', []);
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $apiKey = (string) ($cfg['api_key'] ?? '');

        if ($baseUrl === '' || $apiKey === '') {
            throw new RuntimeException('Smile KYC provider not configured.');
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->withToken($apiKey)
            ->post($baseUrl . $path, $payload)
            ->throw();

        return (array) $response->json();
    }

    private function bool(array $data, array $keys): bool
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);
            if ($value !== null) {
                return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
            }
        }

        return false;
    }

    private function string(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }
}
