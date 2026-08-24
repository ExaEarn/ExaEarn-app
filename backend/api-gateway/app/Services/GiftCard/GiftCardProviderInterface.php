<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

interface GiftCardProviderInterface
{
    public function name(): string;

    public function purchase(array $payload): array;

    public function checkOrder(string $providerReference): array;

    public function refund(string $providerReference, array $payload = []): array;

    public function balance(string $currency): array;
}

