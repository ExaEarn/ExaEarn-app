<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FlightGameBet;
use App\Models\FlightGameRiskIncident;

class FlightGameAccountClosureService
{
    public function blockers(int $userId): array
    {
        $blockers = [];

        $openBets = FlightGameBet::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['placed', 'cashed_out'])
            ->count();
        if ($openBets > 0) {
            $blockers[] = [
                'product' => 'EXA_FLIGHT',
                'type' => 'UNRESOLVED_GAME_ENTRY',
                'count' => $openBets,
                'message' => 'EXA Flight entries must settle before account closure.',
            ];
        }

        $lockedFunds = FlightGameBet::query()
            ->where('user_id', $userId)
            ->where('mode', 'real')
            ->whereIn('status', ['placed', 'cashed_out'])
            ->whereNotNull('ledger_reference')
            ->count();
        if ($lockedFunds > 0) {
            $blockers[] = [
                'product' => 'EXA_FLIGHT',
                'type' => 'GAME_LOCKED_FUNDS',
                'count' => $lockedFunds,
                'message' => 'EXA Flight locked funds must be released or settled before account closure.',
            ];
        }

        $riskCases = FlightGameRiskIncident::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['OPEN', 'REVIEWING', 'ESCALATED'])
            ->count();
        if ($riskCases > 0) {
            $blockers[] = [
                'product' => 'EXA_FLIGHT',
                'type' => 'GAME_RISK_REVIEW',
                'count' => $riskCases,
                'message' => 'Open EXA Flight risk reviews must be resolved before account closure.',
            ];
        }

        return $blockers;
    }
}
