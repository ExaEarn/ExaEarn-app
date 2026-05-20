<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesPosition;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarginModeService
{
    private const SCALE = 8;

    public function setMarginType(int $userId, int $positionId, string $marginType): FuturesPosition
    {
        $marginType = strtolower($marginType);
        if (!in_array($marginType, ['cross', 'isolated'], true)) {
            throw new RuntimeException('Margin type must be cross or isolated.');
        }

        return DB::transaction(function () use ($userId, $positionId, $marginType): FuturesPosition {
            $position = FuturesPosition::query()
                ->where('id', $positionId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($position->status !== 'open') {
                throw new RuntimeException('Only open positions can change margin type.');
            }

            $position->margin_type = $marginType;
            $position->save();

            return $position;
        });
    }

    public function calculateEffectiveMargin(FuturesPosition $position, string $walletBalance): string
    {
        if ($position->margin_type === 'cross') {
            return $this->add((string) $position->margin, $walletBalance);
        }

        return (string) $position->margin;
    }

    public function calculateMargin(FuturesPosition $position, string $walletBalance): string
    {
        return $this->calculateEffectiveMargin($position, $walletBalance);
    }

    public function adjustLiquidationPrice(FuturesPosition $position, string $walletBalance): string
    {
        $effectiveMargin = $this->calculateEffectiveMargin($position, $walletBalance);
        $qty = (string) $position->quantity;
        if ($this->compare($qty, '0') <= 0) {
            return (string) $position->liquidation_price;
        }

        $bufferPerUnit = $this->div($effectiveMargin, $qty);
        return $position->side === 'long'
            ? $this->sub((string) $position->entry_price, $bufferPerUnit)
            : $this->add((string) $position->entry_price, $bufferPerUnit);
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, self::SCALE) : number_format((float) $a + (float) $b, self::SCALE, '.', '');
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, self::SCALE) : number_format((float) $a - (float) $b, self::SCALE, '.', '');
    }

    private function div(string $a, string $b): string
    {
        if ($this->compare($b, '0') === 0) {
            throw new RuntimeException('Division by zero.');
        }
        return function_exists('bcdiv') ? bcdiv($a, $b, self::SCALE) : number_format((float) $a / (float) $b, self::SCALE, '.', '');
    }

    private function compare(string $a, string $b): int
    {
        return function_exists('bccomp') ? bccomp($a, $b, self::SCALE) : ((float) $a <=> (float) $b);
    }
}
