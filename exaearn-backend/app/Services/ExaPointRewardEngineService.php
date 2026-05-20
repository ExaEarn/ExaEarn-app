<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class ExaPointRewardEngineService
{
    public function __construct(private readonly ExaPointService $exaPointService)
    {
    }

    public function rewardReferralBonus(int $userId, string $amount, string $sourceId, array $metadata = []): array
    {
        return $this->rewardFromSource('referral_bonus', $userId, $amount, $sourceId, 'Referral bonus reward', $metadata);
    }

    public function rewardStaking(int $userId, string $amount, string $sourceId, bool $lock = true, array $metadata = []): array
    {
        $description = $lock ? 'Staking reward (locked)' : 'Staking reward';
        return $this->rewardFromSource('staking_rewards', $userId, $amount, $sourceId, $description, $metadata, $lock);
    }

    public function rewardLearningTask(int $userId, string $amount, string $sourceId, array $metadata = []): array
    {
        return $this->rewardFromSource('learning_tasks', $userId, $amount, $sourceId, 'Learning task reward', $metadata);
    }

    public function rewardPromotion(int $userId, string $amount, string $sourceId, array $metadata = []): array
    {
        return $this->rewardFromSource('promotions', $userId, $amount, $sourceId, 'Promotional reward', $metadata);
    }

    public function awardFromActivity(int $userId, string $activityType, string $activityValue, array $context = []): array
    {
        $amount = isset($context['reward_amount_override']) ? (string) $context['reward_amount_override'] : $activityValue;
        if ($this->compare($amount, '0') <= 0) {
            throw new RuntimeException('Computed reward amount must be greater than zero.');
        }

        $sourceId = (string) ($context['activity_key'] ?? $context['transaction_id'] ?? $activityType . ':' . now()->timestamp);
        $meta = array_merge($context, [
            'activity_type' => $activityType,
            'activity_value' => $activityValue,
        ]);

        return match ($activityType) {
            'referral_activity' => $this->rewardReferralBonus($userId, $amount, $sourceId, $meta),
            'staking_participation' => $this->rewardStaking($userId, $amount, $sourceId, true, $meta),
            'education_completion', 'daily_check_in' => $this->rewardLearningTask($userId, $amount, $sourceId, $meta),
            default => $this->rewardPromotion($userId, $amount, $sourceId, $meta),
        };
    }

    private function rewardFromSource(
        string $source,
        int $userId,
        string $amount,
        string $sourceId,
        string $description,
        array $metadata = [],
        bool $lock = false,
    ): array {
        $this->guardAmount($amount);

        $reference = $this->sourceReference($source, $userId, $sourceId);
        $result = $this->exaPointService->earn($userId, $amount, $reference, $description, array_merge($metadata, [
            'source' => $source,
            'source_id' => $sourceId,
        ]));

        if ($lock) {
            $lockReference = $reference . ':lock';
            return $this->exaPointService->lock($userId, $amount, $lockReference, 'Auto lock for staking reward', [
                'source' => $source,
                'source_id' => $sourceId,
                'locked_from_reference' => $reference,
            ]);
        }

        return $result;
    }

    private function sourceReference(string $source, int $userId, string $sourceId): string
    {
        return sprintf('exapoint:%s:%d:%s', $source, $userId, md5($sourceId));
    }

    private function guardAmount(string $amount): void
    {
        if ($this->compare($amount, '0') <= 0) {
            throw new RuntimeException('Reward amount must be greater than zero.');
        }
    }

    private function compare(string $left, string $right): int
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, 8);
        }

        $leftFloat = (float) $left;
        $rightFloat = (float) $right;

        return $leftFloat < $rightFloat ? -1 : ($leftFloat > $rightFloat ? 1 : 0);
    }
}

