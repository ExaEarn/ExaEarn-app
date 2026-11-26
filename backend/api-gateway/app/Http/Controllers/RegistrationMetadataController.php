<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ComplianceJurisdiction;
use Illuminate\Http\JsonResponse;

class RegistrationMetadataController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $countries = ComplianceJurisdiction::query()
            ->whereIn('status', ['ACTIVE', 'ALLOWED', 'SUPPORTED', 'RESTRICTED'])
            ->where(function ($query): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', now());
            })
            ->orderBy('country_name')
            ->get(['country_code', 'country_name', 'status'])
            ->map(fn (ComplianceJurisdiction $country): array => [
                'code' => $country->country_code,
                'name' => $country->country_name,
                'registration_available' => in_array($country->status, ['ACTIVE', 'ALLOWED', 'SUPPORTED'], true),
            ]);

        return response()->json([
            'data' => [
                'countries' => $countries,
                'terms_version' => (string) config('listing.account_terms_version'),
                'listing_terms_version' => (string) config('listing.portal_terms_version'),
            ],
        ]);
    }
}
