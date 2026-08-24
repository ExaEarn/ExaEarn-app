<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ListingApplication;
use App\Models\ListingOrganization;
use App\Services\ListingLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ListingPortalController extends Controller
{
    public function __construct(private readonly ListingLifecycleService $listings)
    {
    }

    public function meta(): JsonResponse
    {
        return response()->json(['data' => [
            'application_types' => ['NEW_TOKEN_LISTING', 'ADDITIONAL_NETWORK', 'ADDITIONAL_TRADING_PAIR', 'TOKEN_MIGRATION', 'REBRAND_TICKER_CHANGE', 'NETWORK_MIGRATION'],
            'statuses' => ['DRAFT', 'SUBMITTED', 'INITIAL_REVIEW', 'INFORMATION_REQUESTED', 'DUE_DILIGENCE', 'COMPLIANCE_REVIEW', 'TECHNICAL_REVIEW', 'SECURITY_REVIEW', 'LIQUIDITY_REVIEW', 'FINAL_REVIEW', 'CONDITIONALLY_APPROVED', 'APPROVED', 'INTEGRATION', 'ASSET_CONFIGURATION', 'NETWORK_CONFIGURATION', 'MARKET_CREATED', 'TESTING', 'READY_FOR_LISTING', 'SCHEDULED', 'PRE_LAUNCH', 'LIVE', 'PAUSED', 'SUSPENDED', 'LAUNCH_BLOCKED', 'DELISTING', 'DELISTED'],
            'supported_standards' => ['Native', 'ERC-20', 'SPL', 'BEP-20', 'TRC-20'],
            'warning' => 'ExaEarn does not guarantee listings and does not use unofficial agents or informal payment requests.',
        ]]);
    }

    public function organizations(Request $request): JsonResponse
    {
        return response()->json(['data' => ListingOrganization::query()
            ->where('owner_user_id', $request->user()->id)
            ->with('applications')
            ->latest()
            ->get()]);
    }

    public function createOrganization(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'legal_name' => ['required', 'string', 'max:180'],
            'project_name' => ['required', 'string', 'max:140'],
            'jurisdiction' => ['required', 'string', 'max:120'],
            'website' => ['nullable', 'url', 'max:255'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'registration_details' => ['nullable', 'array'],
            'incorporation_date' => ['nullable', 'date'],
            'registered_address' => ['nullable', 'array'],
            'project_category' => ['nullable', 'string', 'max:80'],
            'primary_contact' => ['nullable', 'array'],
            'authorized_representative' => ['nullable', 'array'],
        ]);

        return response()->json(['data' => $this->listings->createOrganization($request->user(), $payload, $request)], 201);
    }

    public function applications(Request $request): JsonResponse
    {
        $organizationIds = ListingOrganization::query()->where('owner_user_id', $request->user()->id)->pluck('id');

        return response()->json(['data' => ListingApplication::query()
            ->whereIn('organization_id', $organizationIds)
            ->with(['organization', 'reviews', 'assetConfiguration', 'networkConfigurations', 'contractValidations', 'marketConfigurations', 'schedule', 'launchEvents'])
            ->latest()
            ->paginate((int) $request->query('per_page', 25))]);
    }

    public function saveDraft(Request $request, int $organizationId): JsonResponse
    {
        $payload = $request->validate([
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'application_type' => ['required', 'string', 'in:NEW_TOKEN_LISTING,ADDITIONAL_NETWORK,ADDITIONAL_TRADING_PAIR,TOKEN_MIGRATION,REBRAND_TICKER_CHANGE,NETWORK_MIGRATION'],
            'project_information' => ['nullable', 'array'],
            'asset_information' => ['nullable', 'array'],
            'blockchain_information' => ['nullable', 'array'],
            'tokenomics' => ['nullable', 'array'],
            'technology' => ['nullable', 'array'],
            'security' => ['nullable', 'array'],
            'legal_compliance' => ['nullable', 'array'],
            'market_community' => ['nullable', 'array'],
            'liquidity' => ['nullable', 'array'],
            'listing_request' => ['nullable', 'array'],
        ]);

        try {
            $application = $this->listings->saveDraft($request->user(), ListingOrganization::query()->findOrFail($organizationId), $payload, $request);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $application], $application->wasRecentlyCreated ? 201 : 200);
    }

    public function submit(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate(['authorized_declaration' => ['required', 'accepted']]);
        $application = ListingApplication::query()->where('reference', $reference)->with('organization')->firstOrFail();

        try {
            return response()->json(['data' => $this->listings->submit($request->user(), $application, $payload, $request)], 202);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        $organizationIds = ListingOrganization::query()->where('owner_user_id', $request->user()->id)->pluck('id');
        $application = ListingApplication::query()
            ->where('reference', $reference)
            ->whereIn('organization_id', $organizationIds)
            ->with(['organization', 'reviews', 'assetConfiguration', 'networkConfigurations', 'contractValidations', 'marketConfigurations', 'schedule', 'launchEvents'])
            ->firstOrFail();

        return response()->json(['data' => $application]);
    }

    public function message(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate(['subject' => ['nullable', 'string', 'max:140'], 'body' => ['required', 'string', 'max:5000']]);
        $application = ListingApplication::query()->where('reference', $reference)->with('organization')->firstOrFail();

        if ((int) $application->organization->owner_user_id !== (int) $request->user()->id) {
            abort(404);
        }

        try {
            $message = $this->listings->sendMessage($application, 'user', (int) $request->user()->id, $payload, $request);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $message], 201);
    }
}
