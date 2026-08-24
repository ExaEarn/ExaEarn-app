<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SecurityRelatedAccount;
use App\Models\User;
use Illuminate\Support\Str;

class MarketSurveillanceService
{
    public function __construct(
        private readonly SecuritySignalService $signals,
        private readonly SecurityCaseService $cases,
    ) {
    }

    public function linkAccounts(User $primary, User $related, string $type, array $evidence, string $confidence = '0.7500'): SecurityRelatedAccount
    {
        return SecurityRelatedAccount::query()->updateOrCreate([
            'primary_user_id' => $primary->id,
            'related_user_id' => $related->id,
            'relationship_type' => strtoupper($type),
        ], [
            'link_uuid' => (string) (SecurityRelatedAccount::query()->where('primary_user_id', $primary->id)->where('related_user_id', $related->id)->where('relationship_type', strtoupper($type))->value('link_uuid') ?: Str::uuid()),
            'confidence' => $confidence,
            'evidence' => $evidence,
            'status' => 'ACTIVE',
        ]);
    }

    public function detectSelfTrade(User $buyer, User $seller, string $symbol, array $trade): array
    {
        $related = $buyer->id === $seller->id || SecurityRelatedAccount::query()
            ->where('status', 'ACTIVE')
            ->where('primary_user_id', $buyer->id)
            ->where('related_user_id', $seller->id)
            ->exists();

        if (! $related) {
            return ['status' => 'CLEAR'];
        }

        $signal = $this->signals->record('SELF_TRADE', 'MARKET_SURVEILLANCE', 'USER', $buyer->id, 'HIGH', ['symbol' => $symbol, 'trade' => $trade, 'seller_id' => $seller->id], '0.9500', 86400);
        $case = $this->cases->create('MARKET_MANIPULATION', 'HIGH', 'USER', $buyer->id, ['signals' => [$signal->signal_uuid], 'symbol' => $symbol, 'policy' => 'STP_REVIEW']);

        return ['status' => 'CASE_CREATED', 'case_uuid' => $case->case_uuid, 'signal_uuid' => $signal->signal_uuid];
    }

    public function detectPattern(string $pattern, User $user, array $evidence): array
    {
        $type = strtoupper($pattern);
        $severity = in_array($type, ['WASH_TRADING', 'SPOOFING_SIGNAL', 'LAYERING_SIGNAL'], true) ? 'HIGH' : 'MEDIUM';
        $signal = $this->signals->record($type, 'MARKET_SURVEILLANCE', 'USER', $user->id, $severity, $evidence, '0.8000', 86400);
        $case = $this->cases->create('MARKET_MANIPULATION', $severity, 'USER', $user->id, ['signals' => [$signal->signal_uuid], 'pattern' => $type, 'evidence' => $evidence]);

        return ['status' => 'CASE_CREATED', 'case_uuid' => $case->case_uuid];
    }
}
