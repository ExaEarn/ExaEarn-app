<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    private const CURRENCIES = ['USD', 'NGN', 'EUR', 'GBP', 'BTC', 'ETH'];

    private const SUPPORTED_LANGUAGE_CODES = [
        'en', 'fr', 'es', 'pt', 'de', 'it', 'nl', 'pl', 'ru', 'uk', 'tr', 'ar', 'hi', 'ur', 'bn',
        'id', 'ms', 'vi', 'th', 'zh-CN', 'zh-TW', 'ja', 'ko', 'el', 'sv', 'no', 'da', 'fi', 'ro', 'cs', 'hu', 'bg', 'he', 'fa',
    ];

    private const DEFAULT_LANGUAGE_REGION = [
        'language' => 'English',
        'language_code' => 'en',
        'locale' => 'en',
        'direction' => 'ltr',
        'region' => 'Nigeria',
    ];

    private const DEFAULT_CURRENCY_PREFERENCE = [
        'displayCurrency' => 'USD',
        'transactionCurrency' => 'USD',
    ];

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
}
