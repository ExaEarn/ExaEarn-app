<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SreDependencyCheck;
use App\Models\SreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SreDependencyHealthService
{
    public function check(string $serviceId, string $dependency, string $type, string $status, array $evidence = [], ?int $latencyMs = null): SreDependencyCheck
    {
        return SreDependencyCheck::query()->create([
            'check_uuid' => (string) Str::uuid(),
            'service_id' => $serviceId,
            'dependency' => $dependency,
            'dependency_type' => strtoupper($type),
            'status' => strtoupper($status),
            'latency_ms' => $latencyMs,
            'evidence' => $evidence,
            'checked_at' => now(),
        ]);
    }

    public function checkDatabase(): SreDependencyCheck
    {
        $started = microtime(true);
        try {
            DB::select('select 1');
            return $this->check('api-gateway', 'postgresql', 'DATABASE', 'PASS', ['connection' => config('database.default')], (int) ((microtime(true) - $started) * 1000));
        } catch (\Throwable $e) {
            return $this->check('api-gateway', 'postgresql', 'DATABASE', 'FAIL', ['error' => $e->getMessage()], (int) ((microtime(true) - $started) * 1000));
        }
    }

    public function latestByDependency(): array
    {
        return SreDependencyCheck::query()
            ->latest('checked_at')
            ->get()
            ->unique(fn ($check) => $check->service_id.'|'.$check->dependency)
            ->values()
            ->all();
    }

    public function graph(): array
    {
        return SreService::query()->get()->mapWithKeys(fn (SreService $service) => [
            $service->service_id => $service->dependencies ?? [],
        ])->all();
    }
}
