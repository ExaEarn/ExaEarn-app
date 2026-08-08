<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FlightGameAuditLog;
use App\Models\FlightGameBet;
use App\Models\FlightGameRound;
use App\Models\FlightGameSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FlightGameService
{
    private const SCALE = 8;
    private const FAIRNESS_VERSION = 'exa-flight-v1';
    private const STREAM_CHANNEL = 'exaearn.game.flight';

    public function __construct(
        private readonly FlightFairnessService $fairness,
        private readonly LedgerService $ledger,
        private readonly RealtimeStreamService $stream,
    ) {
    }

    public function tick(): array
    {
        $round = $this->ensureCurrentRound();
        $this->advanceRound($round);
        $round->refresh();

        return [
            'server_time' => now()->toIso8601String(),
            'round' => $this->transformRound($round),
            'history' => $this->history(10),
        ];
    }

    public function state(?User $user = null): array
    {
        $round = $this->ensureCurrentRound();

        return [
            'server_time' => now()->toIso8601String(),
            'round' => $this->transformRound($round),
            'history' => $this->history(),
            'live_bets' => $this->liveBets($round->id),
            'my_bets' => $user ? $this->myBets($user->id, 12) : [],
            'settings' => $this->publicSettings(),
        ];
    }

    public function adminSummary(): array
    {
        $settings = $this->settings();
        $activeRound = FlightGameRound::query()->whereIn('status', ['betting', 'running'])->latest('round_number')->first();

        return [
            'settings' => $settings,
            'active_round' => $activeRound ? $this->transformRound($activeRound) : null,
            'totals' => [
                'rounds_played' => FlightGameRound::query()->count(),
                'total_wagered' => $this->fmt((string) FlightGameBet::query()->sum('stake')),
                'total_payouts' => $this->fmt((string) FlightGameBet::query()->sum('payout')),
                'gross_gaming_revenue' => $this->sub(
                    $this->fmt((string) FlightGameBet::query()->sum('stake')),
                    $this->fmt((string) FlightGameBet::query()->sum('payout'))
                ),
                'unique_players' => FlightGameBet::query()->distinct('user_id')->count('user_id'),
                'active_players' => FlightGameBet::query()->whereHas('round', fn ($query) => $query->whereIn('status', ['betting', 'running']))->distinct('user_id')->count('user_id'),
            ],
            'exposure_by_asset' => FlightGameBet::query()
                ->selectRaw('asset, COALESCE(SUM(payout),0) as payouts, COALESCE(SUM(stake),0) as stakes')
                ->groupBy('asset')
                ->get()
                ->map(fn ($row) => [
                    'asset' => $row->asset,
                    'stakes' => $this->fmt((string) $row->stakes),
                    'payouts' => $this->fmt((string) $row->payouts),
                    'liability' => $this->sub($this->fmt((string) $row->payouts), $this->fmt((string) $row->stakes)),
                ])
                ->all(),
        ];
    }

    public function updateSettings(array $settings, ?int $updatedBy = null): array
    {
        $allowed = ['enabled_assets', 'default_asset', 'min_stake', 'max_stake', 'max_multiplier', 'betting_window_seconds', 'growth_rate', 'public_seed'];

        DB::transaction(function () use ($allowed, $settings, $updatedBy): void {
            foreach ($settings as $key => $value) {
                if (!in_array((string) $key, $allowed, true)) {
                    continue;
                }

                FlightGameSetting::query()->updateOrCreate(
                    ['key' => (string) $key],
                    ['value' => $value, 'updated_by' => $updatedBy]
                );
            }
        });

        $this->audit($updatedBy, 'flight.settings_updated', null, null, ['keys' => array_keys($settings)]);

        return $this->adminSummary();
    }

    public function myBets(int $userId, int $limit = 20): array
    {
        return FlightGameBet::query()
            ->with('round')
            ->where('user_id', $userId)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (FlightGameBet $bet): array => $this->transformBet($bet, true))
            ->all();
    }

    public function history(int $limit = 20): array
    {
        return FlightGameRound::query()
            ->whereNotNull('settled_at')
            ->latest('round_number')
            ->limit($limit)
            ->get()
            ->map(fn (FlightGameRound $round): array => $this->transformRound($round, false, true, false))
            ->all();
    }

    public function fairness(string $roundUuid): array
    {
        $round = FlightGameRound::query()->where('round_uuid', $roundUuid)->firstOrFail();

        return [
            'round_uuid' => $round->round_uuid,
            'round_number' => $round->round_number,
            'fairness_version' => $round->fairness_version,
            'server_seed_hash' => $round->server_seed_hash,
            'server_seed' => $round->server_seed,
            'client_seed' => $round->client_seed,
            'nonce' => $round->nonce,
            'crash_multiplier' => number_format((float) $round->crash_multiplier, 8, '.', ''),
            'verified' => $round->server_seed
                ? $this->fairness->verify((string) $round->server_seed, (string) $round->client_seed, (int) $round->nonce, (string) $round->crash_multiplier)
                : false,
        ];
    }

    public function placeBet(User $user, array $payload, string $idempotencyKey): array
    {
        if ($idempotencyKey === '') {
            throw new RuntimeException('Missing idempotency key.');
        }

        $round = $this->ensureCurrentRound();
        $settings = $this->settings();
        $asset = strtoupper((string) ($payload['asset'] ?? 'USDT'));
        $stake = $this->fmt((string) ($payload['stake'] ?? '0'));
        $slot = max(1, min(2, (int) ($payload['panel_slot'] ?? 1)));
        $autoCashout = isset($payload['auto_cashout']) && $payload['auto_cashout'] !== null && $payload['auto_cashout'] !== ''
            ? $this->fmt((string) $payload['auto_cashout'])
            : null;

        if ($round->status !== 'betting' || now()->gte($round->betting_closes_at)) {
            throw new RuntimeException('This round is no longer accepting entries.');
        }

        if (!in_array($asset, $settings['enabled_assets'], true)) {
            throw new RuntimeException('This asset is not enabled for EXA Flight.');
        }

        if ($this->compare($stake, $settings['min_stake']) < 0) {
            throw new RuntimeException(sprintf('Minimum entry is %s %s.', $settings['min_stake'], $asset));
        }

        if ($this->compare($stake, $settings['max_stake']) > 0) {
            throw new RuntimeException(sprintf('Maximum entry is %s %s.', $settings['max_stake'], $asset));
        }

        if (!$this->ledger->hasBalance($user->id, $stake, $asset, 'funding')) {
            throw new RuntimeException('Insufficient available balance for this entry.');
        }

        $existing = FlightGameBet::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $this->transformBet($existing->fresh('round'), true);
        }

        $bet = DB::transaction(function () use ($asset, $autoCashout, $idempotencyKey, $round, $slot, $stake, $user): FlightGameBet {
            $lockedRound = FlightGameRound::query()->whereKey($round->id)->lockForUpdate()->firstOrFail();
            if ($lockedRound->status !== 'betting' || now()->gte($lockedRound->betting_closes_at)) {
                throw new RuntimeException('This round is no longer accepting entries.');
            }

            $bet = FlightGameBet::query()->create([
                'bet_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'round_id' => $lockedRound->id,
                'panel_slot' => $slot,
                'mode' => 'real',
                'asset' => $asset,
                'stake' => $stake,
                'auto_cashout' => $autoCashout,
                'status' => 'placed',
                'idempotency_key' => $idempotencyKey,
                'placed_at' => now(),
                'metadata' => [
                    'display_name' => $user->name,
                ],
            ]);

            $reference = 'flight_bet:'.$bet->bet_uuid;
            $userFunding = $this->ledger->getOrCreateAccount($user->id, 'funding', $asset);
            $gameLocked = $this->ledger->getOrCreateAccount($user->id, 'game_locked', $asset);
            $this->ledger->postDoubleEntry($reference, 'EXA Flight bet lock', [
                ['account_id' => $userFunding->id, 'amount' => $this->neg($stake), 'asset' => $asset, 'user_id' => $user->id],
                ['account_id' => $gameLocked->id, 'amount' => $stake, 'asset' => $asset, 'user_id' => $user->id],
            ], 'game_bet', ['bet_uuid' => $bet->bet_uuid, 'round_uuid' => $lockedRound->round_uuid]);

            $bet->ledger_reference = $reference;
            $bet->save();

            $this->audit($user->id, 'flight.bet_placed', $lockedRound->id, $bet->id, [
                'asset' => $asset,
                'stake' => $stake,
                'auto_cashout' => $autoCashout,
            ]);

            return $bet;
        });

        $this->publish('game.bet.accepted', [
            'bet' => $this->transformBet($bet->fresh('round'), false),
            'round' => $this->transformRound($bet->round),
        ]);

        return $this->transformBet($bet->fresh('round'), true);
    }

    public function cashOut(User $user, string $betUuid): array
    {
        $this->ensureCurrentRound();

        return DB::transaction(function () use ($betUuid, $user): array {
            $bet = FlightGameBet::query()->with('round')->where('bet_uuid', $betUuid)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($bet->status !== 'placed') {
                throw new RuntimeException('This entry is no longer eligible for collection.');
            }

            $round = FlightGameRound::query()->whereKey($bet->round_id)->lockForUpdate()->firstOrFail();
            $this->advanceRound($round);
            if ($round->status !== 'running' || now()->gte($round->crashes_at)) {
                throw new RuntimeException('This round ended before the collect request reached the server.');
            }

            $multiplier = $this->currentMultiplier($round, CarbonImmutable::now());
            $this->settleWin($bet, $round, $multiplier, 'manual_cashout');

            return $this->transformBet($bet->fresh('round'), true);
        });
    }

    private function ensureCurrentRound(): FlightGameRound
    {
        $this->reconcileRounds();

        $current = FlightGameRound::query()
            ->whereIn('status', ['betting', 'running'])
            ->orderByDesc('round_number')
            ->first();

        if ($current) {
            return $current;
        }

        return DB::transaction(function (): FlightGameRound {
            DB::statement('select pg_advisory_xact_lock(52450471)');

            $latest = FlightGameRound::query()->orderByDesc('round_number')->lockForUpdate()->first();
            if ($latest && in_array($latest->status, ['betting', 'running'], true)) {
                return $latest;
            }

            return $this->createNextRound($latest);
        });
    }

    private function reconcileRounds(): void
    {
        $rounds = FlightGameRound::query()
            ->whereIn('status', ['betting', 'running'])
            ->orderBy('round_number')
            ->get();

        foreach ($rounds as $round) {
            $this->advanceRound($round);
        }
    }

    private function advanceRound(FlightGameRound $round): void
    {
        $now = CarbonImmutable::now();

        if ($round->status === 'betting' && $now->gte($round->starts_at)) {
            $round->status = 'running';
            $round->save();
            $this->publish('game.round.started', ['round' => $this->transformRound($round)]);
        }

        if ($round->status === 'running') {
            $this->processAutoCashouts($round, $now);
        }

        if (in_array($round->status, ['betting', 'running'], true) && $now->gte($round->crashes_at)) {
            $this->finalizeRound($round);
        }
    }

    private function processAutoCashouts(FlightGameRound $round, CarbonImmutable $now): void
    {
        if ($round->status !== 'running' || $now->gte($round->crashes_at)) {
            return;
        }

        $currentMultiplier = $this->runningMultiplier($round, $now);
        $eligible = FlightGameBet::query()
            ->where('round_id', $round->id)
            ->where('status', 'placed')
            ->whereNotNull('auto_cashout')
            ->lockForUpdate()
            ->get();

        foreach ($eligible as $bet) {
            if ($bet->auto_cashout !== null && $this->compare((string) $bet->auto_cashout, $currentMultiplier) <= 0) {
                $this->settleWin($bet, $round, (string) $bet->auto_cashout, 'auto_cashout');
            }
        }
    }

    private function finalizeRound(FlightGameRound $round): void
    {
        DB::transaction(function () use ($round): void {
            $lockedRound = FlightGameRound::query()->whereKey($round->id)->lockForUpdate()->firstOrFail();
            if ($lockedRound->status === 'completed') {
                return;
            }

            $this->processAutoCashouts($lockedRound, CarbonImmutable::now()->subMillisecond());

            $bets = FlightGameBet::query()
                ->where('round_id', $lockedRound->id)
                ->where('status', 'placed')
                ->lockForUpdate()
                ->get();

            foreach ($bets as $bet) {
                $this->settleLoss($bet, $lockedRound);
            }

            $lockedRound->status = 'completed';
            $lockedRound->server_seed = (string) (($lockedRound->metadata['server_seed_plain'] ?? null) ?: $lockedRound->server_seed);
            $lockedRound->settled_at = now();
            $metadata = $lockedRound->metadata ?? [];
            unset($metadata['server_seed_plain']);
            $lockedRound->metadata = $metadata;
            $lockedRound->save();

            $this->audit(null, 'flight.round_completed', $lockedRound->id, null, [
                'crash_multiplier' => (string) $lockedRound->crash_multiplier,
            ]);

            $this->publish('game.round.crashed', [
                'round' => $this->transformRound($lockedRound, true, true),
            ]);
        });
    }

    private function settleWin(FlightGameBet $bet, FlightGameRound $round, string $multiplier, string $reason): void
    {
        if ($bet->status !== 'placed') {
            return;
        }

        $stake = $this->fmt((string) $bet->stake);
        $cashout = $this->fmt($multiplier);
        $payout = $this->mul($stake, $cashout);
        $profit = $this->sub($payout, $stake);
        $asset = strtoupper((string) $bet->asset);

        $userFunding = $this->ledger->getOrCreateAccount($bet->user_id, 'funding', $asset);
        $gameLocked = $this->ledger->getOrCreateAccount($bet->user_id, 'game_locked', $asset);
        $treasury = $this->ledger->getOrCreateAccount(null, 'game_treasury', $asset);
        $reference = 'flight_cashout:'.$bet->bet_uuid.':'.$reason;

        $entries = [
            ['account_id' => $gameLocked->id, 'amount' => $this->neg($stake), 'asset' => $asset, 'user_id' => $bet->user_id],
            ['account_id' => $userFunding->id, 'amount' => $payout, 'asset' => $asset, 'user_id' => $bet->user_id],
        ];

        if ($this->compare($profit, '0') > 0) {
            $entries[] = ['account_id' => $treasury->id, 'amount' => $this->neg($profit), 'asset' => $asset];
        }

        $this->ledger->postDoubleEntry($reference, 'EXA Flight cashout', $entries, 'game_reward', [
            'bet_uuid' => $bet->bet_uuid,
            'round_uuid' => $round->round_uuid,
            'cashout_multiplier' => $cashout,
        ]);

        $bet->status = 'cashed_out';
        $bet->cashout_multiplier = $cashout;
        $bet->payout = $payout;
        $bet->profit = $profit;
        $bet->cashed_out_at = now();
        $bet->settled_at = now();
        $bet->metadata = array_merge($bet->metadata ?? [], ['settlement_reason' => $reason]);
        $bet->save();

        $this->publish('game.cashout.accepted', [
            'bet' => $this->transformBet($bet->fresh('round'), false),
            'round' => $this->transformRound($round),
        ]);
    }

    private function settleLoss(FlightGameBet $bet, FlightGameRound $round): void
    {
        if ($bet->status !== 'placed') {
            return;
        }

        $stake = $this->fmt((string) $bet->stake);
        $asset = strtoupper((string) $bet->asset);
        $gameLocked = $this->ledger->getOrCreateAccount($bet->user_id, 'game_locked', $asset);
        $treasury = $this->ledger->getOrCreateAccount(null, 'game_treasury', $asset);

        $this->ledger->postDoubleEntry('flight_loss:'.$bet->bet_uuid, 'EXA Flight loss settlement', [
            ['account_id' => $gameLocked->id, 'amount' => $this->neg($stake), 'asset' => $asset, 'user_id' => $bet->user_id],
            ['account_id' => $treasury->id, 'amount' => $stake, 'asset' => $asset],
        ], 'game_bet', ['bet_uuid' => $bet->bet_uuid, 'round_uuid' => $round->round_uuid]);

        $bet->status = 'lost';
        $bet->payout = '0';
        $bet->profit = $this->neg($stake);
        $bet->settled_at = now();
        $bet->save();
    }

    private function createNextRound(?FlightGameRound $latest): FlightGameRound
    {
        $settings = $this->settings();
        $roundNumber = (int) ($latest?->round_number ?? 0) + 1;
        $start = CarbonImmutable::now()->addSeconds((int) $settings['betting_window_seconds']);
        $serverSeed = $this->fairness->generateServerSeed();
        $clientSeed = sprintf('%s:%d', $settings['public_seed'], $roundNumber);
        $crashMultiplier = $this->fairness->generateCrashMultiplier($serverSeed, $clientSeed, $roundNumber);
        $growthRate = $this->fmt((string) $settings['growth_rate']);
        $secondsToCrash = max(1, (int) ceil(log((float) $crashMultiplier) / max((float) $growthRate, 0.0001)));

        $round = FlightGameRound::query()->create([
            'round_uuid' => (string) Str::uuid(),
            'round_number' => $roundNumber,
            'status' => 'betting',
            'mode' => 'real',
            'asset' => $settings['default_asset'],
            'fairness_version' => self::FAIRNESS_VERSION,
            'server_seed_hash' => $this->fairness->hashServerSeed($serverSeed),
            'client_seed' => $clientSeed,
            'nonce' => $roundNumber,
            'crash_multiplier' => $crashMultiplier,
            'growth_rate' => $growthRate,
            'betting_opens_at' => CarbonImmutable::now(),
            'betting_closes_at' => $start,
            'starts_at' => $start,
            'crashes_at' => $start->addSeconds($secondsToCrash),
            'metadata' => [
                'server_seed_plain' => $serverSeed,
                'max_cashout_multiplier' => $settings['max_multiplier'],
            ],
        ]);

        $this->publish('game.round.betting', ['round' => $this->transformRound($round)]);

        return $round;
    }

    private function transformRound(FlightGameRound $round, bool $includeServerSeed = false, bool $completed = false, bool $includeStats = true): array
    {
        $now = CarbonImmutable::now();
        $phase = $round->status;
        $currentMultiplier = $phase === 'running' ? $this->currentMultiplier($round, $now) : '1.00000000';
        if ($phase === 'completed') {
            $currentMultiplier = $this->fmt((string) $round->crash_multiplier);
        }

        $bets = $includeStats ? FlightGameBet::query()->where('round_id', $round->id)->count() : 0;
        $totalStake = $includeStats
            ? $this->fmt((string) FlightGameBet::query()->where('round_id', $round->id)->sum('stake'))
            : '0.00000000';

        return [
            'round_uuid' => $round->round_uuid,
            'round_number' => $round->round_number,
            'status' => $phase,
            'asset' => $round->asset,
            'fairness_version' => $round->fairness_version,
            'server_seed_hash' => $round->server_seed_hash,
            'server_seed' => $includeServerSeed ? $round->server_seed : null,
            'client_seed' => $round->client_seed,
            'nonce' => $round->nonce,
            'crash_multiplier' => $completed ? $this->fmt((string) $round->crash_multiplier) : null,
            'current_multiplier' => $currentMultiplier,
            'growth_rate' => $this->fmt((string) $round->growth_rate),
            'betting_opens_at' => optional($round->betting_opens_at)->toIso8601String(),
            'betting_closes_at' => optional($round->betting_closes_at)->toIso8601String(),
            'starts_at' => optional($round->starts_at)->toIso8601String(),
            'crashes_at' => optional($round->crashes_at)->toIso8601String(),
            'settled_at' => optional($round->settled_at)->toIso8601String(),
            'players' => $bets,
            'total_stake' => $totalStake,
        ];
    }

    private function transformBet(FlightGameBet $bet, bool $includePrivate = false): array
    {
        $display = (string) ($bet->metadata['display_name'] ?? 'Pilot');

        return [
            'bet_uuid' => $bet->bet_uuid,
            'round_uuid' => $bet->round?->round_uuid,
            'round_number' => $bet->round?->round_number,
            'asset' => $bet->asset,
            'panel_slot' => $bet->panel_slot,
            'stake' => $this->fmt((string) $bet->stake),
            'auto_cashout' => $bet->auto_cashout !== null ? $this->fmt((string) $bet->auto_cashout) : null,
            'status' => $bet->status,
            'cashout_multiplier' => $bet->cashout_multiplier !== null ? $this->fmt((string) $bet->cashout_multiplier) : null,
            'payout' => $this->fmt((string) $bet->payout),
            'profit' => $this->fmt((string) $bet->profit),
            'placed_at' => optional($bet->placed_at)->toIso8601String(),
            'settled_at' => optional($bet->settled_at)->toIso8601String(),
            'player' => $includePrivate ? $display : $this->maskName($display),
            'is_mine' => $includePrivate,
        ];
    }

    private function liveBets(int $roundId): array
    {
        return FlightGameBet::query()
            ->where('round_id', $roundId)
            ->with('round')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (FlightGameBet $bet): array => $this->transformBet($bet, false))
            ->all();
    }

    private function publicSettings(): array
    {
        $settings = $this->settings();

        return [
            'enabled_assets' => $settings['enabled_assets'],
            'default_asset' => $settings['default_asset'],
            'min_stake' => $settings['min_stake'],
            'max_stake' => $settings['max_stake'],
            'max_multiplier' => $settings['max_multiplier'],
            'betting_window_seconds' => $settings['betting_window_seconds'],
        ];
    }

    private function settings(): array
    {
        $defaults = [
            'enabled_assets' => ['USDT', 'USDC', 'EXA'],
            'default_asset' => 'USDT',
            'min_stake' => '1.00000000',
            'max_stake' => '1000.00000000',
            'max_multiplier' => '1000.00000000',
            'betting_window_seconds' => 8,
            'growth_rate' => '0.16',
            'public_seed' => 'EXA-FLIGHT',
        ];

        $stored = FlightGameSetting::query()->get()->mapWithKeys(function (FlightGameSetting $setting): array {
            return [$setting->key => $setting->value];
        })->all();

        return array_merge($defaults, $stored);
    }

    private function currentMultiplier(FlightGameRound $round, CarbonImmutable $now): string
    {
        if ($now->lte($round->starts_at)) {
            return '1.00000000';
        }

        if ($now->gte($round->crashes_at)) {
            return $this->fmt((string) $round->crash_multiplier);
        }

        return number_format(min((float) $this->runningMultiplier($round, $now), (float) $round->crash_multiplier), 8, '.', '');
    }

    private function runningMultiplier(FlightGameRound $round, CarbonImmutable $now): string
    {
        if ($now->lte($round->starts_at)) {
            return '1.00000000';
        }

        $elapsed = max(0, $round->starts_at->diffInMilliseconds($now) / 1000);
        $raw = exp((float) $round->growth_rate * $elapsed);

        return number_format($raw, 8, '.', '');
    }

    private function publish(string $event, array $data): void
    {
        $this->stream->publishPayload(self::STREAM_CHANNEL, [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ]);
    }

    private function audit(?int $actorUserId, string $action, ?int $roundId, ?int $betId, array $metadata = []): void
    {
        FlightGameAuditLog::query()->create([
            'actor_user_id' => $actorUserId,
            'round_id' => $roundId,
            'bet_id' => $betId,
            'action' => $action,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    private function maskName(string $name): string
    {
        $clean = trim($name);
        if ($clean === '') {
            return 'Pi***ot';
        }

        $prefix = mb_substr($clean, 0, min(2, mb_strlen($clean)));
        $suffix = mb_strlen($clean) > 4 ? mb_substr($clean, -2) : '';

        return $prefix.'***'.$suffix;
    }

    private function fmt(string $value): string
    {
        return bcadd($value, '0', self::SCALE);
    }

    private function sub(string $left, string $right): string
    {
        return bcsub($left, $right, self::SCALE);
    }

    private function mul(string $left, string $right): string
    {
        return bcmul($left, $right, self::SCALE);
    }

    private function neg(string $value): string
    {
        return $this->sub('0', $value);
    }

    private function compare(string $left, string $right): int
    {
        return bccomp($left, $right, self::SCALE);
    }
}



