<?php

declare(strict_types=1);

namespace App\Services;

class FlightFairnessService
{
    public function generateServerSeed(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hashServerSeed(string $serverSeed): string
    {
        return hash('sha256', $serverSeed);
    }

    public function generateCrashMultiplier(string $serverSeed, string $clientSeed, int $nonce): string
    {
        $hash = hash_hmac('sha256', sprintf('%s:%d', $clientSeed, $nonce), $serverSeed);
        $slice = substr($hash, 0, 13);
        $int = hexdec($slice);
        $ratio = $int / 4503599627370496;
        $ratio = min(max($ratio, 0.000000000001), 0.999999999999);
        $raw = (99 / (1 - $ratio)) / 100;
        $multiplier = max(1.00, floor($raw * 100) / 100);

        return number_format($multiplier, 8, '.', '');
    }

    public function verify(string $serverSeed, string $clientSeed, int $nonce, string $expected): bool
    {
        return bccomp($this->generateCrashMultiplier($serverSeed, $clientSeed, $nonce), $expected, 8) === 0;
    }
}