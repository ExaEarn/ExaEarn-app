<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\DeveloperApiKey;
use App\Models\DeveloperProject;
use App\Models\DeveloperProfile;
use App\Models\DeveloperOrganization;
use App\Models\DeveloperOrganizationMembership;
use App\Models\DeveloperWebhookDelivery;
use App\Models\DeveloperWebhookEndpoint;
use App\Services\DeveloperApiKeyService;
use App\Services\DeveloperWebhookService;
use App\Services\DeveloperSandboxService;
use App\Services\DeveloperSandboxExplorerService;
use App\Services\DeveloperWorkspaceService;
use App\Services\DeveloperApiScopeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DeveloperPortalController extends Controller
{
    public function __construct(
        private readonly DeveloperApiKeyService $keys,
        private readonly DeveloperSandboxService $sandbox,
        private readonly DeveloperSandboxExplorerService $explorer,
        private readonly DeveloperWebhookService $webhookService,
        private readonly DeveloperWorkspaceService $workspaces,
        private readonly DeveloperApiScopeRegistry $scopeRegistry,
    ) {
    }

    public function session(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = DeveloperProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'active', 'onboarding_status' => 'not_started'],
        );
        $created = $profile->wasRecentlyCreated;
        $profile->forceFill(['last_login_at' => now()])->save();

        if ($created) {
            $this->keys->audit((int) $user->id, null, null, 'developer_profile.created', 'Developer profile initialized.', []);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'unique_user_id' => $user->unique_user_id,
                    'email_verified' => $user->email_verified_at !== null,
                    'two_factor_enabled' => (bool) $user->two_factor_enabled,
                ],
                'developer_profile' => $profile->fresh(),
                'environments' => ['sandbox' => 'active', 'production' => 'not_activated'],
            ],
        ]);
    }

    public function onboard(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasVerifiedEmail()) {
            return response()->json(['success' => false, 'error' => [
                'code' => 'EMAIL_UNVERIFIED',
                'message' => 'Verify your email before creating Developer credentials.',
            ]], 403);
        }

        $payload = $request->validate([
            'developer_type' => ['required', 'string', 'in:individual,organization'],
            'use_case' => ['nullable', 'string', 'in:trading_application,trading_bot,wallet_payments,market_data,fintech,institutional,learning,other'],
            'organization_name' => ['required_if:developer_type,organization', 'nullable', 'string', 'max:140'],
            'project_name' => ['required', 'string', 'max:120'],
            'terms_accepted' => ['accepted'],
        ]);

        $result = DB::transaction(function () use ($payload, $user): array {
            $profile = DeveloperProfile::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($profile->onboarding_status === 'completed' && $profile->default_project_id) {
                return ['profile' => $profile, 'project' => DeveloperProject::query()->findOrFail($profile->default_project_id)];
            }

            $organization = null;
            $workspace = $this->workspaces->ensurePersonalWorkspace($user);
            if ($payload['developer_type'] === 'organization') {
                $organization = $this->workspaces->createOrganization($user, trim((string) $payload['organization_name']));
                $workspace = \App\Models\DeveloperWorkspace::query()->findOrFail($organization->workspace_id);
            }

            $project = $this->workspaces->provisionProject($user, $workspace, [
                'name' => trim((string) $payload['project_name']),
                'description' => 'Created during Developer onboarding.',
            ]);
            $profile->forceFill([
                'developer_type' => $payload['developer_type'], 'use_case' => $payload['use_case'] ?? null,
                'default_organization_id' => $organization?->id, 'default_project_id' => $project->id,
                'developer_terms_accepted_at' => now(), 'onboarding_status' => 'completed',
            ])->save();
            $this->keys->audit((int) $user->id, $project->id, null, 'developer_onboarding.completed', 'Developer onboarding completed.', ['developer_type' => $payload['developer_type']]);

            return ['profile' => $profile->fresh(), 'project' => $project, 'organization' => $organization];
        });

        return response()->json(['success' => true, 'data' => $result], 201);
    }

    public function projects(Request $request): JsonResponse
    {
        $workspaceIds = $this->workspaces->workspaces($request->user())->pluck('id');
        return response()->json(['success' => true, 'data' => DeveloperProject::query()->with('environments')->whereIn('workspace_id', $workspaceIds)->latest()->get()]);
    }

    public function createProject(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'environment' => ['nullable', 'string', 'in:sandbox,production'],
        ]);

        return response()->json(['success' => true, 'data' => $this->keys->createProject((int) $request->user()->id, $payload)], 201);
    }

    public function createKey(Request $request, int $projectId): JsonResponse
    {
        $project = $this->workspaces->project($request->user(), $projectId, 'api_key.create');
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'environment' => ['nullable', 'string', 'in:sandbox,production'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'max:80'],
            'ip_whitelist' => ['nullable', 'array'],
            'ip_whitelist.*' => ['string', 'max:80'],
            'passphrase' => ['nullable', 'string', 'max:120'],
            'expires_at' => ['nullable', 'date'],
        ]);

        try {
            return response()->json(['success' => true, 'data' => $this->keys->createKey((int) $request->user()->id, $project, $payload)], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function scopes(): JsonResponse
    {
        return response()->json(['success'=>true,'data'=>$this->scopeRegistry->public()]);
    }

    public function keys(Request $request,int $projectId): JsonResponse
    {
        $project=$this->workspaces->project($request->user(),$projectId,'api_key.read');
        return response()->json(['success'=>true,'data'=>DeveloperApiKey::query()->with(['permissions','ipWhitelists'])->where('project_id',$project->id)->latest()->get()]);
    }

    public function rotateKey(Request $request, int $keyId): JsonResponse
    {
        $key = DeveloperApiKey::query()->findOrFail($keyId);
        $this->workspaces->project($request->user(), (int) $key->project_id, 'api_key.update');
        return response()->json(['success' => true, 'data' => $this->keys->rotateSecret((int) $request->user()->id, $key)]);
    }

    public function rotateCredential(Request $request,string $keyUuid): JsonResponse
    {
        $key=DeveloperApiKey::query()->where('key_uuid',$keyUuid)->firstOrFail();
        try{return response()->json(['success'=>true,'data'=>$this->keys->rotateSecret((int)$request->user()->id,$key)]);}catch(RuntimeException $exception){return response()->json(['success'=>false,'message'=>$exception->getMessage()],422);}
    }

    public function disableKey(Request $request, int $keyId): JsonResponse
    {
        $key = DeveloperApiKey::query()->findOrFail($keyId);
        $this->workspaces->project($request->user(), (int) $key->project_id, 'api_key.revoke');
        try{return response()->json(['success'=>true,'data'=>$this->keys->setStatus((int)$request->user()->id,$key,'disabled')]);}catch(RuntimeException $exception){return response()->json(['success'=>false,'message'=>$exception->getMessage()],422);}
    }

    public function enableKey(Request $request,string $keyUuid): JsonResponse
    {
        $key=DeveloperApiKey::query()->where('key_uuid',$keyUuid)->firstOrFail();
        try{return response()->json(['success'=>true,'data'=>$this->keys->setStatus((int)$request->user()->id,$key,'active')]);}catch(RuntimeException $exception){return response()->json(['success'=>false,'message'=>$exception->getMessage()],422);}
    }

    public function revokeKey(Request $request,string $keyUuid): JsonResponse
    {
        $key=DeveloperApiKey::query()->where('key_uuid',$keyUuid)->firstOrFail();
        try{return response()->json(['success'=>true,'data'=>$this->keys->revoke((int)$request->user()->id,$key)]);}catch(RuntimeException $exception){return response()->json(['success'=>false,'message'=>$exception->getMessage()],422);}
    }

    public function updateKeyPolicy(Request $request,string $keyUuid): JsonResponse
    {
        $key=DeveloperApiKey::query()->where('key_uuid',$keyUuid)->firstOrFail();
        $payload=$request->validate(['permissions'=>['required','array','min:1'],'permissions.*'=>['string','max:80'],'ip_whitelist'=>['nullable','array'],'ip_whitelist.*'=>['string','max:80']]);
        try{return response()->json(['success'=>true,'data'=>$this->keys->updatePolicy((int)$request->user()->id,$key,$payload)]);}catch(RuntimeException $exception){return response()->json(['success'=>false,'message'=>$exception->getMessage()],422);}
    }

    public function faucet(Request $request, int $projectId): JsonResponse
    {
        $project = $this->workspaces->project($request->user(), $projectId, 'project.update');
        $payload = $request->validate([
            'asset' => ['required', 'string', 'max:20'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
        ]);

        try {
            return response()->json(['success' => true, 'data' => $this->sandbox->faucet($project, $payload['asset'], isset($payload['amount']) ? (string) $payload['amount'] : null)], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function executeSandboxRequest(Request $request, int $projectId): JsonResponse
    {
        $project = $this->workspaces->project($request->user(), $projectId, 'project.update');
        $payload = $request->validate([
            'api_key_id' => ['required', 'integer'],
            'method' => ['required', 'string', 'in:GET,POST,PATCH,DELETE'],
            'path' => ['required', 'string', 'max:500', 'starts_with:/api/developer/v1/'],
            'body' => ['nullable', 'array'],
        ]);
        $key = DeveloperApiKey::query()
            ->where('user_id', $request->user()->id)
            ->where('project_id', $project->id)
            ->findOrFail((int) $payload['api_key_id']);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->explorer->execute($project, $key, $payload, (string) ($request->ip() ?: '127.0.0.1')),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'SANDBOX_EXPLORER_REJECTED', 'message' => $exception->getMessage()],
            ], 422);
        }
    }

    public function webhooks(Request $request, int $projectId): JsonResponse
    {
        $project = $this->workspaces->project($request->user(), $projectId, 'webhook.read');
        return response()->json(['success' => true, 'data' => DeveloperWebhookEndpoint::query()->where('project_id', $project->id)->latest()->get()]);
    }

    public function createWebhook(Request $request, int $projectId): JsonResponse
    {
        $project = $this->workspaces->project($request->user(), $projectId, 'webhook.create');
        $allowedEvents = implode(',', (array) config('developer_api.webhooks.events', []));
        $payload = $request->validate([
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1', 'max:20'],
            'events.*' => ['required', 'string', 'in:' . $allowedEvents],
            'environment'=>['nullable','string','in:sandbox,production'],
        ]);

        try {
            return response()->json(['success' => true, 'data' => $this->webhookService->register($project, $payload)], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function rotateWebhookSecret(Request $request, int $endpointId): JsonResponse
    {
        $endpoint = DeveloperWebhookEndpoint::query()->findOrFail($endpointId);
        $this->workspaces->project($request->user(), (int) $endpoint->project_id, 'webhook.update');
        return response()->json(['success' => true, 'data' => $this->webhookService->rotateSecret($endpoint)]);
    }

    public function deliveries(Request $request, int $projectId): JsonResponse
    {
        $project = $this->workspaces->project($request->user(), $projectId, 'webhook.read');
        $endpointIds = DeveloperWebhookEndpoint::query()->where('project_id', $project->id)->pluck('id');

        return response()->json(['success' => true, 'data' => DeveloperWebhookDelivery::query()->whereIn('endpoint_id', $endpointIds)->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function replayDelivery(Request $request, int $deliveryId): JsonResponse
    {
        $delivery = DeveloperWebhookDelivery::query()->with('endpoint')->findOrFail($deliveryId);
        $this->workspaces->project($request->user(), (int) $delivery->endpoint->project_id, 'webhook.update');

        return response()->json(['success' => true, 'data' => $this->webhookService->replay($delivery)], 201);
    }
}
