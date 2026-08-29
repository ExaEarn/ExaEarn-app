<?php

declare(strict_types=1);

namespace App\Services;

final class ExperienceRecommendationService
{
    public const EXPERIENCES = ['new', 'intermediate', 'experienced'];
    public const GOALS = ['buy_trade', 'send_pay', 'grow_assets', 'trade_smarter', 'p2p', 'explore'];
    public const INTERESTS = ['trading', 'exaai', 'earn', 'payments', 'p2p', 'exacard', 'copy_trading', 'learning', 'rewards', 'ecosystem'];
    public const MODES = ['lite', 'pro'];

    public function recommend(string $experience, string $goal, array $interests): array
    {
        $pro = 0; $lite = 0; $reasons = [];
        if ($experience === 'experienced') { $pro += 2; $reasons[] = 'EXPERIENCED_USER'; }
        if ($experience === 'new') { $lite += 2; $reasons[] = 'NEW_TO_CRYPTO'; }
        if ($goal === 'trade_smarter') { $pro += 4; $reasons[] = 'ADVANCED_TRADING_GOAL'; }
        if ($goal === 'buy_trade') { $pro += 1; $reasons[] = 'MARKET_GOAL'; }
        if (in_array($goal, ['send_pay', 'grow_assets', 'p2p', 'explore'], true)) { $lite += 3; $reasons[] = 'FOCUSED_NON_ADVANCED_GOAL'; }
        foreach ($interests as $interest) {
            if (in_array($interest, ['exaai', 'copy_trading'], true)) $pro += 2;
            if (in_array($interest, ['payments', 'p2p', 'exacard', 'earn', 'rewards', 'learning', 'ecosystem'], true)) $lite += 1;
        }
        if (in_array('trading', $interests, true)) $pro += 1;
        $mode = $pro > $lite ? 'pro' : 'lite';
        return ['recommended_mode' => $mode, 'reason_codes' => array_values(array_unique($reasons)), 'scores' => ['lite' => $lite, 'pro' => $pro]];
    }

    public function inferInterests(string $goal): array
    {
        return match ($goal) {
            'buy_trade' => ['trading'], 'trade_smarter' => ['trading', 'exaai'], 'send_pay' => ['payments'],
            'grow_assets' => ['earn'], 'p2p' => ['p2p'], default => ['ecosystem'],
        };
    }

    public function legacyIntent(string $goal): string
    {
        return match ($goal) {
            'buy_trade', 'trade_smarter' => 'trade_invest', 'send_pay', 'p2p' => 'pay_spend',
            'grow_assets' => 'earn_grow', default => 'explore_opportunities',
        };
    }
}
