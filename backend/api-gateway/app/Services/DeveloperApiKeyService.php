<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeveloperApiKey;
use App\Models\DeveloperApiKeyIpWhitelist;
use App\Models\DeveloperApiKeyPermission;
use App\Models\DeveloperAuditLog;
use App\Models\DeveloperProject;
use App\Models\InstitutionalAccount;
use App\Models\InstitutionalSubaccount;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DeveloperApiKeyService
{
    public function __construct(private readonly CompliancePolicyService $compliance)
    {
    }

    public function createProject(int $userId, array $payload): DeveloperProject
    {
        $project = DeveloperProject::query()->create([
            'project_uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'name' => (string) $payload['name'],
            'description' => $payload['description'] ?? null,
            'environment' => strtolower((string) ($payload['environment'] ?? 'sandbox')),
            'status' => 'active',
            'tier' => 'standard',
            'settings' => [],
        ]);

        $this->audit($userId, $project->id, null, 'project.created', 'Developer project created.', ['environment' => $project->environment]);

        return $project;
    }

    public function createKey(int $userId, DeveloperProject $project, array $payload): array
    {
        $activeCount = DeveloperApiKey::query()
            ->where('project_id', $project->id)
            ->where('status', 'active')
            ->count();
        if ($activeCount >= (int) config('developer_api.max_keys_per_project', 5)) {
            throw new RuntimeException('API key limit reached for this project.');
        }

        $permissions = array_values(array_unique((array) ($payload['permissions'] ?? [])));
        $allowed = (array) config('developer_api.permissions', []);
        foreach ($permissions as $permission) {
            if (! in_array($permission, $allowed, true)) {
                throw new RuntimeException("Unsupported API permission: {$permission}");
            }
        }

        if (in_array('wallet.withdraw', $permissions, true)) {
            $ipWhitelist = array_filter((array) ($payload['ip_whitelist'] ?? []));
            if ($ipWhitelist === []) {
                throw new RuntimeException('Withdrawal-enabled API keys require an IP whitelist.');
            }
        }

        $requiresTradingAccess = collect($permissions)->contains(fn (string $permission): bool => str_contains($permission, 'trade') || str_starts_with($permission, 'spot.') || str_starts_with($permission, 'futures.') || str_starts_with($permission, 'margin.'));
        if ($requiresTradingAccess || in_array('wallet.withdraw', $permissions, true)) {
            $policy = $this->compliance->decide(\App\Models\User::query()->find($userId), 'API_TRADING', [
                'action' => 'CREATE_KEY',
                'account_type' => ($payload['institution_id'] ?? null) ? 'INSTITUTIONAL' : 'INDIVIDUAL',
                'institution_id' => $payload['institution_id'] ?? null,
            ]);
            if (! in_array($policy['decision'], [CompliancePolicyService::ALLOW, 'RESTRICT'], true)) {
                throw new RuntimeException('Compliance policy rejected developer API trading access: '.$policy['reason_code']);
            }
        }

        $environment = strtolower((string) ($payload['environment'] ?? $project->environment));
        if ($environment !== $project->environment) {
            throw new RuntimeException('API key environment must match the developer project environment.');
        }

        $institutionId = $payload['institution_id'] ?? null;
        $subaccountId = $payload['subaccount_id'] ?? null;
        if ($institutionId !== null || $subaccountId !== null) {
            $institution = InstitutionalAccount::query()->whereKey($institutionId)->where('master_user_id', $userId)->whereIn('status', ['ACTIVE', 'APPROVED'])->first();
            if (! $institution) {
                throw new RuntimeException('Institutional API keys require an active institution owned by the project user.');
            }
            if ($subaccountId !== null) {
                $subaccount = InstitutionalSubaccount::query()->whereKey($subaccountId)->where('institution_id', $institution->id)->where('status', 'ACTIVE')->first();
                if (! $subaccount) {
                    throw new RuntimeException('Institutional API key subaccount is invalid or inactive.');
                }
            }
        }

        $prefix = $environment === 'production' ? 'exa_live_' : 'exa_test_';
        $plainKey = $prefix . Str::random(32);
        $plainSecret = 'exa_sec_' . Str::random(64);
        $passphrase = isset($payload['passphrase']) ? (string) $payload['passphrase'] : null;

        return DB::transaction(function () use ($environment, $passphrase, $permissions, $plainKey, $plainSecret, $prefix, $project, $payload, $userId): array {
            $key = DeveloperApiKey::query()->create([
                'key_uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'project_id' => $project->id,
                'institution_id' => $payload['institution_id'] ?? null,
                'subaccount_id' => $payload['subaccount_id'] ?? null,
                'name' => (string) $payload['name'],
                'environment' => $environment,
                'rate_profile' => strtoupper((string) ($payload['rate_profile'] ?? (($payload['institution_id'] ?? null) ? 'INSTITUTIONAL' : 'RETAIL'))),
                'key_prefix' => $prefix,
                'key_hash' => hash('sha256', $plainKey),
                'encrypted_secret' => Crypt::encryptString($plainSecret),
                'secret_hash' => hash('sha256', $plainSecret),
                'passphrase_hash' => $passphrase ? hash('sha256', $passphrase) : null,
                'status' => 'active',
                'expires_at' => $payload['expires_at'] ?? null,
                'metadata' => ['secret_shown_once' => true],
            ]);

            foreach ($permissions as $permission) {
                DeveloperApiKeyPermission::query()->create(['api_key_id' => $key->id, 'permission' => $permission]);
            }

            foreach (array_filter((array) ($payload['ip_whitelist'] ?? [])) as $cidr) {
                DeveloperApiKeyIpWhitelist::query()->create(['api_key_id' => $key->id, 'cidr' => (string) $cidr]);
            }

            $this->audit($userId, $project->id, $key->id, 'api_key.created', 'Developer API key created.', ['permissions' => $permissions]);

            return [
                'api_key' => $plainKey,
                'api_secret' => $plainSecret,
                'key' => $key->load(['permissions', 'ipWhitelists']),
            ];
        });
    }

    public function rotateSecret(int $userId, DeveloperApiKey $key): array
    {
        if ($key->user_id !== $userId) {
            throw new RuntimeException('API key not found.');
        }

        $plainSecret = 'exa_sec_' . Str::random(64);
        $key->update([
            'encrypted_secret' => Crypt::encryptString($plainSecret),
            'secret_hash' => hash('sha256', $plainSecret),
            'metadata' => array_merge($key->metadata ?? [], ['rotated_at' => now()->toISOString()]),
        ]);

        $this->audit($userId, $key->project_id, $key->id, 'api_key.secret_rotated', 'Developer API key secret rotated.', []);

        return ['api_secret' => $plainSecret, 'key' => $key->fresh(['permissions', 'ipWhitelists'])];
    }

    public function audit(?int $userId, ?int $projectId, ?int $apiKeyId, string $eventType, string $message, array $context): void
    {
        DeveloperAuditLog::query()->create([
            'user_id' => $userId,
            'project_id' => $projectId,
            'api_key_id' => $apiKeyId,
            'event_type' => $eventType,
            'severity' => 'info',
            'message' => $message,
            'context' => $context,
            'created_at' => now(),
        ]);
    }
}
