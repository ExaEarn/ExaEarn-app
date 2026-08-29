<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\MerchantApiKey;
use App\Models\MerchantPaymentLink;
use App\Services\Fiat\ExaPayMerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExaPayMerchantController extends Controller
{
    public function apply(Request $request, ExaPayMerchantService $service): JsonResponse
    {
        $payload = $request->validate([
            'business_name' => ['required', 'string', 'max:160'],
            'organization_name' => ['nullable', 'string', 'max:160'],
            'country' => ['nullable', 'string', 'size:2'],
            'business_type' => ['nullable', 'string', 'max:80'],
            'settlement_currency' => ['required', 'string', 'max:8'],
            'settlement_account_reference' => ['nullable', 'string', 'max:160'],
            'pricing_profile' => ['nullable', 'string', 'max:80'],
            'environment' => ['nullable', 'in:SANDBOX,PRODUCTION,sandbox,production'],
            'website' => ['nullable', 'url', 'max:255'],
            'tax_identifier' => ['nullable', 'string', 'max:120'],
            'expected_monthly_volume' => ['nullable', 'numeric', 'gte:0'],
        ]);

        return response()->json(['data' => $service->apply($request->user(), $payload)], 201);
    }

    public function merchants(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Merchant::query()
                ->where('user_id', $request->user()->id)
                ->with('teamMembers')
                ->latest('id')
                ->get(),
        ]);
    }

    public function overview(Request $request, string $merchantId, ExaPayMerchantService $service): JsonResponse
    {
        return response()->json(['data' => $service->overview($this->merchantForUser($request, $merchantId))]);
    }

    public function createIntent(Request $request, string $merchantId, ExaPayMerchantService $service): JsonResponse
    {
        $payload = $request->validate([
            'payer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'max:8'],
            'description' => ['nullable', 'string', 'max:200'],
            'merchant_reference' => ['nullable', 'string', 'max:160'],
            'customer_reference' => ['nullable', 'string', 'max:160'],
            'environment' => ['nullable', 'in:SANDBOX,PRODUCTION,sandbox,production'],
            'capture_mode' => ['nullable', 'in:AUTOMATIC,MANUAL,automatic,manual'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'idempotency_key' => ['required', 'string', 'max:160'],
            'expires_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ]);

        return response()->json(['data' => $service->createIntent($this->merchantForUser($request, $merchantId), $payload)], 201);
    }

    public function capture(Request $request, string $payIntent, ExaPayMerchantService $service): JsonResponse
    {
        try {
            $intent = DB::table('exaearn_pay_intents')
                ->where(fn ($query) => $query->where('pay_intent_id', $payIntent)->orWhere('public_reference', $payIntent))
                ->first();
            if (! $intent) {
                abort(404);
            }
            $merchantOwned = $intent->merchant_id
                ? Merchant::query()->where('id', $intent->merchant_id)->where('user_id', $request->user()?->id)->exists()
                : false;
            $payerOwned = $intent->payer_user_id && (int) $intent->payer_user_id === (int) $request->user()?->id;
            if (! $merchantOwned && ! $payerOwned) {
                abort(403);
            }

            return response()->json(['data' => $service->capture($payIntent)]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function checkout(string $token, ExaPayMerchantService $service): JsonResponse
    {
        try {
            return response()->json(['data' => $service->checkout($token)]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function createLink(Request $request, string $merchantId, ExaPayMerchantService $service): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'amount_mode' => ['nullable', 'in:FIXED,VARIABLE,fixed,variable'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'maximum_uses' => ['nullable', 'integer', 'min:1'],
            'success_url' => ['nullable', 'url', 'max:255'],
            'cancel_url' => ['nullable', 'url', 'max:255'],
            'expires_at' => ['nullable', 'date'],
            'customer_fields' => ['nullable', 'array'],
        ]);

        return response()->json(['data' => $service->createPaymentLink($this->merchantForUser($request, $merchantId), $payload)], 201);
    }

    public function payLink(Request $request, string $linkId, ExaPayMerchantService $service): JsonResponse
    {
        $payload = $request->validate([
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'payer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_reference' => ['nullable', 'string', 'max:160'],
            'merchant_reference' => ['nullable', 'string', 'max:160'],
            'idempotency_key' => ['required', 'string', 'max:160'],
            'metadata' => ['nullable', 'array'],
        ]);

        $link = MerchantPaymentLink::query()->where('link_id', $linkId)->firstOrFail();

        return response()->json(['data' => $service->createIntentFromLink($link, $payload)], 201);
    }

    public function links(Request $request, string $merchantId): JsonResponse
    {
        $merchant = $this->merchantForUser($request, $merchantId);

        return response()->json(['data' => MerchantPaymentLink::query()->where('merchant_id', $merchant->id)->latest('id')->get()]);
    }

    public function createApiKey(Request $request, string $merchantId, ExaPayMerchantService $service): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'environment' => ['nullable', 'in:SANDBOX,PRODUCTION,sandbox,production'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', 'max:80'],
            'ip_allowlist' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date'],
        ]);

        return response()->json(['data' => $service->createApiKey($this->merchantForUser($request, $merchantId), $payload)], 201);
    }

    public function revokeApiKey(Request $request, string $merchantId, int $keyId, ExaPayMerchantService $service): JsonResponse
    {
        $merchant = $this->merchantForUser($request, $merchantId);
        $key = MerchantApiKey::query()->where('merchant_id', $merchant->id)->findOrFail($keyId);

        return response()->json(['data' => $service->revokeApiKey($key)]);
    }

    public function payments(Request $request, string $merchantId): JsonResponse
    {
        $merchant = $this->merchantForUser($request, $merchantId);

        return response()->json([
            'data' => DB::table('exaearn_pay_intents')
                ->where('merchant_id', $merchant->id)
                ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', strtoupper($status)))
                ->latest('id')
                ->limit(min(100, max(1, (int) $request->integer('limit', 50))))
                ->get(),
        ]);
    }

    public function refund(Request $request, string $merchantId, ExaPayMerchantService $service): JsonResponse
    {
        $payload = $request->validate([
            'payment_reference' => ['required', 'string', 'max:160'],
            'currency' => ['required', 'string', 'max:8'],
            'reason' => ['required', 'string', 'max:160'],
        ]);

        return response()->json(['data' => $service->refund($this->merchantForUser($request, $merchantId), $payload['payment_reference'], $payload['currency'], $payload['reason'])], 201);
    }

    public function dispute(Request $request, string $merchantId, ExaPayMerchantService $service): JsonResponse
    {
        $payload = $request->validate([
            'provider' => ['required', 'string', 'max:40'],
            'provider_reference' => ['required', 'string', 'max:160'],
            'currency' => ['required', 'string', 'max:8'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'metadata' => ['nullable', 'array'],
        ]);

        return response()->json(['data' => $service->openDispute($this->merchantForUser($request, $merchantId), $payload)], 201);
    }

    public function settlement(Request $request, string $merchantId, ExaPayMerchantService $service): JsonResponse
    {
        $payload = $request->validate(['currency' => ['required', 'string', 'max:8']]);

        return response()->json(['data' => $service->settlement($this->merchantForUser($request, $merchantId), $payload['currency'])], 201);
    }

    public function reconciliation(Request $request, string $merchantId, ExaPayMerchantService $service): JsonResponse
    {
        return response()->json(['data' => $service->reconcile($this->merchantForUser($request, $merchantId))]);
    }

    private function merchantForUser(Request $request, string $merchantId): Merchant
    {
        return Merchant::query()
            ->where('user_id', $request->user()->id)
            ->where(fn ($query) => $query->where('merchant_id', $merchantId)->orWhere('id', $merchantId))
            ->firstOrFail();
    }
}
