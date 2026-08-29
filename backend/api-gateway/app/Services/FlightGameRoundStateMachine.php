<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FlightGameRound;
use RuntimeException;

class FlightGameRoundStateMachine
{
    public const SCHEDULED = 'SCHEDULED';
    public const OPEN = 'OPEN';
    public const LOCKED = 'LOCKED';
    public const RUNNING = 'RUNNING';
    public const ENDED = 'ENDED';
    public const SETTLING = 'SETTLING';
    public const SETTLED = 'SETTLED';
    public const CANCELLED = 'CANCELLED';
    public const FAILED = 'FAILED';
    public const MANUAL_REVIEW = 'MANUAL_REVIEW';

    private const TRANSITIONS = [
        self::SCHEDULED => [self::OPEN, self::CANCELLED],
        self::OPEN => [self::LOCKED, self::CANCELLED],
        self::LOCKED => [self::RUNNING, self::CANCELLED, self::FAILED, self::MANUAL_REVIEW],
        self::RUNNING => [self::ENDED, self::FAILED, self::MANUAL_REVIEW],
        self::ENDED => [self::SETTLING, self::FAILED, self::MANUAL_REVIEW],
        self::SETTLING => [self::SETTLED, self::FAILED, self::MANUAL_REVIEW],
        self::SETTLED => [],
        self::CANCELLED => [],
        self::FAILED => [self::MANUAL_REVIEW],
        self::MANUAL_REVIEW => [],
    ];

    public function normalize(?string $state, ?string $legacyStatus = null): string
    {
        if (in_array((string) $legacyStatus, ['running', 'completed', 'cancelled', 'failed'], true)) {
            return match ((string) $legacyStatus) {
                'running' => in_array(strtoupper((string) $state), [self::ENDED, self::SETTLING], true) ? strtoupper((string) $state) : self::RUNNING,
                'completed' => self::SETTLED,
                'cancelled' => self::CANCELLED,
                'failed' => strtoupper((string) $state) === self::MANUAL_REVIEW ? self::MANUAL_REVIEW : self::FAILED,
                default => self::SCHEDULED,
            };
        }

        $state = strtoupper((string) $state);
        if (isset(self::TRANSITIONS[$state])) {
            return $state;
        }

        return match ((string) $legacyStatus) {
            'betting' => self::OPEN,
            'running' => self::RUNNING,
            'completed' => self::SETTLED,
            'cancelled' => self::CANCELLED,
            'failed' => self::FAILED,
            default => self::SCHEDULED,
        };
    }

    public function transition(FlightGameRound $round, string $to, array $metadata = []): FlightGameRound
    {
        $from = $this->normalize($round->round_state, $round->status);
        $to = strtoupper($to);

        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new RuntimeException(sprintf('Invalid EXA Flight round transition: %s -> %s.', $from, $to));
        }

        $round->round_state = $to;
        $round->status = $this->legacyStatus($to);

        if ($to === self::LOCKED) {
            $round->locked_at = $round->locked_at ?: now();
        }
        if ($to === self::ENDED) {
            $round->ended_at = $round->ended_at ?: now();
        }
        if ($to === self::SETTLED) {
            $round->settled_at = $round->settled_at ?: now();
        }
        if ($to === self::MANUAL_REVIEW) {
            $round->manual_review_at = $round->manual_review_at ?: now();
        }

        if ($metadata !== []) {
            $round->metadata = array_merge($round->metadata ?? [], ['state_transition' => compact('from', 'to')], $metadata);
        }

        $round->save();

        return $round->fresh();
    }

    public function assertCanEnter(FlightGameRound $round): void
    {
        if ($this->normalize($round->round_state, $round->status) !== self::OPEN) {
            throw new RuntimeException('This round is no longer accepting entries.');
        }
    }

    public function assertCanCashout(FlightGameRound $round): void
    {
        if ($this->normalize($round->round_state, $round->status) !== self::RUNNING) {
            throw new RuntimeException('This round is not in a collectible state.');
        }
    }

    public function assertCanSettle(FlightGameRound $round): void
    {
        if (! in_array($this->normalize($round->round_state, $round->status), [self::ENDED, self::SETTLING], true)) {
            throw new RuntimeException('This round is not ready for settlement.');
        }
    }

    private function legacyStatus(string $state): string
    {
        return match ($state) {
            self::SCHEDULED, self::OPEN, self::LOCKED => 'betting',
            self::RUNNING, self::ENDED, self::SETTLING => 'running',
            self::SETTLED => 'completed',
            self::CANCELLED => 'cancelled',
            self::FAILED, self::MANUAL_REVIEW => 'failed',
            default => 'failed',
        };
    }
}
