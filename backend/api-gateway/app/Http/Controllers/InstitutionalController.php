<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\InstitutionalAccount;
use App\Models\InstitutionalApplication;
use App\Models\InstitutionalMembership;
use App\Models\InstitutionalSubaccount;
use App\Models\InstitutionalTransferRequest;
use App\Services\InstitutionalService;
use App\Services\InstitutionalRealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class InstitutionalController extends Controller
{
    public function __construct(
        private readonly InstitutionalService $institutions,
        private readonly InstitutionalRealtimeService $realtime,
    )
    {
    }

    public function apply(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'legal_company_name' => ['required', 'string', 'max:180'],
            'trading_name' => ['nullable', 'string', 'max:160'],
            'incorporation_country' => ['required', 'string', 'max:120'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'business_type' => ['required', 'string', 'max:80'],
            'website' => ['nullable', 'url', 'max:255'],
            'contact_person' => ['required', 'string', 'max:140'],
            'business_email' => ['required', 'email', 'max:180'],
            'expected_monthly_spot_volume' => ['nullable', 'numeric', 'gte:0'],
            'expected_monthly_futures_volume' => ['nullable', 'numeric', 'gte:0'],
            'expected_assets_under_custody' => ['nullable', 'numeric', 'gte:0'],
            'team_size' => ['nullable', 'integer', 'min:1'],
            'intended_products' => ['nullable', 'array'],
            'api_requirements' => ['nullable', 'array'],
            'market_making_interest' => ['nullable', 'boolean'],
            'otc_interest' => ['nullable', 'boolean'],
            'fiat_requirements' => ['nullable', 'array'],
            'subaccount_requirements' => ['nullable', 'array'],
        ]);

        return response()->json(['data' => $this->institutions->apply($request->user(), $payload, $request)], 201);
    }

    public function applications(Request $request): JsonResponse
    {
        return response()->json(['data' => InstitutionalApplication::query()->where('user_id', $request->user()->id)->latest()->get()]);
    }

    public function overview(Request $request): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $report = $this->institutions->report($institution, $request->user());

        return response()->json(['data' => [
            'institution' => $institution->load(['subaccounts', 'memberships.role']),
            'summary' => $report->summary,
        ]]);
    }

    public function subaccounts(Request $request): JsonResponse
    {
        $institution = $this->institutionForUser($request);

        return response()->json(['data' => $institution->subaccounts()->latest()->get()]);
    }

    public function createSubaccount(Request $request): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', 'in:GENERAL,SPOT,FUTURES,MARGIN,TREASURY,MARKET_MAKER,COPY_TRADING,EXAAI,API_TRADING'],
            'product_flags' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);

        return $this->handle(fn () => $this->institutions->createSubaccount($request->user(), $institution, $payload, $request), 201);
    }

    public function transfer(Request $request): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $payload = $request->validate([
            'source_subaccount_id' => ['nullable', 'integer'],
            'destination_subaccount_id' => ['nullable', 'integer', 'different:source_subaccount_id'],
            'source_subaccount_uuid' => ['nullable', 'string', 'max:80'],
            'destination_subaccount_uuid' => ['nullable', 'string', 'max:80', 'different:source_subaccount_uuid'],
            'asset' => ['required', 'string', 'max:24'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'idempotency_key' => ['required', 'string', 'max:160'],
            'approval_threshold' => ['nullable', 'numeric', 'gte:0'],
            'reference_note' => ['nullable', 'string', 'max:500'],
        ]);
        if (! isset($payload['source_subaccount_id']) && ! isset($payload['source_subaccount_uuid'])) {
            return response()->json(['message' => 'Source subaccount is required.'], 422);
        }
        if (! isset($payload['destination_subaccount_id']) && ! isset($payload['destination_subaccount_uuid'])) {
            return response()->json(['message' => 'Destination subaccount is required.'], 422);
        }
        $source = InstitutionalSubaccount::query()->where('institution_id', $institution->id)
            ->when(isset($payload['source_subaccount_uuid']), fn ($query) => $query->where('subaccount_uuid', $payload['source_subaccount_uuid']))
            ->when(isset($payload['source_subaccount_id']), fn ($query) => $query->whereKey($payload['source_subaccount_id']))
            ->firstOrFail();
        $destination = InstitutionalSubaccount::query()->where('institution_id', $institution->id)
            ->when(isset($payload['destination_subaccount_uuid']), fn ($query) => $query->where('subaccount_uuid', $payload['destination_subaccount_uuid']))
            ->when(isset($payload['destination_subaccount_id']), fn ($query) => $query->whereKey($payload['destination_subaccount_id']))
            ->firstOrFail();

        return $this->handle(fn () => $this->institutions->createTransfer($request->user(), $institution, $source, $destination, $payload, $request), 201);
    }

    public function approveTransfer(Request $request, string $transferUuid): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $payload = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $transfer = InstitutionalTransferRequest::query()->where('institution_id', $institution->id)->where('transfer_uuid', $transferUuid)->firstOrFail();

        return $this->handle(fn () => $this->institutions->approveTransfer($request->user(), $transfer, $payload['reason'], $request));
    }

    public function transfers(Request $request): JsonResponse
    {
        $institution = $this->institutionForUser($request);

        return response()->json(['data' => InstitutionalTransferRequest::query()->where('institution_id', $institution->id)->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function grantPermission(Request $request): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $payload = $request->validate([
            'membership_id' => ['required', 'integer'],
            'subaccount_id' => ['required', 'integer'],
            'permission' => ['required', 'string', 'max:80'],
        ]);
        $membership = InstitutionalMembership::query()->where('institution_id', $institution->id)->findOrFail($payload['membership_id']);
        $subaccount = InstitutionalSubaccount::query()->where('institution_id', $institution->id)->findOrFail($payload['subaccount_id']);

        return $this->handle(fn () => $this->institutions->grantSubaccountPermission($request->user(), $membership, $subaccount, strtoupper($payload['permission']), $request), 201);
    }

    public function realtimeReplay(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'stream' => ['required', 'string', 'max:120'],
            'after_sequence' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return response()->json(['data' => $this->realtime->replay(
            (int) $request->user()->id,
            (string) $payload['stream'],
            (int) ($payload['after_sequence'] ?? 0),
            (int) ($payload['limit'] ?? 200),
        )]);
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
