<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use App\Models\ActivityLog;
use App\Models\GiftCardFraudFlag;
use App\Models\GiftCardInventory;
use App\Models\GiftCardRate;
use App\Models\GiftCardSubmission;
use App\Models\GiftcardOrder;
use App\Models\LedgerTransaction;
use App\Models\PricingRule;
use App\Models\Reservation;

class GiftCardAdminCenterService
{
    public function __construct(
        private readonly GiftCardTreasuryService $treasury,
        private readonly GiftCardReconciliationService $reconciliation,
        private readonly GiftCardProviderManager $providers,
    ) {
    }

    public function dashboard(string $asset = 'USD'): array
    {
        $asset = strtoupper($asset);
        $buyOrders = GiftcardOrder::query()->where('type', 'buy');
        $sellOrders = GiftcardOrder::query()->where('type', 'sell');
        $submissions = GiftCardSubmission::query();
        $inventory = GiftCardInventory::query();
        $reconciliation = $this->reconciliation->run($asset);

        return [
            'overview' => [
                'buy_orders' => (clone $buyOrders)->count(),
                'sell_orders' => (clone $sellOrders)->count(),
                'sell_submissions' => (clone $submissions)->count(),
                'inventory_total' => (clone $inventory)->count(),
                'inventory_available' => (clone $inventory)->where('available', true)->count(),
                'provider_unknown' => (clone $buyOrders)->where('status', 'provider_unknown')->count(),
                'pending_review' => GiftcardOrder::query()->whereIn('status', ['pending_review', 'flagged', 'pending_analysis'])->count(),
                'refunds' => GiftcardOrder::query()->where('status', 'refunded')->count(),
                'reconciliation_status' => $reconciliation['status'],
            ],
            'sections' => [
                'overview' => ['status' => 'READY'],
                'buy_orders' => ['status' => 'READY', 'count' => (clone $buyOrders)->count()],
                'sell_submissions' => ['status' => 'READY', 'count' => (clone $submissions)->count()],
                'inventory' => ['status' => 'READY', 'available' => (clone $inventory)->where('available', true)->count()],
                'brands_products' => ['status' => 'READY', 'brands' => (clone $inventory)->select('brand')->distinct()->pluck('brand')->values()],
                'rates' => ['status' => 'READY', 'count' => GiftCardRate::query()->count()],
                'pricing' => ['status' => PricingRule::query()->where('product', 'GIFTCARD')->where('status', 'ACTIVE')->exists() ? 'READY' : 'NEEDS_RULES'],
                'providers' => ['status' => $this->providers->productionReady() ? 'CONNECTED' : 'OPERATIONAL_SETUP_REQUIRED'],
                'delivery' => ['status' => 'READY', 'delivered' => GiftcardOrder::query()->whereNotNull('delivered_at')->count()],
                'treasury' => ['status' => 'READY'],
                'reconciliation' => ['status' => $reconciliation['status'], 'findings' => $reconciliation['findings']],
                'fraud' => ['status' => 'READY', 'flags' => class_exists(GiftCardFraudFlag::class) ? GiftCardFraudFlag::query()->count() : 0],
                'refunds' => ['status' => 'READY', 'count' => GiftcardOrder::query()->where('status', 'refunded')->count()],
                'reports' => ['status' => 'READY'],
                'audit' => ['status' => 'READY'],
            ],
            'rows' => $this->rows(),
            'stats' => [
                ['label' => 'Buy Orders', 'value' => (string) (clone $buyOrders)->count()],
                ['label' => 'Sell Submissions', 'value' => (string) (clone $submissions)->count()],
                ['label' => 'Available Inventory', 'value' => (string) (clone $inventory)->where('available', true)->count()],
                ['label' => 'Provider', 'value' => $this->providers->productionReady() ? 'CONNECTED' : 'SETUP REQUIRED'],
                ['label' => 'Reconciliation', 'value' => $reconciliation['status']],
            ],
            'treasury' => $this->treasury->overview($asset),
            'reconciliation' => $reconciliation,
            'audit' => $this->auditRows(),
        ];
    }

