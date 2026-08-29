<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PersonalizedContent;
use App\Services\PersonalizedContent\PersonalizedContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PersonalizedContentController extends Controller
{
    public function __construct(private readonly PersonalizedContentService $content) {}

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->content->dashboard($request->user()), 'meta' => ['surface' => 'DASHBOARD', 'generated_at' => now()->toISOString()]]);
    }

    public function feed(Request $request): JsonResponse
    {
        $payload = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'type' => ['nullable', Rule::in(config('personalized_content.types'))]]);
        return response()->json(['data' => $this->content->feed($request->user(), (int) ($payload['page'] ?? 1), $payload['type'] ?? null)]);
    }

    public function interact(PersonalizedContent $content, Request $request, string $interaction): JsonResponse
    {
        $interaction = strtoupper($interaction);
        abort_unless(in_array($interaction, ['IMPRESSION', 'CLICK', 'DISMISS', 'SAVE'], true), 404);
        $payload = $request->validate(['event_uuid' => ['nullable', 'uuid'], 'surface' => ['nullable', 'string', 'max:32'], 'position' => ['nullable', 'integer', 'min:0', 'max:1000']]);
        $record = $this->content->interact($content, $request->user(), $interaction, $payload);
        return response()->json(['data' => ['event_uuid' => $record->event_uuid, 'interaction' => $record->interaction_type]], 201);
    }
}
