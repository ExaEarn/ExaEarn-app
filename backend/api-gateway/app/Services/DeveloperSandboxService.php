<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeveloperProject;
use App\Models\DeveloperSandboxBalance;
use App\Models\DeveloperSandboxFaucetClaim;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeveloperSandboxService
{
    public function faucet(DeveloperProject $project, string $asset, ?string $amount = null): DeveloperSandboxFaucetClaim
    {
        if ($project->environment !== 'sandbox') {
            throw new RuntimeException('Developer faucet is available only for sandbox projects.');
        }

        $asset = strtoupper($asset);
        $allowed = (array) config('developer_api.sandbox_faucet', []);
        if (! isset($allowed[$asset])) {
            throw new RuntimeException('Requested sandbox asset is unavailable.');
        }

        $amount = $amount ?: (string) $allowed[$asset];
        if (bccomp($amount, (string) $allowed[$asset], 8) > 0) {
            throw new RuntimeException('Requested sandbox faucet amount exceeds the configured limit.');
        }

        $recent = DeveloperSandboxFaucetClaim::query()
            ->where('project_id', $project->id)
            ->where('asset', $asset)
            ->where('claimed_at', '>=', now()->subHour())
            ->exists();
        if ($recent) {
            throw new RuntimeException('Sandbox faucet can be claimed once per asset per hour.');
        }

        return DB::transaction(function () use ($amount, $asset, $project): DeveloperSandboxFaucetClaim {
            $balance = DeveloperSandboxBalance::query()
                ->where('project_id', $project->id)
                ->where('asset', $asset)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = DeveloperSandboxBalance::query()->create([
                    'user_id' => $project->user_id,
                    'project_id' => $project->id,
                    'asset' => $asset,
                    'available' => '0',
                    'reserved' => '0',
                    'metadata' => ['source' => 'developer_sandbox_faucet'],
                ]);
            }

            $balance->available = bcadd((string) $balance->available, $amount, 8);
            $balance->save();

            return DeveloperSandboxFaucetClaim::query()->create([
                'user_id' => $project->user_id,
                'project_id' => $project->id,
                'asset' => $asset,
                'amount' => $amount,
                'claimed_at' => now(),
            ]);
        });
    }
}
