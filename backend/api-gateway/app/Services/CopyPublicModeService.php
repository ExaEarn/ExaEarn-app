<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CopyPublicSetting;

class CopyPublicModeService
{
    public const MODES = ['DISABLED', 'SHADOW', 'INTERNAL', 'LIMITED_PUBLIC', 'PUBLIC', 'PAUSED', 'EMERGENCY'];
    public const FLAG_STATES = ['DISABLED', 'LIMITED', 'ENABLED', 'PAUSED'];

    public function mode(): string
    {
        return $this->setting('COPY_TRADING_MODE', (string) config('copy_trading.public.mode', 'DISABLED'));
    }

    public function flag(string $key): string
    {
        return $this->setting($key, (string) config('copy_trading.public.flags.' . strtolower($key), 'DISABLED'));
    }

    public function emergencyState(): string
    {
        return $this->setting('COPY_TRADING_EMERGENCY_STATE', 'NORMAL');
    }

    public function set(string $key, string $value, ?int $adminId = null, array $metadata = []): CopyPublicSetting
    {
        return CopyPublicSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => strtoupper($value), 'metadata' => $metadata, 'updated_by' => $adminId],
        );
    }

    public function isPubliclyUsable(): bool
    {
        return in_array($this->mode(), ['LIMITED_PUBLIC', 'PUBLIC'], true)
            && !in_array($this->emergencyState(), ['COPY_PAUSED', 'EMERGENCY'], true);
    }

    public function allowsNewRisk(): bool
    {
        return $this->isPubliclyUsable()
            && !in_array($this->emergencyState(), ['NEW_RISK_DISABLED', 'REDUCE_ONLY'], true);
    }

    private function setting(string $key, string $default): string
    {
        return strtoupper((string) (CopyPublicSetting::query()->where('key', $key)->value('value') ?: $default));
    }
}
