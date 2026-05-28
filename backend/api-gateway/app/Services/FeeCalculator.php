<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

class FeeCalculator
{
    private const SCALE = 18;

    /**
     * @return array{source:string, asset:string, gross_amount:string, fee_amount:string, net_amount:string, rate_bps:string, fixed_fee:string}
     */
    public function withdrawal(string $amount, string $asset): array
    {
        return $this->withFixedAndBps('withdrawal', $amount, $asset);
    }

    /**
     * @return array{source:string, asset:string, gross_amount:string, fee_amount:string, net_amount:string, rate_bps:string, fixed_fee:string, liquidity_role:string}
     */
    public function spot(string $notional, string $asset, string $liquidityRole = 'taker'): array
    {
        return $this->marketFee('spot', $notional, $asset, $liquidityRole);
    }

    /**
     * @return array{source:string, asset:string, gross_amount:string, fee_amount:string, net_amount:string, rate_bps:string, fixed_fee:string, liquidity_role:string}
     */
    public function futures(string $notional, string $asset = 'USDT', string $liquidityRole = 'taker'): array
    {
        return $this->marketFee('futures', $notional, $asset, $liquidityRole);
    }

    /**
     * @return array{source:string, asset:string, gross_amount:string, fee_amount:string, net_amount:string, rate_bps:string, fixed_fee:string}
     */
    public function fiatDeposit(string $amount, string $asset = 'NGN'): array
    {
        return $this->withFixedAndBps('fiat_deposit', $amount, $asset);
    }

    private function withFixedAndBps(string $source, string $amount, string $asset): array
    {
        $asset = strtoupper($asset);
        $this->assertPositive($amount);

        $bps = (string) config("fees.{$source}.bps.{$asset}", '0');
        $fixed = (string) config("fees.{$source}.fixed.{$asset}", '0');
        $fee = $this->add($this->bps($amount, $bps), $fixed);

        if ($this->compare($fee, $amount) >= 0) {
            throw new InvalidArgumentException('Fee must be lower than gross amount.');
        }

        return [
            'source' => $source,
            'asset' => $asset,
            'gross_amount' => $this->fmt($amount),
            'fee_amount' => $this->fmt($fee),
            'net_amount' => $this->fmt($this->sub($amount, $fee)),
            'rate_bps' => $bps,
            'fixed_fee' => $this->fmt($fixed),
        ];
    }

    private function marketFee(string $source, string $notional, string $asset, string $liquidityRole): array
    {
        $asset = strtoupper($asset);
        $liquidityRole = strtolower($liquidityRole) === 'maker' ? 'maker' : 'taker';
        $this->assertPositive($notional);

        $bps = (string) config("fees.{$source}.{$liquidityRole}_bps", '0');
        $fee = $this->bps($notional, $bps);

        return [
            'source' => $source,
            'asset' => $asset,
            'gross_amount' => $this->fmt($notional),
            'fee_amount' => $this->fmt($fee),
            'net_amount' => $this->fmt($this->sub($notional, $fee)),
            'rate_bps' => $bps,
            'fixed_fee' => $this->fmt('0'),
            'liquidity_role' => $liquidityRole,
        ];
    }

    private function bps(string $amount, string $bps): string
    {
        return $this->div($this->mul($amount, $bps), '10000');
    }

    private function assertPositive(string $amount): void
    {
        if ($this->compare($amount, '0') <= 0) {
            throw new InvalidArgumentException('Fee basis amount must be greater than zero.');
        }
    }

    private function fmt(string $value): string
    {
        return function_exists('bcadd') ? bcadd($value, '0', self::SCALE) : number_format((float) $value, self::SCALE, '.', '');
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, self::SCALE) : number_format((float) $a + (float) $b, self::SCALE, '.', '');
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, self::SCALE) : number_format((float) $a - (float) $b, self::SCALE, '.', '');
    }

    private function mul(string $a, string $b): string
    {
        return function_exists('bcmul') ? bcmul($a, $b, self::SCALE) : number_format((float) $a * (float) $b, self::SCALE, '.', '');
    }

    private function div(string $a, string $b): string
    {
        return function_exists('bcdiv') ? bcdiv($a, $b, self::SCALE) : number_format((float) $a / (float) $b, self::SCALE, '.', '');
    }

    private function compare(string $a, string $b): int
    {
        return function_exists('bccomp') ? bccomp($a, $b, self::SCALE) : ((float) $a <=> (float) $b);
    }
}
