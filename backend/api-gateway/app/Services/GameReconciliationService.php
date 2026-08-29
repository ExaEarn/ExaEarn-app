<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FinanceReconciliationBreak;
use App\Models\FlightGameBet;
use App\Models\LedgerEntry;
use Illuminate\Support\Str;

class GameReconciliationService
{
    public function run(int $lookbackHours = 24): array
    {
        $findings = [];
        $since = now()->subHours($lookbackHours);

        FlightGameBet::query()
            ->with('round')
            ->where('created_at', '>=', $since)
            ->orderBy('id')
            ->get()
            ->each(function (FlightGameBet $bet) use (&$findings): void {
                $this->checkBet($bet, $findings);
            });

        return [
            'status' => $findings === [] ? 'PASS' : 'FAIL',
            'findings' => $findings,
            'checked_at' => now()->toISOString(),
        ];
    }

    private function checkBet(FlightGameBet $bet, array &$findings): void
    {
        if ($bet->mode === 'demo') {
            return;
        }

        if (! $bet->ledger_reference) {
            $findings[] = $this->record('HIGH', 'GAME_ENTRY_MISSING_LEDGER_REFERENCE', $bet, [
                'bet_uuid' => $bet->bet_uuid,
            ]);
        } elseif (! LedgerEntry::query()->where('reference', $bet->ledger_reference)->exists()) {
            $findings[] = $this->record('HIGH', 'GAME_ENTRY_LEDGER_ENTRIES_MISSING', $bet, [
                'ledger_reference' => $bet->ledger_reference,
            ]);
        }

        if ($bet->status === 'placed' && $bet->round && in_array($bet->round->status, ['completed', 'cancelled', 'failed'], true)) {
            $findings[] = $this->record('CRITICAL', 'GAME_BET_UNSETTLED_AFTER_ROUND_END', $bet, [
                'round_uuid' => $bet->round->round_uuid,
                'round_status' => $bet->round->status,
            ]);
        }

        if (in_array($bet->status, ['cashed_out', 'lost', 'refunded'], true) && $bet->settled_at === null) {
            $findings[] = $this->record('MEDIUM', 'GAME_BET_SETTLED_STATUS_WITHOUT_TIMESTAMP', $bet, [
                'status' => $bet->status,
            ]);
        }

        if ($bet->status === 'cashed_out' && bccomp((string) $bet->payout, '0', 8) <= 0) {
            $findings[] = $this->record('HIGH', 'GAME_CASHOUT_WITHOUT_POSITIVE_PAYOUT', $bet, [
                'payout' => (string) $bet->payout,
            ]);
        }
    }

    private function record(string $severity, string $code, FlightGameBet $bet, array $evidence): array
    {
        $break = FinanceReconciliationBreak::query()->firstOrCreate([
            'scope' => 'GAMES_EXA_FLIGHT',
            'code' => $code,
            'subject_type' => 'flight_game_bet',
            'subject_reference' => (string) $bet->bet_uuid,
            'status' => 'OPEN',
        ], [
            'break_uuid' => (string) Str::uuid(),
            'severity' => $severity,
            'message' => 'EXA Flight reconciliation detected '.$code,
            'evidence' => array_merge($evidence, [
                'bet_uuid' => $bet->bet_uuid,
                'round_uuid' => $bet->round?->round_uuid,
                'asset' => $bet->asset,
                'stake' => (string) $bet->stake,
                'status' => $bet->status,
            ]),
        ]);

        return $break->toArray();
    }
}
