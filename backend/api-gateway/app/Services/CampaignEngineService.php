<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

class CampaignEngineService
{
    public function generate(array $input): array
    {
        $type = strtoupper((string) ($input['type'] ?? ''));

        return match ($type) {
            'NFT_MINTED' => $this->fromNftMinted($input),
            'USER_BEHAVIOR' => $this->fromUserBehavior($input),
            'PARTNERSHIP' => $this->fromPartnership($input),
            default => throw new InvalidArgumentException('Unsupported campaign input type.'),
        };
    }

    private function fromNftMinted(array $input): array
    {
        $count = (int) ($input['count'] ?? 0);
        $timeframe = (string) ($input['timeframe'] ?? '24h');

        return [
            'campaign_title' => 'NFT Earning Surge - Activate Now',
            'campaign_message' => "{$count}+ earning NFTs were minted in {$timeframe}. Enter now to unlock yield, fee savings, and faster financial access.",
            'user_action_required' => 'Mint a financial NFT or upgrade an existing one now',
            'reward_benefit' => 'Higher earning potential and lower platform fees',
            'urgency_trigger' => "Momentum is rising this {$timeframe}; early entrants capture better upside",
            'slider_update' => "{$count}+ financial NFTs minted - earning demand is climbing",
            'notification_message' => 'NFT momentum is live. Mint or upgrade now to increase returns.',
        ];
    }

    private function fromUserBehavior(array $input): array
    {
        $action = strtolower((string) ($input['action'] ?? 'activity'));
        $trend = strtolower((string) ($input['trend'] ?? 'increasing'));

        $actionLabel = match ($action) {
            'staking' => 'Staking demand is rising',
            'withdrawal' => 'Withdrawal activity is accelerating',
            'trading' => 'Trading participation is increasing',
            default => 'Platform activity is building',
        };

        return [
            'campaign_title' => 'Market Momentum - Take Position',
            'campaign_message' => "{$actionLabel}. Users acting now are improving yield and execution speed.",
            'user_action_required' => "Open {$action} flow and complete one transaction today",
            'reward_benefit' => 'Greater earnings consistency and stronger account performance',
            'urgency_trigger' => "Current trend is {$trend}; delaying may reduce upside timing",
            'slider_update' => ucfirst($action) . ' momentum is ' . $trend . ' - optimize your position now',
            'notification_message' => ucfirst($action) . " trend is {$trend}. Complete your next action to capture value.",
        ];
    }

    private function fromPartnership(array $input): array
    {
        $status = strtoupper((string) ($input['status'] ?? ''));
        $impact = (string) ($input['impact'] ?? 'improved platform efficiency');
        $benefit = (string) ($input['benefit_to_users'] ?? 'better transaction experience');
        $partner = trim((string) ($input['partner_name'] ?? ''));

        if ($status !== 'ANNOUNCED') {
            throw new InvalidArgumentException('Only announced strategic updates are allowed for campaigns.');
        }

        $partnerPrefix = $partner !== '' ? $partner . ' integration: ' : '';

        return [
            'campaign_title' => 'Strategic Upgrade - User Advantage Live',
            'campaign_message' => $partnerPrefix . "{$impact}. This means {$benefit} for your account right now.",
            'user_action_required' => 'Use the updated flow now to capture the new performance advantage',
            'reward_benefit' => $benefit,
            'urgency_trigger' => 'Now live; early users benefit first from execution improvements',
            'slider_update' => 'Strategic upgrade active - faster, smoother financial actions now available',
            'notification_message' => 'New strategic upgrade is live. Activate it now for immediate account benefit.',
        ];
    }
}
