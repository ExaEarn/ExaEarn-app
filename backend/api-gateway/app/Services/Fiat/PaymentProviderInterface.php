<?php

declare(strict_types=1);

namespace App\Services\Fiat;

interface PaymentProviderInterface
{
    public function key(): string;
    public function state(): string;
    public function capabilities(): array;
    public function healthCheck(): array;
    public function listBanks(string $country, string $currency): array;
    public function verifyAccount(string $country, string $currency, string $bankCode, string $accountNumber): array;
    public function createVirtualAccount(int $userId, string $currency, string $country, string $reference): array;
    public function verifyWebhook(array $payload, string $rawBody, array $headers): bool;
    public function normalizeWebhook(array $payload): array;
    public function initiateTransfer(array $payload): array;
    public function verifyTransfer(string $reference): array;
    public function getBalance(string $currency): string;
    public function getTransferFee(string $currency, string $amount): string;
}
