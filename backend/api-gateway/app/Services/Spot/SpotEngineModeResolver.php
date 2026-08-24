<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Models\Market;

class SpotEngineModeResolver
{
    public const LEGACY = 'legacy';
    public const SHADOW = 'shadow';
    public const NEW = 'new';
    public const HALTED = 'halted';
    public const ROLLBACK_ONLY = 'rollback_only';

    /**
     * @return array<int, string>
     */
    public function modes(): array
    {
        return [self::LEGACY, self::SHADOW, self::NEW, self::HALTED, self::ROLLBACK_ONLY];
    }

    public function mode(Market $market): string
    {
        $override = $this->overrideFor((string) $market->symbol);
        if ($override !== null) {
            return $override;
        }

        $global = strtolower((string) config('trading.engine.mode', 'legacy'));
        if ($global !== self::LEGACY && in_array($global, $this->modes(), true)) {
            return $global;
        }

        $mode = strtolower((string) ($market->engine_mode ?: ''));
        if (in_array($mode, $this->modes(), true)) {
            return $mode;
        }

        $default = strtolower((string) config('trading.engine.default_mode', 'legacy'));
        return in_array($default, $this->modes(), true) ? $default : self::LEGACY;
    }

    public function isNewAuthority(Market $market): bool
    {
        return $this->mode($market) === self::NEW;
    }

    public function isLegacyAuthority(Market $market): bool
    {
        return in_array($this->mode($market), [self::LEGACY, self::SHADOW, self::ROLLBACK_ONLY], true);
    }

    public function rejectsNewOrders(Market $market): bool
    {
        return $this->mode($market) === self::HALTED || in_array((string) $market->cutover_state, [
            'CUTOVER_PENDING',
            'HALTED_FOR_CUTOVER',
            'INITIALIZING_NEW_ENGINE',
            'VALIDATING',
            'ROLLBACK_PENDING',
            'HALTED_FOR_ROLLBACK',
        ], true);
    }

    public function assertOrderEntryAllowed(Market $market): void
    {
        if ($this->rejectsNewOrders($market)) {
            throw new \RuntimeException('Market is not accepting new orders during engine cutover.');
        }
    }

    private function overrideFor(string $symbol): ?string
    {
        foreach ((array) config('trading.engine.market_overrides', []) as $entry) {
            if (!str_contains($entry, '=')) {
                continue;
            }
            [$market, $mode] = array_map('trim', explode('=', $entry, 2));
            $mode = strtolower($mode);
            if (strtoupper($market) === strtoupper($symbol) && in_array($mode, $this->modes(), true)) {
                return $mode;
            }
        }

        return null;
    }
}
