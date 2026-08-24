<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class FinancialDecimal
{
    public const SCALE = 18;

    public static function ensureAvailable(): void
    {
        foreach (['bcadd', 'bcsub', 'bcmul', 'bcdiv', 'bccomp'] as $function) {
            if (!function_exists($function)) {
                throw new RuntimeException('Financial decimal support is unavailable. Enable the PHP BCMath extension before processing money.');
            }
        }
    }

    public static function normalize(string $value, int $scale = self::SCALE): string
    {
        self::ensureAvailable();
        $value = trim($value);
        if ($value === '') {
            throw new RuntimeException('Decimal value is required.');
        }

        return bcadd($value, '0', $scale);
    }

    public static function add(string $left, string $right, int $scale = self::SCALE): string
    {
        self::ensureAvailable();
        return bcadd($left, $right, $scale);
    }

    public static function sub(string $left, string $right, int $scale = self::SCALE): string
    {
        self::ensureAvailable();
        return bcsub($left, $right, $scale);
    }

    public static function mul(string $left, string $right, int $scale = self::SCALE): string
    {
        self::ensureAvailable();
        return bcmul($left, $right, $scale);
    }

    public static function div(string $left, string $right, int $scale = self::SCALE): string
    {
        self::ensureAvailable();
        if (bccomp($right, '0', $scale) === 0) {
            throw new RuntimeException('Division by zero.');
        }

        return bcdiv($left, $right, $scale);
    }

    public static function compare(string $left, string $right, int $scale = self::SCALE): int
    {
        self::ensureAvailable();
        return bccomp($left, $right, $scale);
    }

    public static function min(string $left, string $right, int $scale = self::SCALE): string
    {
        return self::compare($left, $right, $scale) <= 0 ? self::normalize($left, $scale) : self::normalize($right, $scale);
    }

    public static function max(string $left, string $right, int $scale = self::SCALE): string
    {
        return self::compare($left, $right, $scale) >= 0 ? self::normalize($left, $scale) : self::normalize($right, $scale);
    }

    public static function abs(string $value, int $scale = self::SCALE): string
    {
        return self::compare($value, '0', $scale) < 0
            ? self::sub('0', $value, $scale)
            : self::normalize($value, $scale);
    }
}
