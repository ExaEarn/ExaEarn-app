<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardAuthorization;
use App\Models\CardAuditLog;
use App\Models\CardCustomer;
use App\Models\CardDispute;
use App\Models\CardFundingRequest;
use App\Models\CardProviderBalance;
use App\Models\CardTransaction;
use App\Models\CardUnloadRequest;
use App\Services\Cards\CardProviderRegistry;
use App\Services\Cards\CardReconciliationService;
use App\Services\Cards\CardTreasuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExaCardOperationsController extends Controller
{
    public function __construct(
        private readonly CardProviderRegistry $providers,
        private readonly CardTreasuryService $treasury,
        private readonly CardReconciliationService $reconciliation,
    ) {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['data' => [
            'cards_total' => Card::query()->count(),
            'cards_active' => Card::query()->where('status', 'ACTIVE')->count(),
            'funding_24h' => CardFundingRequest::query()->where('created_at', '>=', now()->subDay())->count(),
            'transactions_24h' => CardTransaction::query()->where('created_at', '>=', now()->subDay())->count(),
            'open_authorizations' => CardAuthorization::query()->whereIn('status', ['AUTHORIZED', 'PENDING'])->count(),
            'open_disputes' => CardDispute::query()->whereIn('status', ['OPEN', 'REVIEWING', 'ESCALATED'])->count(),
            'provider_health' => $this->providers->provider()->health(),
            'treasury' => $this->treasury->overview(),
        ]]);
    }

    public function cards(Request $request): JsonResponse
    {
        return response()->json(['data' => Card::query()
            ->when($request->query('status'), fn ($query, string $status) => $query->where('status', strtoupper($status)))
            ->latest()
            ->paginate((int) $request->query('per_page', 50))]);
    }

    public function customers(Request $request): JsonResponse
    {
        return response()->json(['data' => CardCustomer::query()
            ->when($request->query('provider'), fn ($query, string $provider) => $query->where('provider', strtolower($provider)))
            ->latest()
            ->paginate((int) $request->query('per_page', 50))]);
    }

    public function transactions(Request $request): JsonResponse
    {
        return response()->json(['data' => CardTransaction::query()
            ->when($request->query('status'), fn ($query, string $status) => $query->where('status', strtoupper($status)))
            ->when($request->query('type'), fn ($query, string $type) => $query->where('type', strtoupper($type)))
            ->latest('provider_created_at')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 50))]);
    }

    public function funding(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'funding_requests' => CardFundingRequest::query()
                ->when($request->query('status'), fn ($query, string $status) => $query->where('status', strtoupper($status)))
                ->latest()
                ->paginate((int) $request->query('per_page', 50)),
            'unload_requests' => CardUnloadRequest::query()
                ->when($request->query('unload_status'), fn ($query, string $status) => $query->where('status', strtoupper($status)))
                ->latest()
                ->limit(100)
                ->get(),
        ]]);
    }

    public function disputes(Request $request): JsonResponse
    {
        return response()->json(['data' => CardDispute::query()
            ->when($request->query('status'), fn ($query, string $status) => $query->where('status', strtoupper($status)))
            ->latest()
            ->paginate((int) $request->query('per_page', 50))]);
    }

    public function treasury(): JsonResponse
    {
        return response()->json(['data' => $this->treasury->overview()]);
    }

    public function providers(): JsonResponse
    {
        return response()->json(['data' => [
            'active_provider' => $this->providers->provider()->health(),
            'balances' => CardProviderBalance::query()->orderBy('provider')->orderBy('currency')->get(),
        ]]);
    }

    public function revenue(): JsonResponse
    {
        return response()->json(['data' => [
            'funding_fee_total' => (string) CardFundingRequest::query()->where('status', 'COMPLETED')->sum('fee_amount'),
            'provider_cost_total' => (string) CardFundingRequest::query()->where('status', 'COMPLETED')->sum('provider_cost'),
            'transaction_fee_total' => (string) CardTransaction::query()->where('status', 'POSTED')->sum('fee'),
            'transaction_provider_cost_total' => (string) CardTransaction::query()->where('status', 'POSTED')->sum('provider_cost'),
        ]]);
    }

    public function providerBalance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:60'],
            'currency' => ['required', 'string', 'max:24'],
            'available' => ['required', 'numeric', 'min:0'],
            'required_minimum' => ['nullable', 'numeric', 'min:0'],
            'target' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json(['data' => $this->treasury->upsertProviderBalance(
            $data['provider'],
            $data['currency'],
            (string) $data['available'],
            (string) ($data['required_minimum'] ?? '0'),
            (string) ($data['target'] ?? '0'),
        )], 201);
    }

    public function reconciliation(): JsonResponse
    {
        return response()->json(['data' => $this->reconciliation->run()], 201);
    }

    public function auditLogs(): JsonResponse
    {
        return response()->json(['data' => CardAuditLog::query()->latest()->paginate(100)]);
    }
}
