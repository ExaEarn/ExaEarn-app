<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ProcessLotteryResultJob;
use App\Jobs\UpdateGameLeaderboardJob;
use App\Jobs\VerifyEntryTransactionJob;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\AuditLog;
use App\Models\Bet;
use App\Models\BettingPool;
use App\Models\LotteryEntry;
use App\Models\LotteryGame;
use App\Models\LotteryResult;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class GameFiService
{
    private const SCALE = 8;

    public function __construct(private readonly BlockchainApiService $blockchain)
    {
    }

    public function lotteryGames(): Collection
    {
        return LotteryGame::query()
            ->with(['results'])
            ->latest()
            ->get()
            ->map(fn (LotteryGame $game) => $this->transformGame($game));
    }

    public function lotteryGame(int $gameId): array
    {
        $game = LotteryGame::query()->with(['entries.user', 'results'])->findOrFail($gameId);

        return [
            ...$this->transformGame($game),
            'entries' => $game->entries->map(fn (LotteryEntry $entry) => $this->transformEntry($entry)),
            'results' => $game->results->map(fn (LotteryResult $result) => $this->transformResult($result)),
        ];
    }

    public function bettingPools(): Collection
    {
        return BettingPool::query()
            ->latest()
            ->get()
            ->map(fn (BettingPool $pool) => $this->transformPool($pool));
    }

    public function createLotteryGame(array $payload): LotteryGame
    {
        $game = LotteryGame::query()->create([
            'game_uuid' => (string) Str::uuid(),
            'name' => (string) $payload['name'],
            'entry_fee_eth' => (string) $payload['entry_fee_eth'],
            'max_players' => $payload['max_players'] ?? null,
            'current_players' => 0,
            'jackpot_amount_eth' => '0',
            'trigger_type' => $payload['trigger_type'],
            'draw_at' => $payload['draw_at'] ?? null,
            'rolling_interval_seconds' => $payload['rolling_interval_seconds'] ?? null,
            'status' => 'open',
            'metadata' => [
                'frontend_visible' => true,
            ],
        ]);

        if (config('gamefi.contract_enabled', true)) {
            $onChain = $this->blockchain->createLotteryRound([
                'game_id' => $game->id,
                'entry_fee_eth' => (string) $game->entry_fee_eth,
                'max_players' => $game->max_players,
                'draw_at' => $game->draw_at?->timestamp,
            ]);

            $game->contract_round_id = (int) ($onChain['round_id'] ?? 0) ?: null;
            $game->contract_address = $onChain['contract_address'] ?? null;
            $game->creation_tx_hash = $onChain['tx_hash'] ?? null;
            $game->save();
        }

        $this->logAudit(null, 'gamefi_lottery_created', ['game_id' => $game->id]);

        return $game->fresh();
    }

    public function recordLotteryEntry(User $user, int $gameId, array $payload): LotteryEntry
    {
        $game = LotteryGame::query()->findOrFail($gameId);
        $this->guardWalletAge($user);

        $entryCount = LotteryEntry::query()
            ->where('game_id', $game->id)
            ->where('wallet_address', strtolower((string) $payload['wallet_address']))
            ->count();

        if ($entryCount >= config('gamefi.max_entries_per_wallet', 10)) {
            throw new RuntimeException('Wallet has reached the maximum entries for this lottery.');
        }

        $entry = LotteryEntry::query()->create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'wallet_address' => strtolower((string) $payload['wallet_address']),
            'entry_tx_hash' => strtolower((string) $payload['entry_tx_hash']),
            'entry_amount_eth' => (string) $payload['entry_amount_eth'],
            'status' => 'pending_verification',
            'metadata' => [
                'submitted_from' => 'api',
            ],
        ]);

        VerifyEntryTransactionJob::dispatch('lottery', $entry->id);

        return $entry;
    }

    public function enterLottery(User $user, int $gameId, array $payload): LotteryEntry
    {
        $game = LotteryGame::query()->findOrFail($gameId);
        if ($game->status !== 'open') {
            throw new RuntimeException('Lottery is not open.');
        }

        if (!$game->contract_round_id) {
            throw new RuntimeException('Lottery round is not available on-chain yet.');
        }

        $this->guardWalletAge($user);

        $walletAddress = strtolower((string) $payload['wallet_address']);
        $entryCount = LotteryEntry::query()
            ->where('game_id', $game->id)
            ->where('wallet_address', $walletAddress)
            ->count();

        if ($entryCount >= config('gamefi.max_entries_per_wallet', 10)) {
            throw new RuntimeException('Wallet has reached the maximum entries for this lottery.');
        }

        $onChain = $this->blockchain->executeContract(
            'enterLottery',
            [(int) $game->contract_round_id],
            'lottery',
            (string) ($payload['network'] ?? 'base'),
            (string) $game->entry_fee_eth
        );

        $txHash = strtolower((string) ($onChain['txHash'] ?? $onChain['tx_hash'] ?? ''));
        if ($txHash === '') {
            throw new RuntimeException('Blockchain service did not return a transaction hash.');
        }

        return DB::transaction(function () use ($user, $game, $payload, $walletAddress, $txHash, $onChain): LotteryEntry {
            $entry = LotteryEntry::query()->create([
                'game_id' => $game->id,
                'user_id' => $user->id,
                'wallet_address' => $walletAddress,
                'entry_tx_hash' => $txHash,
                'entry_amount_eth' => (string) $game->entry_fee_eth,
                'status' => 'pending_verification',
                'metadata' => [
                    'submitted_from' => 'backend_blockchain_api',
                    'network' => $payload['network'] ?? 'base',
                    'blockchain_response' => $onChain,
                ],
            ]);

            Transaction::query()->create([
                'transaction_id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'type' => TransactionType::LotteryBet,
                'currency' => 'ETH',
                'amount' => (string) $game->entry_fee_eth,
                'fee' => '0',
                'status' => TransactionStatus::Pending,
                'reference' => 'lottery_entry:' . $entry->id,
                'tx_hash' => $txHash,
                'metadata' => [
                    'game_id' => $game->id,
                    'contract_round_id' => $game->contract_round_id,
                    'wallet_address' => $walletAddress,
                    'network' => $payload['network'] ?? 'base',
                ],
            ]);

            return $entry;
        });
    }

    public function createBettingPool(array $payload): BettingPool
    {
        $pool = BettingPool::query()->create([
            'pool_uuid' => (string) Str::uuid(),
            'event_name' => (string) $payload['event_name'],
            'bet_options' => array_values($payload['bet_options']),
            'entry_fee_eth' => (string) ($payload['entry_fee_eth'] ?? 0),
            'status' => 'open',
            'locking_at' => $payload['locking_at'] ?? null,
            'metadata' => [
                'frontend_visible' => true,
            ],
        ]);

        if (config('gamefi.contract_enabled', true)) {
            $onChain = $this->blockchain->createBettingPool([
                'pool_id' => $pool->id,
                'event_name' => $pool->event_name,
                'bet_options' => $pool->bet_options,
                'locking_at' => $pool->locking_at?->timestamp,
            ]);

            $pool->contract_pool_id = (int) ($onChain['pool_id'] ?? 0) ?: null;
            $pool->contract_address = $onChain['contract_address'] ?? null;
            $pool->creation_tx_hash = $onChain['tx_hash'] ?? null;
            $pool->save();
        }

        return $pool->fresh();
    }

    public function recordBet(User $user, int $poolId, array $payload): Bet
    {
        $pool = BettingPool::query()->findOrFail($poolId);
        if ($pool->status !== 'open') {
            throw new RuntimeException('Betting pool is not open.');
        }

        $this->guardWalletAge($user);

        $betCount = Bet::query()
            ->where('pool_id', $pool->id)
            ->where('wallet_address', strtolower((string) $payload['wallet_address']))
            ->count();

        if ($betCount >= config('gamefi.max_bets_per_wallet', 20)) {
            throw new RuntimeException('Wallet has reached the maximum bets for this pool.');
        }

        if (!in_array((string) $payload['bet_option'], $pool->bet_options ?? [], true)) {
            throw new RuntimeException('Selected bet option is not valid for this pool.');
        }

        $bet = Bet::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'wallet_address' => strtolower((string) $payload['wallet_address']),
            'bet_option' => (string) $payload['bet_option'],
            'bet_amount_eth' => (string) $payload['bet_amount_eth'],
            'entry_tx_hash' => strtolower((string) $payload['entry_tx_hash']),
            'status' => 'pending_verification',
            'metadata' => [],
        ]);

        VerifyEntryTransactionJob::dispatch('bet', $bet->id);

        return $bet;
    }

    public function verifyEntryTransaction(string $type, int $recordId): void
    {
        if ($type === 'lottery') {
            $entry = LotteryEntry::query()->with('game')->findOrFail($recordId);
            if ($entry->status !== 'pending_verification') {
                return;
            }

            $verification = $this->blockchain->verifyLotteryEntry([
                'tx_hash' => $entry->entry_tx_hash,
                'round_id' => $entry->game->contract_round_id,
                'wallet_address' => $entry->wallet_address,
                'entry_fee_eth' => (string) $entry->game->entry_fee_eth,
            ]);

            if (!($verification['confirmed'] ?? false)) {
                $entry->status = 'rejected';
                $entry->metadata = array_merge($entry->metadata ?? [], ['verification' => $verification]);
                $entry->save();
                return;
            }

            DB::transaction(function () use ($entry, $verification): void {
                $entry->status = 'verified';
                $entry->verified_at = now();
                $entry->metadata = array_merge($entry->metadata ?? [], ['verification' => $verification]);
                $entry->save();

                $entry->game->increment('current_players');
                $entry->game->jackpot_amount_eth = $this->add((string) $entry->game->jackpot_amount_eth, (string) $entry->entry_amount_eth);
                $entry->game->save();
            });

            $this->blockchain->publishGameEvent([
                'channel' => 'lottery',
                'event' => 'player_joined',
                'payload' => [
                    'game_id' => $entry->game_id,
                    'current_players' => $entry->game->current_players,
                    'jackpot_amount_eth' => (string) $entry->game->jackpot_amount_eth,
                ],
            ]);

            ProcessLotteryResultJob::dispatch($entry->game_id);
            UpdateGameLeaderboardJob::dispatch('lottery');
            return;
        }

        $bet = Bet::query()->with('pool')->findOrFail($recordId);
        if ($bet->status !== 'pending_verification') {
            return;
        }

        $verification = $this->blockchain->verifyBettingEntry([
            'tx_hash' => $bet->entry_tx_hash,
            'pool_id' => $bet->pool->contract_pool_id,
            'wallet_address' => $bet->wallet_address,
            'bet_option' => $bet->bet_option,
            'bet_amount_eth' => (string) $bet->bet_amount_eth,
        ]);

        $bet->status = ($verification['confirmed'] ?? false) ? 'verified' : 'rejected';
        $bet->verified_at = ($verification['confirmed'] ?? false) ? now() : null;
        $bet->metadata = array_merge($bet->metadata ?? [], ['verification' => $verification]);
        $bet->save();

        if ($bet->status === 'verified') {
            $this->blockchain->publishGameEvent([
                'channel' => 'betting',
                'event' => 'bet_joined',
                'payload' => [
                    'pool_id' => $bet->pool_id,
                    'bet_option' => $bet->bet_option,
                    'bet_amount_eth' => (string) $bet->bet_amount_eth,
                ],
            ]);
            UpdateGameLeaderboardJob::dispatch('betting');
        }
    }

    public function syncLotteryResult(int $gameId): void
    {
        $game = LotteryGame::query()->findOrFail($gameId);
        if ($game->status === 'completed' || !$game->contract_round_id) {
            return;
        }

        $result = $this->blockchain->fetchLotteryResult([
            'round_id' => $game->contract_round_id,
        ]);

        if (!($result['drawn'] ?? false) || empty($result['winner_wallet'])) {
            return;
        }

        LotteryResult::query()->updateOrCreate(
            ['game_id' => $game->id],
            [
                'winner_wallet' => strtolower((string) $result['winner_wallet']),
                'jackpot_amount_eth' => (string) ($result['jackpot_amount_eth'] ?? $game->jackpot_amount_eth),
                'tx_hash' => $result['tx_hash'] ?? null,
                'draw_time' => now(),
                'metadata' => $result,
            ]
        );

        $game->status = 'completed';
        $game->metadata = array_merge($game->metadata ?? [], ['result_synced_at' => now()->toISOString()]);
        $game->save();

        $this->blockchain->publishGameEvent([
            'channel' => 'lottery',
            'event' => 'winner_selected',
            'payload' => [
                'game_id' => $game->id,
                'winner_wallet' => $result['winner_wallet'],
                'jackpot_amount_eth' => $result['jackpot_amount_eth'] ?? (string) $game->jackpot_amount_eth,
            ],
        ]);
    }

    public function resolveBettingPool(int $poolId, array $payload): BettingPool
    {
        $pool = BettingPool::query()->findOrFail($poolId);
        if ($pool->status !== 'open') {
            throw new RuntimeException('Betting pool is not open.');
        }

        if (!in_array((string) $payload['winning_option'], $pool->bet_options ?? [], true)) {
            throw new RuntimeException('Winning option is invalid for this pool.');
        }

        if (config('gamefi.contract_enabled', true) && $pool->contract_pool_id) {
            $onChain = $this->blockchain->resolveBettingPool([
                'pool_id' => $pool->contract_pool_id,
                'winning_option' => (string) $payload['winning_option'],
            ]);

            $pool->metadata = array_merge($pool->metadata ?? [], ['resolution_tx_hash' => $onChain['tx_hash'] ?? null]);
        }

        $pool->winning_option = (string) $payload['winning_option'];
        $pool->status = 'resolved';
        $pool->save();

        $this->blockchain->publishGameEvent([
            'channel' => 'betting',
            'event' => 'pool_resolved',
            'payload' => [
                'pool_id' => $pool->id,
                'winning_option' => $pool->winning_option,
            ],
        ]);

        return $pool;
    }

    public function refreshLeaderboards(string $context): void
    {
        $this->logAudit(null, 'gamefi_leaderboard_refresh', ['context' => $context]);
    }

    private function transformGame(LotteryGame $game): array
    {
        return [
            'id' => $game->id,
            'game_uuid' => $game->game_uuid,
            'contract_round_id' => $game->contract_round_id,
            'name' => $game->name,
            'entry_fee_eth' => (string) $game->entry_fee_eth,
            'max_players' => $game->max_players,
            'current_players' => $game->current_players,
            'jackpot_amount_eth' => (string) $game->jackpot_amount_eth,
            'trigger_type' => $game->trigger_type,
            'draw_at' => $game->draw_at?->toISOString(),
            'status' => $game->status,
            'latest_result' => $game->results()->latest()->first(),
        ];
    }

    private function transformEntry(LotteryEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'user_id' => $entry->user_id,
            'wallet_address' => $entry->wallet_address,
            'entry_tx_hash' => $entry->entry_tx_hash,
            'entry_amount_eth' => (string) $entry->entry_amount_eth,
            'status' => $entry->status,
            'verified_at' => $entry->verified_at?->toISOString(),
        ];
    }

    private function transformResult(LotteryResult $result): array
    {
        return [
            'id' => $result->id,
            'winner_wallet' => $result->winner_wallet,
            'jackpot_amount_eth' => (string) $result->jackpot_amount_eth,
            'tx_hash' => $result->tx_hash,
            'draw_time' => $result->draw_time?->toISOString(),
        ];
    }

    private function transformPool(BettingPool $pool): array
    {
        return [
            'id' => $pool->id,
            'pool_uuid' => $pool->pool_uuid,
            'contract_pool_id' => $pool->contract_pool_id,
            'event_name' => $pool->event_name,
            'bet_options' => $pool->bet_options ?? [],
            'entry_fee_eth' => (string) $pool->entry_fee_eth,
            'status' => $pool->status,
            'winning_option' => $pool->winning_option,
            'locking_at' => $pool->locking_at?->toISOString(),
        ];
    }

    private function guardWalletAge(User $user): void
    {
        $minWalletAge = max(0, (int) config('gamefi.min_wallet_age_days', 1));
        if ($user->created_at && $user->created_at->diffInDays(now()) < $minWalletAge) {
            throw new RuntimeException('Wallet age requirement not met for GameFi participation.');
        }
    }

    private function logAudit(?int $userId, string $action, array $metadata = []): void
    {
        AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => request()?->ip(),
            'device' => request()?->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    private function add(string $left, string $right): string
    {
        if (function_exists('bcadd')) {
            return bcadd($left, $right, self::SCALE);
        }

        return number_format(((float) $left + (float) $right), self::SCALE, '.', '');
    }
}
