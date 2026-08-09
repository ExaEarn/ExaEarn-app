<?php
declare(strict_types=1);
namespace App\Services;
final class DashboardExperienceRegistry
{
    public const KEYS = ['crypto_exchange', 'exaai', 'earn', 'giftcards', 'games', 'exaskills', 'crowdfund', 'nft_marketplace', 'agritech'];
    public function all(): array
    {
        return [
            'crypto_exchange' => $this->item('Crypto Exchange', 'Trade spot and futures markets, convert assets, and manage open orders.', 'trade', 'available'),
            'exaai' => $this->item('ExaAI', 'Manage subscriptions, strategies, allocations, sessions, and performance.', 'aiAssistant', 'available'),
            'earn' => $this->item('Earn', 'Discover supported staking products and manage positions and rewards.', 'staking', 'available'),
            'giftcards' => $this->item('Gift Cards', 'Buy supported cards or submit cards for secure settlement.', 'giftcard', 'available'),
            'games' => $this->item('Games', 'Play the live Flight game and review your bet activity.', 'game', 'available'),
            'exaskills' => $this->item('ExaSkills', 'Continue courses, track credentials, and find challenges and opportunities.', 'edtech', 'available'),
            'crowdfund' => $this->item('Crowdfund', 'Discover and support community campaigns.', 'crowdfunding', 'partial'),
            'nft_marketplace' => $this->item('NFT Marketplace', 'Discover collections and manage owned, minted, and listed assets.', 'nftMarketplace', 'available'),
            'agritech' => $this->item('Agritech', 'Explore agricultural projects and follow your project participation.', 'agriculture', 'available'),
        ];
    }
    private function item(string $label, string $description, string $route, string $availability): array { return compact('label', 'description', 'route', 'availability'); }
}
