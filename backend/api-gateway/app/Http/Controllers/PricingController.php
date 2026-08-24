<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PricingRule;
use App\Services\PricingPolicyEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PricingController extends Controller
{
    public function preview(Request $request, PricingPolicyEngine $pricing): JsonResponse
    {
        $data = $request->validate([
            'product' => ['required', 'string', 'max:60'],
            'operation' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'asset' => ['nullable', 'string', 'max:24'],
            'currency' => ['nullable', 'string', 'max:24'],
            'network' => ['nullable', 'string', 'max:64'],
            'market_symbol' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'size:2'],
            'vip_tier' => ['nullable', 'string', 'max:24'],
            'merchant_tier' => ['nullable', 'string', 'max:40'],
            'promotion_code' => ['nullable', 'string', 'max:80'],
        ]);

        return response()->json(['data' => $pricing->preview($data)]);
    }

    public function publicFees(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product' => ['nullable', 'string', 'max:60'],
            'operation' => ['nullable', 'string', 'max:80'],
        ]);

        $rules = PricingRule::query()
            ->where('status', 'ACTIVE')
            ->whereNull('user_id')
            ->whereNull('institution_id')
            ->when($data['product'] ?? null, fn ($query, string $product) => $query->where('product', strtoupper($product)))
            ->when($data['operation'] ?? null, fn ($query, string $operation) => $query->where('operation', strtoupper($operation)))
            ->where(function ($query): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('effective_until')->orWhere('effective_until', '>', now());
            })
            ->orderBy('product')
            ->orderBy('operation')
            ->orderByDesc('priority')
            ->paginate((int) $request->query('per_page', 50));

        return response()->json(['data' => $rules]);
    }
}
