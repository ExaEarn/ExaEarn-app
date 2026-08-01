<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class EvaluateExaTokenBonusEligibility implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 600;

    public function handle(): void
    {
        $campaigns = DB::table('exatoken_staking_campaigns')->where('status', 'active')->get();
        foreach ($campaigns as $campaign) {
            $remaining = $this->sub($this->sub((string) $campaign->budget_amount, (string) $campaign->reserved_amount), (string) $campaign->distributed_amount);
            if ($this->compare($remaining, '0') <= 0) {
                DB::table('exatoken_staking_campaigns')->where('id', $campaign->id)->update([
                    'status' => 'paused_insufficient_reserve',
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function uniqueId(): string
    {
        return 'staking:evaluate-exatoken-bonuses';
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, 18) : number_format((float) $a - (float) $b, 18, '.', '');
    }

    private function compare(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, 18);
        }

        return (float) $a <=> (float) $b;
    }
}
