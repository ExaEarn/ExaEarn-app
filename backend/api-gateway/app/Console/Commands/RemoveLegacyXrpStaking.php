<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class RemoveLegacyXrpStaking extends Command
{
    protected $signature = 'staking:remove-legacy-xrp {--drop-resolved : Drop legacy staking tables only when no XRP liabilities remain}';

    protected $description = 'Audit, back up, freeze, and safely remove legacy XRP staking records.';

    public function handle(): int
    {
        $timestamp = now()->format('Ymd_His');
        $backupPath = "staking/xrp-removal/{$timestamp}_legacy_xrp_staking_backup.enc";
        $reportPath = "staking/xrp-removal/{$timestamp}_xrp_staking_removal_report.json";

        $pools = Schema::hasTable('staking_pools')
            ? DB::table('staking_pools')->whereRaw('UPPER(asset) = ?', ['XRP'])->get()
            : collect();
        $poolIds = $pools->pluck('id')->all();

        $stakes = Schema::hasTable('user_stakes') && $poolIds
            ? DB::table('user_stakes')->whereIn('pool_id', $poolIds)->get()
            : collect();
        $stakeIds = $stakes->pluck('id')->all();

        $rewards = Schema::hasTable('staking_rewards') && $stakeIds
            ? DB::table('staking_rewards')->whereIn('stake_id', $stakeIds)->get()
            : collect();

        $report = [
            'generated_at' => now()->toISOString(),
            'affected_users' => $stakes->pluck('user_id')->unique()->count(),
            'total_xrp_principal' => $this->sum($stakes, 'amount'),
            'rewards_credited' => $this->sum($rewards, 'reward_amount'),
            'rewards_already_paid' => $this->sum($rewards->where('claimed', true), 'reward_amount'),
            'outstanding_principal' => $this->sum($stakes->whereIn('status', ['active', 'pending', 'locked']), 'amount'),
            'outstanding_reward_liabilities' => $this->sum($rewards->where('claimed', false), 'reward_amount'),
            'pending_positions' => $stakes->where('status', 'pending')->count(),
            'active_positions' => $stakes->where('status', 'active')->count(),
            'completed_positions' => $stakes->where('status', 'completed')->count(),
            'failed_or_disputed_records' => $stakes->whereIn('status', ['failed', 'disputed'])->count(),
            'legacy_tables_present' => [
                'staking_pools' => Schema::hasTable('staking_pools'),
                'user_stakes' => Schema::hasTable('user_stakes'),
                'staking_rewards' => Schema::hasTable('staking_rewards'),
            ],
            'backup_path' => $backupPath,
            'drop_attempted' => (bool) $this->option('drop-resolved'),
        ];

        Storage::disk('local')->put($backupPath, Crypt::encryptString(json_encode([
            'staking_pools' => $pools,
            'user_stakes' => $stakes,
            'staking_rewards' => $rewards,
        ], JSON_PRETTY_PRINT)));
        Storage::disk('local')->put($reportPath, json_encode($report, JSON_PRETTY_PRINT));

        if (Schema::hasTable('staking_pools') && $poolIds) {
            DB::table('staking_pools')->whereIn('id', $poolIds)->update(['status' => 'disabled', 'updated_at' => now()]);
        }

        $hasLiabilities = $this->positive($report['outstanding_principal']) || $this->positive($report['outstanding_reward_liabilities']);
        if ($this->option('drop-resolved')) {
            if ($hasLiabilities) {
                $this->error('Legacy XRP staking tables were not dropped because unresolved liabilities remain.');
                $this->line("Report: storage/app/{$reportPath}");
                $this->line("Encrypted backup: storage/app/{$backupPath}");

                return self::FAILURE;
            }

            Schema::dropIfExists('staking_rewards');
            Schema::dropIfExists('user_stakes');
            Schema::dropIfExists('staking_pools');
            $this->info('Legacy staking tables dropped after zero-liability audit.');
        }

        $this->info('Legacy XRP staking audit complete.');
        $this->line("Report: storage/app/{$reportPath}");
        $this->line("Encrypted backup: storage/app/{$backupPath}");

        return $hasLiabilities ? self::FAILURE : self::SUCCESS;
    }

    private function sum($rows, string $field): string
    {
        $total = '0';
        foreach ($rows as $row) {
            $value = (string) ($row->{$field} ?? '0');
            $total = function_exists('bcadd') ? bcadd($total, $value, 18) : number_format((float) $total + (float) $value, 18, '.', '');
        }

        return $total;
    }

    private function positive(string $amount): bool
    {
        return function_exists('bccomp') ? bccomp($amount, '0', 18) > 0 : (float) $amount > 0;
    }
}
