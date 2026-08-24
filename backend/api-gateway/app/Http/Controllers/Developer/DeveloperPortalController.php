<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\DeveloperApiKey;
use App\Models\DeveloperProject;
use App\Models\DeveloperWebhookDelivery;
use App\Models\DeveloperWebhookEndpoint;
use App\Services\DeveloperApiKeyService;
use App\Services\DeveloperWebhookService;
use App\Services\DeveloperSandboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DeveloperPortalController extends Controller
{
    public function __construct(
        private readonly DeveloperApiKeyService $keys,
        private readonly DeveloperSandboxService $sandbox,
        private readonly DeveloperWebhookService $webhookService,
    ) {
    }

    public function projects(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => DeveloperProject::query()->where('user_id', $request->user()->id)->latest()->get()]);
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
        $project = DeveloperProject::query()->where('user_id', $request->user()->id)->findOrFail($projectId);
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

    public function rotateKey(Request $request, int $keyId): JsonResponse
    {
        $key = DeveloperApiKey::query()->where('user_id', $request->user()->id)->findOrFail($keyId);
        return response()->json(['success' => true, 'data' => $this->keys->rotateSecret((int) $request->user()->id, $key)]);
    }

    public function disableKey(Request $request, int $keyId): JsonResponse
    {
        $key = DeveloperApiKey::query()->where('user_id', $request->user()->id)->findOrFail($keyId);
        $key->update(['status' => 'disabled']);
        $this->keys->audit((int) $request->user()->id, $key->project_id, $key->id, 'api_key.disabled', 'Developer API key disabled.', []);
        return response()->json(['success' => true, 'data' => $key->fresh()]);
    }

    public function faucet(Request $request, int $projectId): JsonResponse
    {
        $project = DeveloperProject::query()->where('user_id', $request->user()->id)->findOrFail($projectId);
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

    public function webhooks(Request $request, int $projectId): JsonResponse
    {
        $project = DeveloperProject::query()->where('user_id', $request->user()->id)->findOrFail($projectId);
        return response()->json(['success' => true, 'data' => DeveloperWebhookEndpoint::query()->where('project_id', $project->id)->latest()->get()]);
    }

    public function createWebhook(Request $request, int $projectId): JsonResponse
    {
        $project = DeveloperProject::query()->where('user_id', $request->user()->id)->findOrFail($projectId);
        $allowedEvents = implode(',', (array) config('developer_api.webhooks.events', []));
        $payload = $request->validate([
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1', 'max:20'],
            'events.*' => ['required', 'string', 'in:' . $allowedEvents],
        ]);

        try {
            return response()->json(['success' => true, 'data' => $this->webhookService->register($project, $payload)], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function rotateWebhookSecret(Request $request, int $endpointId): JsonResponse
    {
        $endpoint = DeveloperWebhookEndpoint::query()->where('user_id', $request->user()->id)->findOrFail($endpointId);
        return response()->json(['success' => true, 'data' => $this->webhookService->rotateSecret($endpoint)]);
    }

    public function deliveries(Request $request, int $projectId): JsonResponse
    {
        $project = DeveloperProject::query()->where('user_id', $request->user()->id)->findOrFail($projectId);
        $endpointIds = DeveloperWebhookEndpoint::query()->where('project_id', $project->id)->pluck('id');

        return response()->json(['success' => true, 'data' => DeveloperWebhookDelivery::query()->whereIn('endpoint_id', $endpointIds)->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function replayDelivery(Request $request, int $deliveryId): JsonResponse
    {
        $delivery = DeveloperWebhookDelivery::query()
            ->whereHas('endpoint', fn ($query) => $query->where('user_id', $request->user()->id))
            ->findOrFail($deliveryId);

        return response()->json(['success' => true, 'data' => $this->webhookService->replay($delivery)], 201);
    }
}
