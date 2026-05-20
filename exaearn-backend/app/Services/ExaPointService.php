<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ExaPointTransaction;
use App\Models\ExapointBalance;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use RuntimeException;

class ExaPointService
{
    private const SCALE = 8;

    public function earn(int $userId, string $amount, string $reference, ?string $description = null, array $metadata = []): array
    {
        $this->guardRateLimit($userId, 'earn');
        $this->guardPositive($amount, 'Earn amount must be greater than zero.');

        DB::transaction(function () use ($userId, $amount, $reference, $description, $metadata): void {
            $this->guardReferenceUnique($reference);

            $balance = $this->lockBalanceRow($userId);
            $newAvailable = $this->add((string) $balance->available_points, $amount);
            $newTotalEarned = $this->add((string) $balance->total_earned, $amount);

            $balance->available_points = $newAvailable;
            $balance->total_earned = $newTotalEarned;
            $balance->save();

            $this->logTransaction($userId, 'earn', $amount, $newAvailable, $reference, $description, $metadata);
            $this->logAudit($userId, 'exapoint.earn', [
                'amount' => $amount,
                'reference' => $reference,
                'description' => $description,
            ]);
        });

        return $this->afterMutation($userId);
    }

    public function spend(int $userId, string $amount, string $reference, ?string $description = null, array $metadata = []): array
    {
        $this->guardRateLimit($userId, 'spend');
        $this->guardPositive($amount, 'Spend amount must be greater than zero.');

        DB::transaction(function () use ($userId, $amount, $reference, $description, $metadata): void {
            $this->guardReferenceUnique($reference);

            $balance = $this->lockBalanceRow($userId);
            if ($this->compare((string) $balance->available_points, $amount) < 0) {
                throw new RuntimeException('Insufficient available ExaPoints.');
            }

            $newAvailable = $this->sub((string) $balance->available_points, $amount);
            $newSpent = $this->add((string) $balance->total_spent, $amount);

            $balance->available_points = $newAvailable;
            $balance->total_spent = $newSpent;
            $balance->save();

            $this->logTransaction($userId, 'spend', $amount, $newAvailable, $reference, $description, $metadata);
            $this->logAudit($userId, 'exapoint.spend', [
                'amount' => $amount,
                'reference' => $reference,
                'description' => $description,
            ]);
        });

        return $this->afterMutation($userId);
    }

    public function lock(int $userId, string $amount, ?string $reference = null, ?string $description = null, array $metadata = []): array
    {
        $this->guardRateLimit($userId, 'lock');
        $this->guardPositive($amount, 'Lock amount must be greater than zero.');
        $reference = $reference ?: $this->generateReference('lock', $userId);

        DB::transaction(function () use ($userId, $amount, $reference, $description, $metadata): void {
            $this->guardReferenceUnique($reference);

            $balance = $this->lockBalanceRow($userId);
            if ($this->compare((string) $balance->available_points, $amount) < 0) {
                throw new RuntimeException('Insufficient available ExaPoints for lock.');
            }

            $newAvailable = $this->sub((string) $balance->available_points, $amount);
            $newLocked = $this->add((string) $balance->locked_points, $amount);

            $balance->available_points = $newAvailable;
            $balance->locked_points = $newLocked;
            $balance->save();

            $this->logTransaction($userId, 'lock', $amount, $newAvailable, $reference, $description, $metadata);
            $this->logAudit($userId, 'exapoint.lock', [
                'amount' => $amount,
                'reference' => $reference,
            ]);
        });

        return $this->afterMutation($userId);
    }

