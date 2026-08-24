<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Models\Card;
use App\Models\CardCustomer;

interface CardProviderInterface
{
    public function name(): string;

    public function capabilities(): array;

    public function createCustomer(array $payload): array;

    public function issueCard(CardCustomer $customer, array $payload): array;

    public function fundCard(Card $card, array $payload): array;

    public function unloadCard(Card $card, array $payload): array;

    public function freeze(Card $card, string $reason): array;

    public function unfreeze(Card $card, string $reason): array;

    public function terminate(Card $card, string $reason): array;

    public function updateLimits(Card $card, array $limits): array;

    public function updateControls(Card $card, array $controls): array;

    public function sensitiveDetailsToken(Card $card): array;

    public function verifyWebhook(string $rawBody, array $headers): bool;

    public function parseWebhook(string $rawBody, array $headers): array;

    public function health(): array;
}
