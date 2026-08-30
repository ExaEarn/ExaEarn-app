<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeveloperApiKey;
use App\Models\DeveloperApiKeyIpWhitelist;
use App\Models\DeveloperApiKeyPermission;
use App\Models\DeveloperApiRealtimeSession;
use App\Models\DeveloperAuditLog;
use App\Models\DeveloperProject;
use App\Models\InstitutionalAccount;
use App\Models\InstitutionalSubaccount;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;
use RuntimeException;

class DeveloperApiKeyService
{
    public function __construct(
        private readonly CompliancePolicyService $compliance,
        private readonly DeveloperWorkspaceService $workspaces,
        private readonly DeveloperApiScopeRegistry $scopes,
        private readonly DeveloperProductionAccessService $productionAccess,
    )
    {
    }

    public function createProject(int $userId, array $payload): DeveloperProject
    {
        $user = \App\Models\User::query()->findOrFail($userId);
        $workspace = $this->workspaces->ensurePersonalWorkspace($user);
        $project = $this->workspaces->provisionProject($user, $workspace, $payload);

        $this->audit($userId, $project->id, null, 'project.created', 'Developer project created.', ['environment' => $project->environment]);

        return $project;
    }

    public function createKey(int $userId, DeveloperProject $project, array $payload): array
    {
        if ($project->status !== 'active') {
            throw new RuntimeException('API credentials cannot be created for an inactive project.');
        }
        $environment = strtolower((string) ($payload['environment'] ?? 'sandbox'));
        $approved = $environment === 'production' ? $this->productionAccess->approvedCapabilities($project->id) : [];
        $permissions = $this->scopes->validate((array) ($payload['permissions'] ?? []), $environment, $approved);
        if ($permissions === []) throw new RuntimeException('Select at least one API permission.');
        $ipWhitelist = $this->normalizeIpRules((array) ($payload['ip_whitelist'] ?? []));

        if (in_array('wallet.withdraw', $permissions, true)) {
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

        $environmentState = $project->environments()->where('type', $environment)->first();
        if (!$environmentState || $environmentState->status !== 'active') {
            throw new RuntimeException($environment === 'production'
                ? 'Production access is not activated for this project.'
                : 'The requested project environment is not active.');
        }
        if ($environment === 'production') {
            $creator=\App\Models\User::query()->findOrFail($userId);
            if((bool)config('developer_api.production_access.require_two_factor_for_keys',true) && !$creator->two_factor_enabled) throw new RuntimeException('Two-factor authentication is required for Production API keys.');
            $this->productionAccess->assertCapabilities($project->load(['environments','organization','user']), $permissions);
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
        $plainKey = $prefix . bin2hex(random_bytes(24));
        $plainSecret = 'exa_sec_' . bin2hex(random_bytes(48));
        $passphrase = isset($payload['passphrase']) ? (string) $payload['passphrase'] : null;

        return DB::transaction(function () use ($environment, $passphrase, $permissions, $ipWhitelist, $plainKey, $plainSecret, $prefix, $project, $payload, $userId): array {
            DeveloperProject::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $activeCount = DeveloperApiKey::query()->where('project_id',$project->id)->where('environment',$environment)->whereIn('status',['active','disabled'])->count();
            if ($activeCount >= (int) config('developer_api.max_keys_per_project',5)) throw new RuntimeException('API key limit reached for this project environment.');
            $key = DeveloperApiKey::query()->create([
                'key_uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'project_id' => $project->id,
                'created_by' => $userId,
                'institution_id' => $payload['institution_id'] ?? null,
                'subaccount_id' => $payload['subaccount_id'] ?? null,
                'name' => (string) $payload['name'],
                'environment' => $environment,
                'rate_profile' => strtoupper((string) ($payload['rate_profile'] ?? (($payload['institution_id'] ?? null) ? 'INSTITUTIONAL' : 'RETAIL'))),
                'key_prefix' => $prefix,
                'key_hash' => hash('sha256', $plainKey),
                'encrypted_api_key' => Crypt::encryptString($plainKey),
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

            foreach ($ipWhitelist as $cidr) {
                DeveloperApiKeyIpWhitelist::query()->create(['api_key_id' => $key->id, 'cidr' => (string) $cidr]);
            }

            $this->audit($userId, $project->id, $key->id, 'developer.api_key.created', 'Developer API key created.', ['permissions' => $permissions,'environment'=>$environment,'key_uuid'=>$key->key_uuid]);

            return [
                'api_key' => $plainKey,
                'api_secret' => $plainSecret,
                'key' => $key->load(['permissions', 'ipWhitelists']),
            ];
        });
    }

    public function rotateSecret(int $userId, DeveloperApiKey $key): array
    {
        $user = \App\Models\User::query()->findOrFail($userId);
        $this->workspaces->project($user, (int) $key->project_id, 'api_key.update');

        $plainSecret = 'exa_sec_' . bin2hex(random_bytes(48));
        DB::transaction(function()use($key,$plainSecret):void{
            $locked=DeveloperApiKey::query()->whereKey($key->id)->lockForUpdate()->firstOrFail();
            if($locked->status==='revoked')throw new RuntimeException('Revoked API keys cannot be rotated.');
            $locked->update(['encrypted_secret'=>Crypt::encryptString($plainSecret),'secret_hash'=>hash('sha256',$plainSecret),'metadata'=>array_merge($locked->metadata??[],['rotated_at'=>now()->toISOString()])]);
            DeveloperApiRealtimeSession::query()->where('api_key_id',$locked->id)->where('status','active')->update(['status'=>'revoked','revoked_at'=>now()]);
        });

        $this->audit($userId, $key->project_id, $key->id, 'developer.api_key.secret_rotated', 'Developer API key secret rotated.', ['environment'=>$key->environment,'key_uuid'=>$key->key_uuid]);

        return ['api_secret' => $plainSecret, 'key' => $key->fresh(['permissions', 'ipWhitelists'])];
    }

    public function setStatus(int $userId, DeveloperApiKey $key, string $status): DeveloperApiKey
    {
        $user = \App\Models\User::query()->findOrFail($userId);
        $this->workspaces->project($user,(int)$key->project_id,'api_key.revoke');
        if (!in_array($status,['active','disabled'],true)) throw new RuntimeException('Unsupported API key status.');
        DB::transaction(function()use($key,$status):void{$locked=DeveloperApiKey::query()->whereKey($key->id)->lockForUpdate()->firstOrFail();if($locked->status==='revoked')throw new RuntimeException('Revoked API keys cannot be changed.');$locked->update(['status'=>$status,'disabled_at'=>$status==='disabled'?now():null]);if($status==='disabled')DeveloperApiRealtimeSession::query()->where('api_key_id',$locked->id)->where('status','active')->update(['status'=>'revoked','revoked_at'=>now()]);});
        $this->audit($userId,$key->project_id,$key->id,$status==='active'?'developer.api_key.enabled':'developer.api_key.disabled','Developer API key status changed.',['environment'=>$key->environment,'key_uuid'=>$key->key_uuid]);
        return $key->fresh(['permissions','ipWhitelists']);
    }

    public function revoke(int $userId, DeveloperApiKey $key): DeveloperApiKey
    {
        $user = \App\Models\User::query()->findOrFail($userId);
        $this->workspaces->project($user,(int)$key->project_id,'api_key.revoke');
        DB::transaction(function()use($key,$userId):void{$locked=DeveloperApiKey::query()->whereKey($key->id)->lockForUpdate()->firstOrFail();if($locked->status!=='revoked')$locked->update(['status'=>'revoked','revoked_at'=>now(),'revoked_by'=>$userId,'disabled_at'=>now()]);DeveloperApiRealtimeSession::query()->where('api_key_id',$locked->id)->where('status','active')->update(['status'=>'revoked','revoked_at'=>now()]);});
        $this->audit($userId,$key->project_id,$key->id,'developer.api_key.revoked','Developer API key permanently revoked.',['environment'=>$key->environment,'key_uuid'=>$key->key_uuid]);
        return $key->fresh(['permissions','ipWhitelists']);
    }

    public function updatePolicy(int $userId, DeveloperApiKey $key, array $payload): DeveloperApiKey
    {
        $user = \App\Models\User::query()->findOrFail($userId);
        $this->workspaces->project($user,(int)$key->project_id,'api_key.update');
        $approved=$key->environment==='production'?$this->productionAccess->approvedCapabilities($key->project_id):[];
        $permissions=$this->scopes->validate((array)$payload['permissions'],$key->environment,$approved);
        if($key->environment==='production')$this->productionAccess->assertCapabilities($key->project->load(['environments','organization','user']),$permissions);
        if ($permissions===[]) throw new RuntimeException('Select at least one API permission.');
        $rules=$this->normalizeIpRules((array)($payload['ip_whitelist']??[]));
        if (in_array('wallet.withdraw',$permissions,true)&&$rules===[]) throw new RuntimeException('Withdrawal-enabled API keys require an IP whitelist.');
        DB::transaction(function()use($key,$permissions,$rules):void{
            $locked=DeveloperApiKey::query()->whereKey($key->id)->lockForUpdate()->firstOrFail();
            if($locked->status==='revoked')throw new RuntimeException('Revoked API keys cannot be changed.');
            $key->permissions()->delete();
            foreach($permissions as $permission)$key->permissions()->create(['permission'=>$permission]);
            $key->ipWhitelists()->delete();
            foreach($rules as $cidr)$key->ipWhitelists()->create(['cidr'=>$cidr]);
        });
        $this->audit($userId,$key->project_id,$key->id,'developer.api_key.policy_updated','Developer API key scopes or IP policy changed.',['permissions'=>$permissions,'ip_rule_count'=>count($rules),'environment'=>$key->environment]);
        return $key->fresh(['permissions','ipWhitelists']);
    }

    private function normalizeIpRules(array $rules): array
    {
        $normalized=[];
        foreach(array_filter(array_map(fn($rule)=>trim(strtolower((string)$rule)),$rules)) as $rule){
            [$address,$bits]=array_pad(explode('/',$rule,2),2,null);
            $version=filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)!==false?4:(filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)!==false?6:0);
            if($version===0||($bits!==null&&(!ctype_digit($bits)||(int)$bits<0||(int)$bits>($version===4?32:128)))) throw new RuntimeException("Invalid IP allowlist rule: {$rule}");
            $candidate=$bits===null?$address:"{$address}/{$bits}";
            if(!IpUtils::checkIp($address,$candidate)) throw new RuntimeException("Invalid IP allowlist rule: {$rule}");
            $normalized[]=$candidate;
        }
        return array_values(array_unique($normalized));
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