    public function unlock(int $userId, string $amount, ?string $reference = null, ?string $description = null, array $metadata = []): array
    {
        $this->guardRateLimit($userId, 'unlock');
        $this->guardPositive($amount, 'Unlock amount must be greater than zero.');
        $reference = $reference ?: $this->generateReference('unlock', $userId);

        DB::transaction(function () use ($userId, $amount, $reference, $description, $metadata): void {
            $this->guardReferenceUnique($reference);

            $balance = $this->lockBalanceRow($userId);
            if ($this->compare((string) $balance->locked_points, $amount) < 0) {
                throw new RuntimeException('Insufficient locked ExaPoints for unlock.');
            }

            $newLocked = $this->sub((string) $balance->locked_points, $amount);
            $newAvailable = $this->add((string) $balance->available_points, $amount);

            $balance->locked_points = $newLocked;
            $balance->available_points = $newAvailable;
            $balance->save();

            $this->logTransaction($userId, 'unlock', $amount, $newAvailable, $reference, $description, $metadata);
            $this->logAudit($userId, 'exapoint.unlock', [
                'amount' => $amount,
                'reference' => $reference,
            ]);
        });

        return $this->afterMutation($userId);
    }

    public function adjust(
        int $userId,
        string $amount,
        string $type,
        User $actor,
        ?string $reference = null,
        ?string $description = null,
        array $metadata = [],
        bool $approvedLargeAdjustment = false,
    ): array {
        if ((string) $actor->role !== 'admin') {
            throw new RuntimeException('Only admins can adjust ExaPoints.');
        }

        $normalizedType = strtolower(trim($type));
        if (!in_array($normalizedType, ['credit', 'debit'], true)) {
            throw new RuntimeException('Adjustment type must be credit or debit.');
        }

        $this->guardPositive($amount, 'Adjustment amount must be greater than zero.');
        $this->guardRateLimit($actor->id, 'adjust');
        $reference = $reference ?: $this->generateReference('adjust', $userId);

        $threshold = (string) config('exapoints.large_adjustment_threshold', '10000');
        if ($this->compare($amount, $threshold) > 0 && !$approvedLargeAdjustment) {
            throw new RuntimeException('Large adjustment requires explicit approval.');
        }

        DB::transaction(function () use ($userId, $amount, $normalizedType, $actor, $reference, $description, $metadata): void {
            $this->guardReferenceUnique($reference);

            $balance = $this->lockBalanceRow($userId);
            $available = (string) $balance->available_points;
            $earned = (string) $balance->total_earned;
            $spent = (string) $balance->total_spent;

            if ($normalizedType === 'credit') {
                $available = $this->add($available, $amount);
                $earned = $this->add($earned, $amount);
                $signedAmount = $amount;
            } else {
                if ($this->compare($available, $amount) < 0) {
                    throw new RuntimeException('Cannot debit more than available ExaPoints.');
                }

                $available = $this->sub($available, $amount);
                $spent = $this->add($spent, $amount);
                $signedAmount = $this->mul($amount, '-1');
            }

            $balance->available_points = $available;
            $balance->total_earned = $earned;
            $balance->total_spent = $spent;
            $balance->save();

            $this->logTransaction($userId, 'adjust', $signedAmount, $available, $reference, $description, array_merge($metadata, [
                'adjustment_type' => $normalizedType,
                'admin_user_id' => $actor->id,
            ]));

            $this->logAudit($actor->id, 'exapoint.adjust', [
                'target_user_id' => $userId,
                'amount' => $amount,
                'adjustment_type' => $normalizedType,
                'reference' => $reference,
            ]);
        });

        return $this->afterMutation($userId);
    }

    public function getBalance(int $userId): array
    {
        $cacheKey = $this->balanceCacheKey($userId);
        $ttl = (int) config('exapoints.balance_cache_ttl', 60);

        /** @var array{user_id:int,available_points:string,locked_points:string,total_points:string} $value */
        $value = Cache::remember($cacheKey, $ttl, function () use ($userId): array {
            $balance = ExapointBalance::query()->firstOrCreate(
                ['user_id' => $userId],
                [
                    'available_points' => 0,
                    'locked_points' => 0,
                    'total_earned' => 0,
                    'total_spent' => 0,
                ]
            );

            $available = $this->fmt((string) $balance->available_points);
            $locked = $this->fmt((string) $balance->locked_points);

            return [
                'user_id' => $userId,
                'available_points' => $available,
                'locked_points' => $locked,
                'total_points' => $this->add($available, $locked),
            ];
        });

        return $value;
    }

