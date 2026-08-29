<?php
declare(strict_types=1);
namespace App\Services;
final class DashboardExperienceRegistry
{
    public const KEYS = ['trade_invest', 'earn_grow', 'pay_spend', 'learn_build', 'explore_opportunities', 'play_earn', 'crypto_exchange', 'exaai', 'earn', 'giftcards', 'games', 'exaskills', 'crowdfund', 'nft_marketplace', 'agritech'];
    public function all(): array
    {
        return [
            'trade_invest' => $this->item('Trade & Invest', 'Prioritize markets, buying, trading, and intelligent tools.', 'trade', 'available'),
            'earn_grow' => $this->item('Earn & Grow', 'Prioritize Earn products, rewards, and long-term opportunities.', 'staking', 'available'),
            'pay_spend' => $this->item('Pay & Spend', 'Prioritize funding, transfers, ExaPay, and ExaCard.', 'addFunds', 'available'),
            'learn_build' => $this->item('Learn & Build', 'Prioritize education, guided discovery, and developer tools.', 'edtech', 'available'),
            'explore_opportunities' => $this->item('Explore Opportunities', 'Prioritize the broader ExaEarn ecosystem.', 'more', 'available'),
            'play_earn' => $this->item('Play & Earn', 'Prioritize games, rewards, and eligible earning experiences.', 'game', 'available'),
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
