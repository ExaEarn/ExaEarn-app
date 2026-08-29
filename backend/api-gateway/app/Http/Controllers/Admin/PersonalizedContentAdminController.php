<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PersonalizedContent;
use App\Services\PersonalizedContent\ProductEventContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class PersonalizedContentAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PersonalizedContent::query()->withCount(['interactions as impressions_count' => fn ($q) => $q->where('interaction_type', 'IMPRESSION'), 'interactions as clicks_count' => fn ($q) => $q->where('interaction_type', 'CLICK'), 'interactions as dismissals_count' => fn ($q) => $q->where('interaction_type', 'DISMISS')]);
        if ($request->filled('status')) $query->where('status', strtoupper((string) $request->query('status')));
        if ($request->filled('type')) $query->where('type', strtoupper((string) $request->query('type')));
        return response()->json(['data' => $query->latest()->paginate(min(100, max(1, (int) $request->query('per_page', 25))))]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validated($request);
        $record = PersonalizedContent::query()->create(array_merge($payload, ['content_uuid' => (string) Str::uuid(), 'source_type' => 'ADMIN', 'status' => $payload['status'] ?? 'DRAFT', 'created_by_admin_id' => $request->user()->id]));
        $this->audit($request, 'personalized_content.create', $record);
        return response()->json(['data' => $record], 201);
    }

    public function update(PersonalizedContent $content, Request $request): JsonResponse
    {
        abort_if($content->status === 'ARCHIVED', 409, 'Archived content is immutable. Duplicate it to create a new version.');
        $content->fill($this->validated($request, true))->save();
        $this->audit($request, 'personalized_content.update', $content);
        return response()->json(['data' => $content->fresh()]);
    }

    public function transition(PersonalizedContent $content, Request $request, string $action): JsonResponse
    {
        $status = match (strtolower($action)) { 'publish' => 'PUBLISHED', 'pause' => 'PAUSED', 'unpublish' => 'DRAFT', 'archive' => 'ARCHIVED', 'expire' => 'EXPIRED', default => abort(404) };
        $content->forceFill(['status' => $status, 'publish_at' => $status === 'PUBLISHED' ? ($content->publish_at ?? now()) : $content->publish_at, 'expires_at' => $status === 'EXPIRED' ? now() : $content->expires_at])->save();
        $this->audit($request, "personalized_content.{$action}", $content);
        return response()->json(['data' => $content]);
    }

    public function duplicate(PersonalizedContent $content, Request $request): JsonResponse
    {
        $copy = $content->replicate(['id', 'content_uuid', 'idempotency_key', 'status', 'publish_at', 'expires_at', 'created_at', 'updated_at']);
        $copy->content_uuid = (string) Str::uuid(); $copy->title .= ' (Copy)'; $copy->status = 'DRAFT'; $copy->created_by_admin_id = $request->user()->id; $copy->save();
        $this->audit($request, 'personalized_content.duplicate', $copy);
        return response()->json(['data' => $copy], 201);
    }

    public function ingestEvent(Request $request, ProductEventContentService $events): JsonResponse
    {
        $payload = $request->validate(['event_name' => ['required', 'string', Rule::in(array_keys(config('personalized_content.event_registry')))], 'event_id' => ['required', 'string', 'max:160'], 'payload' => ['required', 'array']]);
        try { $content = $events->ingest($payload['event_name'], $payload['event_id'], $payload['payload']); }
        catch (InvalidArgumentException $exception) { return response()->json(['message' => $exception->getMessage()], 422); }
        $this->audit($request, 'personalized_content.event_ingested', $content);
        return response()->json(['data' => $content], 201);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'type' => [$required, Rule::in(config('personalized_content.types'))], 'title' => [$required, 'string', 'max:160'], 'subtitle' => ['nullable', 'string', 'max:240'], 'body' => ['nullable', 'string', 'max:4000'],
            'image_url' => ['nullable', 'url', 'max:500', 'starts_with:https://'], 'icon' => ['nullable', 'string', 'max:48'], 'badge' => ['nullable', 'string', 'max:48'], 'cta_label' => ['nullable', 'string', 'max:48'], 'cta_route' => ['nullable', Rule::in(config('personalized_content.routes'))], 'cta_payload' => ['nullable', 'array'],
            'related_product' => ['nullable', 'string', 'max:48'], 'related_asset' => ['nullable', 'string', 'max:24'], 'related_entity_type' => ['nullable', 'string', 'max:64'], 'related_entity_id' => ['nullable', 'string', 'max:120'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'], 'severity' => ['nullable', Rule::in(['INFO', 'WARNING', 'HIGH', 'CRITICAL'])], 'status' => ['nullable', Rule::in(['DRAFT', 'SCHEDULED', 'PUBLISHED', 'PAUSED'])],
            'target_interests' => ['nullable', 'array'], 'target_interests.*' => ['string', 'max:64'], 'target_products' => ['nullable', 'array'], 'target_products.*' => ['string', 'max:48'], 'target_assets' => ['nullable', 'array'], 'target_assets.*' => ['string', 'max:24'],
            'target_experience_modes' => ['nullable', 'array'], 'target_experience_modes.*' => [Rule::in(['LITE', 'PRO', 'lite', 'pro'])], 'target_regions' => ['nullable', 'array'], 'target_regions.*' => ['string', 'max:64'], 'target_countries' => ['nullable', 'array'], 'target_countries.*' => ['string', 'size:2'], 'target_user_segments' => ['nullable', 'array'],
            'minimum_kyc_tier' => ['nullable', 'integer', 'min:0', 'max:5'], 'eligibility_rules' => ['nullable', 'array'], 'personalization_weight' => ['nullable', 'integer', 'min:0', 'max:100'], 'frequency_cap' => ['nullable', 'integer', 'min:1', 'max:100'], 'publish_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after:publish_at'],
        ]);
    }

    private function audit(Request $request, string $action, PersonalizedContent $content): void
    {
        AuditLog::query()->create(['user_id' => null, 'action' => $action, 'ip_address' => $request->ip(), 'device' => (string) $request->userAgent(), 'metadata' => ['admin_id' => $request->user()->id, 'content_uuid' => $content->content_uuid, 'status' => $content->status], 'created_at' => now()]);
    }
}
