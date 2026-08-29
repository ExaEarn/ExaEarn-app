<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\FlightGameBet;
use App\Models\FlightGameRiskIncident;
use Illuminate\Support\Str;
use RuntimeException;

class GameTreasuryRiskService
{
    private const SCALE = 8;

    public function __construct(private readonly FlightGamePolicyService $policy)
    {
    }

    public function evaluateEntry(string $asset, string $stake, string $maxMultiplier, ?int $roundId = null, ?int $userId = null): array
    {
        $settings = $this->policy->settings();
        $asset = strtoupper($asset);
        $stake = $this->fmt($stake);
        $maxMultiplier = $this->fmt($maxMultiplier);
        $potentialPayout = $this->mul($stake, $maxMultiplier);
        $existingExposure = $this->currentExposure($asset, $roundId);
        $postExposure = $this->add($existingExposure, $potentialPayout);
        $treasuryBalance = $this->treasuryBalance($asset);
        $requiredReserve = $this->fmt((string) ($settings['treasury_required_reserve'] ?? '0'));
        $maxRoundLiability = $this->fmt((string) ($settings['max_round_liability'] ?? '10000'));
        $maxPlatformExposure = $this->fmt((string) ($settings['max_platform_exposure'] ?? '25000'));
        $availableRiskCapital = $this->sub($treasuryBalance, $requiredReserve);

        $result = [
            'asset' => $asset,
            'stake' => $stake,
            'potential_payout' => $potentialPayout,
            'existing_round_exposure' => $existingExposure,
            'post_round_exposure' => $postExposure,
            'treasury_balance' => $treasuryBalance,
            'required_reserve' => $requiredReserve,
            'available_risk_capital' => $availableRiskCapital,
            'max_round_liability' => $maxRoundLiability,
            'max_platform_exposure' => $maxPlatformExposure,
            'decision' => 'ALLOW',
            'reason' => null,
        ];

        if (bccomp($postExposure, $maxRoundLiability, self::SCALE) > 0) {
            return $this->reject($result, 'ROUND_LIABILITY_LIMIT', $userId, $roundId);
        }

        if (bccomp($postExposure, $maxPlatformExposure, self::SCALE) > 0) {
            return $this->reject($result, 'PLATFORM_EXPOSURE_LIMIT', $userId, $roundId);
        }

        if (bccomp($postExposure, $availableRiskCapital, self::SCALE) > 0) {
            return $this->reject($result, 'TREASURY_COVERAGE_LIMIT', $userId, $roundId);
        }

        return $result;
    }

    public function currentExposure(string $asset, ?int $roundId = null): string
    {
        $query = FlightGameBet::query()
            ->where('asset', strtoupper($asset))
            ->where('mode', 'real')
            ->where('status', 'placed');

        if ($roundId !== null) {
            $query->where('round_id', $roundId);
        }

        $settings = $this->policy->settings();
        $maxMultiplier = $this->fmt((string) ($settings['max_multiplier'] ?? '1000'));
        $stake = $this->fmt((string) $query->sum('stake'));

        return $this->mul($stake, $maxMultiplier);
    }

    private function reject(array $result, string $reason, ?int $userId, ?int $roundId): array
    {
        $result['decision'] = 'REJECT';
        $result['reason'] = $reason;

        FlightGameRiskIncident::query()->create([
            'incident_uuid' => (string) Str::uuid(),
            'type' => 'TREASURY_RISK',
            'severity' => 'HIGH',
            'status' => 'OPEN',
            'user_id' => $userId,
            'round_id' => $roundId,
            'asset' => $result['asset'],
            'evidence' => $result,
        ]);

        throw new RuntimeException('EXA Flight treasury risk limit rejected this entry.');
    }

    private function treasuryBalance(string $asset): string
    {
        $account = Account::query()
            ->whereNull('user_id')
            ->where('account_type', 'game_treasury')
            ->where('asset', strtoupper($asset))
            ->first();

        return $account ? $this->fmt((string) $account->balance) : '0.00000000';
    }

    private function fmt(string $value): string
    {
        return bcadd($value, '0', self::SCALE);
    }

    private function add(string $left, string $right): string
    {
        return bcadd($left, $right, self::SCALE);
    }

    private function sub(string $left, string $right): string
    {
        return bcsub($left, $right, self::SCALE);
    }

    private function mul(string $left, string $right): string
    {
        return bcmul($left, $right, self::SCALE);
    }
}
