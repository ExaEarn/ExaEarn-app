<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class DeveloperApiScopeRegistry
{
    public function all(): array
    {
        return (array) config('developer_api.scope_registry', []);
    }

    public function validate(array $requested, string $environment, array $approvedProductionScopes = []): array
    {
        $scopes = array_values(array_unique(array_map('strtolower', $requested)));
        foreach ($scopes as $scope) {
            $definition = $this->all()[$scope] ?? null;
            if (!$definition) throw new RuntimeException("Unsupported API permission: {$scope}");
            if (!in_array($environment, (array) ($definition['environments'] ?? []), true)) throw new RuntimeException("API permission is unavailable in {$environment}: {$scope}");
            if ($environment === 'production' && (($definition['production_approval_required'] ?? false) === true) && !in_array($scope,$approvedProductionScopes,true)) throw new RuntimeException("Production approval is required for API permission: {$scope}");
        }
        return $scopes;
    }

    public function public(): array
    {
        return collect($this->all())->map(fn(array $item,string $scope)=>['scope'=>$scope]+$item)->values()->all();
    }
}
