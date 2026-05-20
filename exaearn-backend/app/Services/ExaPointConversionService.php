<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaPointConversionRecord;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExaPointConversionService
{
    public function __construct(private readonly ExaPointService $exaPointService)
    {
    }

    public function convertPointsToToken(int $userId, ?string $rate = null, ?string $pointsToConvert = null): ExaPointConversionRecord
    {
        $conversionRate = $rate ?: (string) config('exapoints.conversion.default_rate', '1000');
        if ($this->compare($conversionRate, '0') <= 0) {
            throw new RuntimeException('Conversion rate must be greater than zero.');
        }

        $balance = $this->exaPointService->getTotalExaPoints($userId);
        $available = (string) $balance['available_points'];
        $locked = (string) $balance['locked_points'];
        $total = (string) $balance['total_points'];

        $convert = $pointsToConvert ?: $total;
        if ($this->compare($convert, '0') <= 0) {
            throw new RuntimeException('No ExaPoints available for conversion.');
        }

        if ($this->compare($convert, $total) > 0) {
            throw new RuntimeException('Requested conversion exceeds total ExaPoints.');
        }

        return DB::transaction(function () use ($userId, $convert, $available, $conversionRate): ExaPointConversionRecord {
            if ($this->compare($convert, $available) > 0) {
                $unlockAmount = $this->sub($convert, $available);
                $this->exaPointService->unlock(
                    $userId,
                    $unlockAmount,
                    $this->exaPointService->generateReference('conversion_unlock', $userId),
                    'Unlock ExaPoints for conversion',
                    ['module' => 'conversion']
                );
            }

            $this->exaPointService->spend(
                $userId,
                $convert,
                $this->exaPointService->generateReference('conversion_spend', $userId),
                'ExaPoints conversion allocation',
                ['module' => 'conversion']
            );

            $issued = $this->div($convert, $conversionRate);

            return ExaPointConversionRecord::query()->create([
                'user_id' => $userId,
                'exapoints_converted' => $convert,
                'exatokens_issued' => $issued,
                'conversion_rate' => $conversionRate,
                'status' => 'pending',
                'note' => 'Prepared for future ExaToken issuance (mainnet launch).',
            ]);
        });
    }

    private function compare(string $left, string $right): int
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, 8);
        }

        $leftFloat = (float) $left;
        $rightFloat = (float) $right;

        return $leftFloat < $rightFloat ? -1 : ($leftFloat > $rightFloat ? 1 : 0);
    }

    private function sub(string $left, string $right): string
    {
        if (function_exists('bcsub')) {
            return bcsub($left, $right, 8);
        }

        return number_format((float) $left - (float) $right, 8, '.', '');
    }

    private function div(string $left, string $right): string
    {
        if (function_exists('bcdiv')) {
            return bcdiv($left, $right, 8);
        }

        return number_format((float) $left / (float) $right, 8, '.', '');
    }
}