    public function getTotalExaPoints(int $userId): array
    {
        return $this->getBalance($userId);
    }

    public function generateReference(string $prefix, int $userId): string
    {
        return sprintf('exapoint:%s:%d:%s', $prefix, $userId, (string) Str::uuid());
    }

    private function afterMutation(int $userId): array
    {
        Cache::forget($this->balanceCacheKey($userId));
        $payload = $this->getBalance($userId);
        $this->publishBalanceUpdate($payload);

        return $payload;
    }

    private function publishBalanceUpdate(array $payload): void
    {
        try {
            Redis::publish((string) config('exapoints.redis_channel', 'exapoint_updates'), json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (\Throwable $exception) {
            Log::warning('ExaPoint Redis publish failed.', [
                'channel' => (string) config('exapoints.redis_channel', 'exapoint_updates'),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function logTransaction(
        int $userId,
        string $type,
        string $amount,
        string $balanceAfter,
        string $reference,
        ?string $description,
        array $metadata
    ): void {
        ExaPointTransaction::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'amount' => $this->fmt($amount),
            'balance_after' => $this->fmt($balanceAfter),
            'reference' => $reference,
            'description' => $description,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }

    private function logAudit(int $userId, string $action, array $metadata): void
    {
        AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => null,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    private function lockBalanceRow(int $userId): ExapointBalance
    {
        $balance = ExapointBalance::query()->where('user_id', $userId)->lockForUpdate()->first();
        if ($balance) {
            return $balance;
        }

        return ExapointBalance::query()->create([
            'user_id' => $userId,
            'available_points' => 0,
            'locked_points' => 0,
            'total_earned' => 0,
            'total_spent' => 0,
        ]);
    }

    private function guardReferenceUnique(string $reference): void
    {
        if (ExaPointTransaction::query()->where('reference', $reference)->exists()) {
            throw new RuntimeException('Duplicate reward reference detected.');
        }
    }

    private function guardRateLimit(int $userId, string $action): void
    {
        $max = (int) config('exapoints.rate_limit_per_minute', 120);
        $window = now()->format('YmdHi');
        $key = sprintf('exapoint:rate:%s:%d:%s', $action, $userId, $window);

        if (!Cache::has($key)) {
            Cache::put($key, 0, now()->addMinute());
        }

        $current = (int) Cache::increment($key);
        if ($current > $max) {
            throw new RuntimeException('Too many ExaPoint operations. Please retry later.');
        }
    }

    private function guardPositive(string $amount, string $message): void
    {
        if ($this->compare($amount, '0') <= 0) {
            throw new RuntimeException($message);
        }
    }

    private function balanceCacheKey(int $userId): string
    {
        return sprintf('exapoint:balance:%d', $userId);
    }

    private function fmt(string $value): string
    {
        if (str_contains($value, '.')) {
            return number_format((float) $value, self::SCALE, '.', '');
        }

        return number_format((float) $value, self::SCALE, '.', '');
    }

    private function add(string $left, string $right): string
    {
        if (function_exists('bcadd')) {
            return bcadd($left, $right, self::SCALE);
        }

        return number_format((float) $left + (float) $right, self::SCALE, '.', '');
    }

    private function sub(string $left, string $right): string
    {
        if (function_exists('bcsub')) {
            return bcsub($left, $right, self::SCALE);
        }

        return number_format((float) $left - (float) $right, self::SCALE, '.', '');
    }

    private function mul(string $left, string $right): string
    {
        if (function_exists('bcmul')) {
            return bcmul($left, $right, self::SCALE);
        }

        return number_format((float) $left * (float) $right, self::SCALE, '.', '');
    }

    private function compare(string $left, string $right): int
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, self::SCALE);
        }

        $l = (float) $left;
        $r = (float) $right;

        return $l < $r ? -1 : ($l > $r ? 1 : 0);
    }
}

