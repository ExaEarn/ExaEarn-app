<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\DashboardExperienceRegistry;
use App\Services\ExperienceRecommendationService;
use App\Services\ServiceCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserPreferenceController extends Controller
{
    public function __construct(
        private readonly ExperienceRecommendationService $recommendations,
        private readonly ServiceCatalogService $serviceCatalog,
    ) {}
    private const CURRENCIES = ['USD', 'NGN', 'EUR', 'GBP', 'BTC', 'ETH'];

    private const SUPPORTED_LANGUAGE_CODES = [
        'en', 'fr', 'es', 'pt', 'de', 'it', 'nl', 'pl', 'ru', 'uk', 'tr', 'ar', 'hi', 'ur', 'bn',
        'id', 'ms', 'vi', 'th', 'zh-CN', 'zh-TW', 'ja', 'ko', 'el', 'sv', 'no', 'da', 'fi', 'ro', 'cs', 'hu', 'bg', 'he', 'fa',
    ];

    private const DEFAULT_LANGUAGE_REGION = [
        'language' => 'English (Default)',
        'language_code' => 'en',
        'locale' => 'en',
        'direction' => 'ltr',
        'region' => 'Nigeria',
    ];

    private const DEFAULT_CURRENCY_PREFERENCE = [
        'displayCurrency' => 'USD',
        'transactionCurrency' => 'USD',
    ];

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->dashboardPayload($request)]);
    }

    public function updateDashboard(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'mode' => ['required', Rule::in(['all', 'personalized'])],
            'primary_interest' => ['nullable', Rule::in(DashboardExperienceRegistry::KEYS)],
            'selected_interests' => ['array', 'max:6'],
            'selected_interests.*' => ['distinct', Rule::in(DashboardExperienceRegistry::KEYS)],
            'hidden_widgets' => ['sometimes', 'array'],
            'hidden_widgets.*' => ['string', 'max:64'],
            'widget_order' => ['sometimes', 'array'],
            'widget_order.*' => ['string', 'max:64'],
            'onboarding_completed' => ['sometimes', 'boolean'],
            'experience_level' => ['sometimes', Rule::in(ExperienceRecommendationService::EXPERIENCES)],
            'primary_goal' => ['sometimes', Rule::in(ExperienceRecommendationService::GOALS)],
            'interests' => ['sometimes', 'array', 'max:3'],
            'interests.*' => ['distinct', Rule::in(ExperienceRecommendationService::INTERESTS)],
            'selected_mode' => ['sometimes', Rule::in(ExperienceRecommendationService::MODES)],
            'onboarding_version' => ['sometimes', 'integer', 'min:4'],
            'quick_access_services' => ['sometimes', 'array', 'max:4'],
            'quick_access_services.*' => ['distinct', Rule::in($this->serviceCatalog->quickAccessServiceIds())],
            'quick_access' => ['sometimes', 'array', 'max:4'],
            'quick_access.*' => ['distinct', Rule::in($this->serviceCatalog->quickAccessServiceIds())],
        ]);
        if (
            array_key_exists('quick_access_services', $payload)
            && array_key_exists('quick_access', $payload)
            && $payload['quick_access_services'] !== $payload['quick_access']
        ) {
            return response()->json(['status' => 'error', 'message' => 'Provide only one Quick Access preference value.'], 422);
        }
        if (array_key_exists('quick_access', $payload)) {
            $payload['quick_access_services'] = $payload['quick_access'];
            unset($payload['quick_access']);
        }
        $user = $request->user();
        if (array_key_exists('quick_access_services', $payload)) {
            $requestedQuickAccess = array_values($payload['quick_access_services']);
            $eligibleQuickAccess = $this->serviceCatalog->resolveQuickAccessIds($requestedQuickAccess, $user);
            if ($eligibleQuickAccess !== $requestedQuickAccess) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'One or more Quick Access services are unavailable or not eligible.',
                ], 422);
            }
        }
        $selected = array_values($payload['selected_interests'] ?? []);
        if (($payload['primary_interest'] ?? null) && !in_array($payload['primary_interest'], $selected, true)) {
            return response()->json(['status' => 'error', 'message' => 'Primary interest must be selected.'], 422);
        }
        if ($payload['mode'] === 'personalized' && $selected === []) {
            return response()->json(['status' => 'error', 'message' => 'Choose at least one interest for a personalized dashboard.'], 422);
        }
        $preferences = (array) ($user->preferences ?? []);
        $existingDashboard = (array) (($preferences['dashboard'] ?? []) ?: []);
        $rawQuickAccess = $existingDashboard['quick_access_services']
            ?? $existingDashboard['quick_access']
            ?? $this->serviceCatalog->resolveQuickAccessIds(null, $user);
        unset($existingDashboard['quick_access']);
        $dashboardDefaults = [
            'mode' => 'all',
            'primary_interest' => null,
            'selected_interests' => [],
            'hidden_widgets' => [],
            'widget_order' => [],
            'onboarding_completed' => false,
            'experience_level' => null,
            'primary_goal' => null,
            'interests' => [],
            'recommended_mode' => null,
            'selected_mode' => 'lite',
            'onboarding_version' => null,
            'completed_at' => null,
            'quick_access_services' => $rawQuickAccess,
        ];
        $dashboard = array_merge($dashboardDefaults, $existingDashboard, $payload, [
            'selected_interests' => $selected,
            'primary_interest' => $payload['mode'] === 'all' ? null : ($payload['primary_interest'] ?? $selected[0] ?? null),
            'updated_at' => now()->toIso8601String(),
        ]);
        if (isset($dashboard['experience_level'], $dashboard['primary_goal'])) {
            $interests = array_values($dashboard['interests'] ?? $this->recommendations->inferInterests((string) $dashboard['primary_goal']));
            $result = $this->recommendations->recommend((string) $dashboard['experience_level'], (string) $dashboard['primary_goal'], $interests);
            $dashboard['interests'] = $interests;
            $dashboard['recommended_mode'] = $result['recommended_mode'];
            $dashboard['selected_mode'] = $payload['selected_mode'] ?? $dashboard['selected_mode'] ?? $result['recommended_mode'];
            $dashboard['recommendation_reason_codes'] = $result['reason_codes'];
        }
        $preferences['dashboard'] = $dashboard;
        $user->forceFill(['preferences' => $preferences])->save();
        AuditLog::create(['user_id' => $user->id, 'action' => 'dashboard.personalized', 'ip_address' => $request->ip(), 'device' => (string) $request->userAgent(), 'metadata' => ['mode' => $payload['mode'], 'primary_interest' => $preferences['dashboard']['primary_interest'], 'selected_interests' => $selected], 'created_at' => now()]);
        return response()->json([
            'status' => 'success',
            'message' => 'Dashboard preferences updated.',
            'data' => array_merge($preferences['dashboard'], [
                'quick_access_services' => $this->serviceCatalog->resolveQuickAccessIds($preferences['dashboard']['quick_access_services'] ?? [], $user),
                'quick_access' => $this->serviceCatalog->resolveQuickAccessIds($preferences['dashboard']['quick_access_services'] ?? [], $user),
            ]),
        ]);
    }

    public function resetDashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $preferences = (array) ($user->preferences ?? []);
        unset($preferences['dashboard']);
        $user->forceFill(['preferences' => $preferences])->save();
        AuditLog::create(['user_id' => $user->id, 'action' => 'dashboard.reset', 'ip_address' => $request->ip(), 'device' => (string) $request->userAgent(), 'metadata' => ['mode' => 'all'], 'created_at' => now()]);
        return response()->json(['status' => 'success', 'message' => 'Dashboard reset.', 'data' => $this->dashboardPayload($request)]);
    }

    public function languageRegion(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->languageRegionPayload($request),
        ]);
    }

    public function updateLanguageRegion(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'language' => ['required', 'string', 'max:96'],
            'language_code' => ['nullable', 'string', 'in:' . implode(',', self::SUPPORTED_LANGUAGE_CODES)],
            'locale' => ['nullable', 'string', 'max:16'],
            'direction' => ['nullable', 'string', 'in:ltr,rtl'],
            'region' => ['required', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $preferences = (array) ($user->preferences ?? []);
        $preferences['language_region'] = [
            'language' => $payload['language'],
            'language_code' => $payload['language_code'] ?? 'en',
            'locale' => $payload['locale'] ?? ($payload['language_code'] ?? 'en'),
            'direction' => $payload['direction'] ?? 'ltr',
            'region' => $payload['region'],
        ];

        $user->forceFill(['preferences' => $preferences])->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Language and region updated.',
            'data' => $preferences['language_region'],
        ]);
    }

    public function currencyPreference(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->currencyPreferencePayload($request),
        ]);
    }

    public function updateCurrencyPreference(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'displayCurrency' => ['required', 'string', 'in:' . implode(',', self::CURRENCIES)],
            'transactionCurrency' => ['required', 'string', 'in:' . implode(',', self::CURRENCIES)],
        ]);

        $user = $request->user();
        $preferences = (array) ($user->preferences ?? []);
        $preferences['currency_preference'] = [
            'display_currency' => $payload['displayCurrency'],
            'transaction_currency' => $payload['transactionCurrency'],
        ];

        $user->forceFill(['preferences' => $preferences])->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Currency preference updated.',
            'data' => [
                'displayCurrency' => $payload['displayCurrency'],
                'transactionCurrency' => $payload['transactionCurrency'],
            ],
        ]);
    }

    private function languageRegionPayload(Request $request): array
    {
        $preferences = (array) ($request->user()->preferences ?? []);
        $languageRegion = (array) ($preferences['language_region'] ?? []);

        return array_merge(self::DEFAULT_LANGUAGE_REGION, array_filter([
            'language' => $languageRegion['language'] ?? null,
            'language_code' => $languageRegion['language_code'] ?? null,
            'locale' => $languageRegion['locale'] ?? null,
            'direction' => $languageRegion['direction'] ?? null,
            'region' => $languageRegion['region'] ?? null,
        ]));
    }

    private function currencyPreferencePayload(Request $request): array
    {
        $preferences = (array) ($request->user()->preferences ?? []);
        $currencyPreference = (array) ($preferences['currency_preference'] ?? []);

        return array_merge(self::DEFAULT_CURRENCY_PREFERENCE, array_filter([
            'displayCurrency' => $currencyPreference['display_currency'] ?? null,
            'transactionCurrency' => $currencyPreference['transaction_currency'] ?? null,
        ]));
    }

    private function dashboardPayload(Request $request): array
    {
        $preferences = (array) ($request->user()->preferences ?? []);
        $dashboard = (array) ($preferences['dashboard'] ?? []);
        $quickAccess = $this->serviceCatalog->resolveQuickAccessIds(
            $dashboard['quick_access_services'] ?? $dashboard['quick_access'] ?? null,
            $request->user(),
        );
        unset($dashboard['quick_access']);

        return array_merge([
            'mode' => 'all', 'primary_interest' => null, 'selected_interests' => [],
            'hidden_widgets' => [], 'widget_order' => [], 'onboarding_completed' => false,
            'experience_level' => null, 'primary_goal' => null, 'interests' => [],
            'recommended_mode' => null, 'selected_mode' => 'lite', 'onboarding_version' => null, 'completed_at' => null,
            'quick_access_services' => $quickAccess,
        ], $dashboard, [
            'quick_access_services' => $quickAccess,
            'quick_access' => $quickAccess,
        ]);
    }
}