    private function rows(): array
    {
        $orders = GiftcardOrder::query()
            ->with('user:id,email,name')
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (GiftcardOrder $order): array => [
                'type' => 'order',
                'id' => $order->id,
                'user' => $order->user?->email ?? $order->user_id,
                'flow' => $order->type,
                'brand' => data_get($order->metadata, 'brand'),
                'amount' => (string) $order->amount,
                'currency' => $order->currency,
                'status' => $order->status,
                'risk_level' => $order->risk_level,
                'reference' => $order->reference,
            ])
            ->all();

        $submissions = GiftCardSubmission::query()
            ->with('user:id,email,name')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (GiftCardSubmission $submission): array => [
                'type' => 'submission',
                'id' => $submission->id,
                'user' => $submission->user?->email ?? $submission->user_id,
                'flow' => 'sell_submission',
                'brand' => $submission->brand,
                'amount' => (string) $submission->payout_amount,
                'currency' => $submission->currency,
                'status' => $submission->status,
                'risk_level' => data_get($submission->metadata, 'fraud_risk_level', 'UNKNOWN'),
                'reference' => 'submission-'.$submission->id,
            ])
            ->all();

        return array_values(array_merge($orders, $submissions));
    }

    private function auditRows(): array
    {
        if (!class_exists(ActivityLog::class)) {
            return [];
        }

        return ActivityLog::query()
            ->where(function ($query): void {
                $query->where('type', 'like', '%giftcard%')
                    ->orWhere('action', 'like', '%giftcard%');
            })
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($log): array => [
                'id' => $log->id,
                'type' => $log->type,
                'description' => $log->action,
                'created_at' => $log->created_at,
            ])
            ->all();
    }

    public function closureBlockers(int $userId): array
    {
        $orderStatuses = ['pending', 'pending_analysis', 'pending_review', 'flagged', 'paid', 'provider_pending', 'provider_unknown', 'delivery_pending', 'refund_pending'];
        $submissionStatuses = ['pending', 'submitted', 'verifying', 'under_review', 'approved', 'payout_pending', 'disputed'];

        $orders = GiftcardOrder::query()
            ->where('user_id', $userId)
            ->whereIn('status', $orderStatuses)
            ->get(['id', 'type', 'status', 'reference']);

        $submissions = GiftCardSubmission::query()
            ->where('user_id', $userId)
            ->whereIn('status', $submissionStatuses)
            ->get(['id', 'status', 'brand']);

        $reservations = Reservation::query()
            ->where('user_id', $userId)
            ->where('purpose', 'giftcard_purchase')
            ->whereIn('status', [Reservation::STATUS_ACTIVE, Reservation::STATUS_PARTIALLY_CONSUMED])
            ->get(['reservation_id', 'status', 'amount', 'asset']);

        $transactions = LedgerTransaction::query()
            ->whereIn('transaction_type', ['giftcard_purchase', 'giftcard_refund', 'giftcard_sell_payout'])
            ->whereIn('status', ['pending', 'processing'])
            ->where('metadata->user_id', $userId)
            ->get(['reference', 'transaction_type', 'status']);

        $blockers = [];
        foreach ($orders as $order) {
            $blockers[] = ['type' => 'giftcard_order', 'id' => $order->id, 'status' => $order->status, 'reference' => $order->reference];
        }
        foreach ($submissions as $submission) {
            $blockers[] = ['type' => 'giftcard_submission', 'id' => $submission->id, 'status' => $submission->status, 'brand' => $submission->brand];
        }
        foreach ($reservations as $reservation) {
            $blockers[] = ['type' => 'giftcard_reservation', 'id' => $reservation->reservation_id, 'status' => $reservation->status, 'amount' => (string) $reservation->amount, 'asset' => $reservation->asset];
        }
        foreach ($transactions as $transaction) {
            $blockers[] = ['type' => 'giftcard_ledger_transaction', 'id' => $transaction->reference, 'status' => $transaction->status];
        }

        return $blockers;
    }
}
