<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\InstitutionalAccount;
use App\Models\InstitutionalMembership;
use App\Models\InstitutionalSubaccount;
use App\Models\MarketMakerProfile;
use App\Models\MarketMakerProgramApplication;
use App\Services\MarketMakerInventoryService;
use App\Services\MarketMakerProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MarketMakerController extends Controller
{
    public function __construct(
        private readonly MarketMakerProgramService $program,
        private readonly MarketMakerInventoryService $inventory,
    ) {
    }

    public function overview(Request $request): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $profiles = MarketMakerProfile::query()->where('institution_id', $institution->id)->with('assignments')->latest()->get();

        return response()->json(['data' => [
            'institution' => $institution,
            'applications' => MarketMakerProgramApplication::query()->where('institution_id', $institution->id)->latest()->get(),
            'profiles' => $profiles,
            'readiness' => [
                'dedicated_subaccount_required' => true,
                'uses_institutional_subaccount_ledger' => true,
                'api_access_required' => true,
                'public_program_launch' => 'LIMITED_RELEASE',
            ],
        ]]);
    }

    public function apply(Request $request): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $payload = $request->validate([
            'subaccount_id' => ['required', 'integer'],
            'provider_type' => ['nullable', 'string', 'max:40'],
            'requested_markets' => ['required', 'array', 'min:1'],
            'requested_products' => ['required', 'array', 'min:1'],
            'technical_profile' => ['nullable', 'array'],
            'risk_profile' => ['nullable', 'array'],
            'commercial_terms' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:160'],
        ]);
        $subaccount = InstitutionalSubaccount::query()->where('institution_id', $institution->id)->findOrFail($payload['subaccount_id']);

        return $this->handle(fn () => $this->program->apply($request->user(), $institution, $subaccount, $payload, $request), 201);
    }

    public function capital(Request $request, string $profileUuid, string $symbol): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $profile = MarketMakerProfile::query()->where('institution_id', $institution->id)->where('profile_uuid', $profileUuid)->firstOrFail();

        return response()->json(['data' => $this->program->capitalReadiness($profile, $symbol)]);
    }

    public function inventory(Request $request, string $profileUuid, string $symbol): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $profile = MarketMakerProfile::query()->where('institution_id', $institution->id)->where('profile_uuid', $profileUuid)->firstOrFail();

        return response()->json(['data' => $this->inventory->snapshot($profile, $symbol)], 201);
    }

    private function institutionForUser(Request $request): InstitutionalAccount
    {
        $institution = InstitutionalAccount::query()->where('master_user_id', $request->user()->id)->whereIn('status', ['ACTIVE', 'APPROVED', 'RESTRICTED'])->first();
        if ($institution) {
            return $institution;
        }
        $membership = InstitutionalMembership::query()->where('user_id', $request->user()->id)->where('status', 'ACTIVE')->firstOrFail();

        return InstitutionalAccount::query()->findOrFail($membership->institution_id);
    }

    private function handle(\Closure $callback, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()], $status);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
