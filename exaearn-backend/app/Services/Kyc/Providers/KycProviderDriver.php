<?php

declare(strict_types=1);

namespace App\Services\Kyc\Providers;

interface KycProviderDriver
{
    public function verifyDocument(array $payload): array;
    public function verifyFace(array $payload): array;
    public function checkDuplicate(array $payload): array;
    public function checkCountry(array $payload): array;
}
