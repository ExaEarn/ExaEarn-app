<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Models\Card;
use App\Models\User;
use App\Services\CompliancePolicyService;
use App\Services\SecurityRiskEngine;
use RuntimeException;

class CardEligibilityService
{
    public function __construct(
        private readonly CardProductService $products,
        private readonly CompliancePolicyService $compliance,
        private readonly SecurityRiskEngine $security,
    ) {
    }

    public function assertIssueAllowed(User $user, string $productCode): array
    {
        $product = $this->products->find($productCode);
        if (! ($product['enabled'] ?? false)) {
            throw new RuntimeException('This ExaCard product is not enabled.');
        }

        if (($product['type'] ?? '') === 'PHYSICAL' && ! (bool) config('exacard.production_issuance_enabled', false)) {
            throw new RuntimeException('Physical ExaCard issuance is disabled until a live provider is configured.');
        }

        $country = strtoupper((string) ($user->verified_country ?? $user->residence_country ?? ''));
        if ($country !== '' && ! in_array($country, array_map('strtoupper', (array) ($product['countries'] ?? [])), true)) {
            throw new RuntimeException('This ExaCard product is not available in your jurisdiction.');
        }

        if ((int) ($user->kyc_level ?? 0) < (int) ($product['minimum_kyc_level'] ?? 0)) {
            throw new RuntimeException('Complete the required identity verification level before issuing this card.');
        }

        if (strtoupper((string) ($user->account_status ?? 'FULLY_ACTIVE')) !== 'FULLY_ACTIVE') {
            throw new RuntimeException('Account status does not allow ExaCard issuance.');
        }

        $count = Card::query()
            ->where('user_id', $user->id)
            ->where('card_product', $product['product_code'])
            ->whereNotIn('status', ['TERMINATED', 'CANCELLED'])
            ->count();
        if ($count >= (int) ($product['maximum_cards_user'] ?? 1)) {
            throw new RuntimeException('Card limit reached for this product.');
        }

        $this->compliance->assertAllowed($user, 'EXACARD', [
            'action' => 'ISSUE_CARD',
            'jurisdiction' => $country ?: null,
            'product' => $product,
        ]);

        $risk = $this->security->evaluate('USER', $user->id, 'CARD_ISSUE', ['product_code' => $product['product_code']]);
        if (in_array($risk['decision'] ?? 'ALLOW', ['BLOCK', 'EMERGENCY_LOCK', 'TEMPORARY_HOLD'], true)) {
            throw new RuntimeException('Security risk controls prevented card issuance.');
        }

        return ['product' => $product, 'risk' => $risk, 'country' => $country ?: null];
    }

    public function assertCardActionAllowed(User $user, Card $card, string $action): void
    {
        if ((int) $card->user_id !== (int) $user->id) {
            throw new RuntimeException('Card does not belong to this user.');
        }

        $this->compliance->assertAllowed($user, 'EXACARD', ['action' => strtoupper($action)]);
        $risk = $this->security->evaluate('USER', $user->id, strtoupper('CARD_'.$action), ['card_uuid' => $card->card_uuid]);
        if (in_array($risk['decision'] ?? 'ALLOW', ['BLOCK', 'EMERGENCY_LOCK', 'TEMPORARY_HOLD'], true)) {
            throw new RuntimeException('Security risk controls prevented this card action.');
        }
    }
}
