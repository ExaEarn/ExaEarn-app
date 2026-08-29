<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use App\Models\DeveloperProject;
use App\Models\Merchant;
use App\Models\MerchantApiKey;
use App\Models\MerchantPaymentLink;
use App\Models\MerchantTeamMember;
use App\Models\MerchantWebhookEvent;
use App\Models\User;
use App\Services\DeveloperWebhookService;
use App\Services\FinancialDecimal;
use App\Services\PricingPolicyEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use RuntimeException;

class ExaPayMerchantService
{
    private const MERCHANT_PERMISSIONS = [
        'OWNER' => ['payments.read', 'payments.create', 'refunds.create', 'settlements.read', 'api_keys.manage', 'webhooks.manage', 'reports.read', 'team.manage'],
        'ADMIN' => ['payments.read', 'payments.create', 'refunds.create', 'settlements.read', 'api_keys.manage', 'webhooks.manage', 'reports.read'],
        'DEVELOPER' => ['payments.read', 'payments.create', 'api_keys.manage', 'webhooks.manage'],
        'FINANCE' => ['payments.read', 'refunds.create', 'settlements.read', 'reports.read'],
        'SUPPORT' => ['payments.read', 'refunds.create'],
        'VIEWER' => ['payments.read', 'settlements.read', 'reports.read'],
    ];

    public function __construct(
        private readonly ExaEarnPayService $payments,
        private readonly PaymentRefundService $refunds,
        private readonly PaymentDisputeService $disputes,
        private readonly MerchantSettlementService $settlements,
        private readonly PricingPolicyEngine $pricing,
        private readonly DeveloperWebhookService $webhooks,
    ) {
    }

    public function apply(User $owner, array $payload): Merchant
    {
        return DB::transaction(function () use ($owner, $payload): Merchant {
            $merchant = Merchant::query()->updateOrCreate(
                ['user_id' => $owner->id, 'environment' => strtoupper((string) ($payload['environment'] ?? 'SANDBOX'))],
                [
                    'merchant_id' => (string) Str::uuid(),
                    'business_name' => (string) $payload['business_name'],
                    'organization_name' => $payload['organization_name'] ?? $payload['business_name'],
                    'country' => strtoupper((string) ($payload['country'] ?? 'NG')),
                    'business_type' => (string) ($payload['business_type'] ?? 'GENERAL_COMMERCE'),
                    'kyb_status' => 'APPLIED',
                    'settlement_currency' => strtoupper((string) $payload['settlement_currency']),
                    'settlement_account_reference' => $payload['settlement_account_reference'] ?? null,
                    'pricing_profile' => $payload['pricing_profile'] ?? 'STANDARD',
                    'status' => 'APPLIED',
                    'risk_status' => 'NORMAL',
                    'profile' => [
                        'expected_monthly_volume' => $payload['expected_monthly_volume'] ?? null,
                        'website' => $payload['website'] ?? null,
                        'tax_identifier' => $payload['tax_identifier'] ?? null,
                    ],
                    'metadata' => ['source' => 'exapay_merchant_application'],
                ],
            );

            MerchantTeamMember::query()->updateOrCreate(
                ['merchant_id' => $merchant->id, 'user_id' => $owner->id],
                ['role' => 'OWNER', 'permissions' => self::MERCHANT_PERMISSIONS['OWNER'], 'status' => 'ACTIVE'],
            );

            return $merchant->fresh();
        });
    }

    public function approve(Merchant $merchant, int $adminId, string $reason): Merchant
    {
        $merchant->forceFill([
            'kyb_status' => 'APPROVED',
            'status' => 'ACTIVE',
            'activated_at' => now(),
            'metadata' => array_merge($merchant->metadata ?? [], [
                'approved_by_admin_id' => $adminId,
                'approval_reason' => $reason,
                'phase16_gate' => 'APPROVED_BY_ADMIN_REVIEW',
            ]),
        ])->save();

        return $merchant->fresh();
    }

    public function restrict(Merchant $merchant, string $status, string $reason): Merchant
    {
        $status = strtoupper($status);
        if (! in_array($status, ['NEEDS_INFORMATION', 'RESTRICTED', 'SUSPENDED', 'REJECTED', 'CLOSED'], true)) {
            throw new RuntimeException('Unsupported merchant restriction state.');
        }

        $merchant->forceFill([
            'status' => $status,
            'risk_status' => in_array($status, ['RESTRICTED', 'SUSPENDED'], true) ? 'RESTRICTED' : $merchant->risk_status,
            'metadata' => array_merge($merchant->metadata ?? [], ['restriction_reason' => $reason]),
        ])->save();

        return $merchant->fresh();
    }

    public function createApiKey(Merchant $merchant, array $payload): array
    {
        $this->assertActive($merchant);
        $secret = 'exapay_sk_' . Str::random(48);
        $prefix = 'epk_' . Str::lower(Str::random(12));
        $key = MerchantApiKey::query()->create([
            'key_id' => (string) Str::uuid(),
            'merchant_id' => $merchant->id,
            'environment' => strtoupper((string) ($payload['environment'] ?? $merchant->environment)),
            'name' => (string) $payload['name'],
            'key_prefix' => $prefix,
            'key_hash' => hash('sha256', $secret),
            'scopes' => array_values(array_unique((array) ($payload['scopes'] ?? ['payments.read', 'payments.create']))),
            'ip_allowlist' => $payload['ip_allowlist'] ?? [],
            'status' => 'ACTIVE',
            'expires_at' => $payload['expires_at'] ?? null,
        ]);

        return ['api_key' => $key, 'secret' => $secret];
    }

    public function revokeApiKey(MerchantApiKey $key): MerchantApiKey
    {
        $key->update(['status' => 'REVOKED']);

        return $key->fresh();
    }

    public function createPaymentLink(Merchant $merchant, array $payload): MerchantPaymentLink
    {
        $this->assertActive($merchant);
        $amountMode = strtoupper((string) ($payload['amount_mode'] ?? 'FIXED'));
        if ($amountMode === 'FIXED' && empty($payload['amount'])) {
            throw new RuntimeException('Fixed payment links require an amount.');
        }

        return MerchantPaymentLink::query()->create([
            'link_id' => (string) Str::uuid(),
            'merchant_id' => $merchant->id,
            'environment' => strtoupper((string) ($payload['environment'] ?? $merchant->environment)),
            'title' => (string) $payload['title'],
            'description' => $payload['description'] ?? null,
            'amount_mode' => $amountMode,
            'amount' => $payload['amount'] ?? null,
            'currency' => strtoupper((string) ($payload['currency'] ?? $merchant->settlement_currency)),
            'maximum_uses' => $payload['maximum_uses'] ?? null,
            'success_url' => $payload['success_url'] ?? null,
            'cancel_url' => $payload['cancel_url'] ?? null,
            'expires_at' => $payload['expires_at'] ?? null,
            'status' => 'ACTIVE',
            'metadata' => ['customer_fields' => $payload['customer_fields'] ?? []],
        ]);
    }

    public function createIntent(Merchant $merchant, array $payload): array
    {
        $this->assertActive($merchant);
        $idempotency = (string) ($payload['idempotency_key'] ?? Str::uuid());
        $existing = DB::table('exaearn_pay_intents')
            ->where('merchant_id', $merchant->id)
            ->where('environment', strtoupper((string) ($payload['environment'] ?? $merchant->environment)))
            ->where('idempotency_key', $idempotency)
            ->first();
        if ($existing) {
            return (array) $existing;
        }

        $amount = FinancialDecimal::normalize((string) $payload['amount']);
        $currency = strtoupper((string) $payload['currency']);
        $pricing = $this->pricingSnapshot($merchant, $amount, $currency, (string) ($payload['payment_method'] ?? 'EXAEARN_BALANCE'));
        $token = Str::random(48);
        $intent = $this->payments->createMerchantIntent($merchant, [
            'payer_user_id' => $payload['payer_user_id'] ?? null,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $payload['description'] ?? null,
            'merchant_reference' => $payload['merchant_reference'] ?? null,
            'customer_reference' => $payload['customer_reference'] ?? null,
            'environment' => strtoupper((string) ($payload['environment'] ?? $merchant->environment)),
            'capture_mode' => strtoupper((string) ($payload['capture_mode'] ?? 'AUTOMATIC')),
            'payment_method' => strtoupper((string) ($payload['payment_method'] ?? 'EXAEARN_BALANCE')),
            'pricing_snapshot' => $pricing,
            'idempotency_key' => $idempotency,
            'checkout_token_hash' => hash('sha256', $token),
            'expires_at' => isset($payload['expires_at']) ? Carbon::parse((string) $payload['expires_at']) : now()->addMinutes(30),
            'metadata' => array_merge($payload['metadata'] ?? [], ['source' => 'exapay_merchant']),
        ]);

        $this->recordWebhook($merchant, 'payment.created', 'payment', (string) $intent['pay_intent_id'], $intent);

        return array_merge($intent, ['checkout_token' => $token]);
    }

    public function createIntentFromLink(MerchantPaymentLink $link, array $payload): array
    {
        if ($link->status !== 'ACTIVE' || ($link->expires_at && $link->expires_at->isPast())) {
            throw new RuntimeException('Payment link is not active.');
        }
        if ($link->maximum_uses !== null && $link->uses_count >= $link->maximum_uses) {
            throw new RuntimeException('Payment link usage limit reached.');
        }
        $amount = $link->amount_mode === 'FIXED' ? (string) $link->amount : (string) ($payload['amount'] ?? '0');
        if (FinancialDecimal::compare($amount, '0') <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        /** @var Merchant $merchant */
        $merchant = Merchant::query()->findOrFail($link->merchant_id);
        $intent = $this->createIntent($merchant, array_merge($payload, [
            'amount' => $amount,
            'currency' => $link->currency,
            'description' => $link->title,
            'merchant_reference' => $payload['merchant_reference'] ?? 'link:'.$link->link_id,
            'environment' => $link->environment,
        ]));
        $link->increment('uses_count');

        return $intent;
    }

    public function checkout(string $token): array
    {
        $intent = DB::table('exaearn_pay_intents')
            ->where('checkout_token_hash', hash('sha256', $token))
            ->first();
        if (! $intent || $intent->expires_at < now()) {
            throw new RuntimeException('Checkout session expired or unavailable.');
        }
        $merchant = Merchant::query()->findOrFail((int) $intent->merchant_id);

        return [
            'merchant' => [
                'merchant_id' => $merchant->merchant_id,
                'business_name' => $merchant->business_name,
                'country' => $merchant->country,
            ],
            'payment' => [
                'pay_intent_id' => $intent->pay_intent_id,
                'public_reference' => $intent->public_reference,
                'amount' => $intent->amount,
                'currency' => $intent->currency,
                'description' => $intent->description,
                'status' => $intent->status,
                'expires_at' => $intent->expires_at,
            ],
            'accepted_payment_methods' => ['EXAEARN_BALANCE', 'SANDBOX_PROVIDER'],
            'trust_branding' => 'EXAPAY',
        ];
    }

    public function capture(string $identifier): array
    {
        $captured = $this->payments->capture($identifier);
        if (! empty($captured['merchant_id'])) {
            $merchant = Merchant::query()->find((int) $captured['merchant_id']);
            if ($merchant) {
                $this->recordWebhook($merchant, 'payment.captured', 'payment', (string) $captured['pay_intent_id'], $captured);
            }
        }

        return $captured;
    }

    public function refund(Merchant $merchant, string $paymentReference, string $currency, string $reason): array
    {
        $intent = DB::table('exaearn_pay_intents')
            ->where('merchant_id', $merchant->id)
            ->where(fn ($query) => $query->where('pay_intent_id', $paymentReference)->orWhere('public_reference', $paymentReference)->orWhere('ledger_reference', $paymentReference))
            ->first();
        if ($intent?->ledger_reference && in_array((string) $intent->status, ['REFUNDED', 'PARTIALLY_REFUNDED'], true)) {
            $existing = DB::table('payment_refunds')->where('original_reference', $intent->ledger_reference)->first();
            if ($existing) {
                return (array) $existing;
            }
        }
        if (! $intent || $intent->status !== 'CAPTURED' || ! $intent->ledger_reference) {
            throw new RuntimeException('Payment is not refundable.');
        }

        $refund = $this->refunds->reverseLedgerReference((string) $intent->ledger_reference, $currency, $reason);
        DB::table('exaearn_pay_intents')->where('id', $intent->id)->update(['status' => 'REFUNDED', 'updated_at' => now()]);
        $this->recordWebhook($merchant, 'payment.refunded', 'payment', (string) $intent->pay_intent_id, $refund);

        return $refund;
    }

    public function openDispute(Merchant $merchant, array $payload): array
    {
        $dispute = $this->disputes->open(array_merge($payload, [
            'metadata' => array_merge($payload['metadata'] ?? [], ['merchant_id' => $merchant->id]),
        ]));
        $this->recordWebhook($merchant, 'dispute.created', 'dispute', (string) $dispute['dispute_id'], $dispute);

        return $dispute;
    }

    public function settlement(Merchant $merchant, string $currency): array
    {
        $settlement = $this->settlements->create($merchant->id, $currency);
        $this->recordWebhook($merchant, 'settlement.created', 'settlement', (string) $settlement['settlement_id'], $settlement);

        return $settlement;
    }

    public function overview(Merchant $merchant): array
    {
        $captured = DB::table('exaearn_pay_intents')->where('merchant_id', $merchant->id)->where('status', 'CAPTURED');
        $total = (string) (clone $captured)->sum('amount');
        $fees = (string) (clone $captured)->sum('fee_amount');
        $count = (clone $captured)->count();
        $allCount = DB::table('exaearn_pay_intents')->where('merchant_id', $merchant->id)->count();

        return [
            'merchant' => $merchant,
            'metrics' => [
                'gross_amount' => FinancialDecimal::normalize($total),
                'fees' => FinancialDecimal::normalize($fees),
                'net_payable' => FinancialDecimal::sub($total, $fees),
                'successful_payments' => $count,
                'success_rate' => $allCount > 0 ? round($count / $allCount, 6) : 0,
                'refunds' => DB::table('payment_refunds')->whereJsonContains('metadata->merchant_id', $merchant->id)->count(),
                'disputes' => DB::table('payment_disputes')->whereJsonContains('metadata->merchant_id', $merchant->id)->count(),
            ],
            'recent_payments' => DB::table('exaearn_pay_intents')->where('merchant_id', $merchant->id)->latest('id')->limit(20)->get(),
            'settlements' => DB::table('merchant_settlements')->where('merchant_id', $merchant->id)->latest('id')->limit(20)->get(),
        ];
    }

    public function reconcile(?Merchant $merchant = null): array
    {
        return DB::transaction(function () use ($merchant): array {
            $runId = (string) Str::uuid();
            $query = DB::table('exaearn_pay_intents')->where('status', 'CAPTURED');
            if ($merchant) {
                $query->where('merchant_id', $merchant->id);
            }

            $capturedWithoutLedger = (clone $query)->whereNull('ledger_reference')->count();
            $duplicateReferences = DB::table('exaearn_pay_intents')
                ->select('ledger_reference', DB::raw('COUNT(*) as count'))
                ->whereNotNull('ledger_reference')
                ->groupBy('ledger_reference')
                ->having('count', '>', 1)
                ->get();

            $runPk = DB::table('merchant_reconciliation_runs')->insertGetId([
                'run_id' => $runId,
                'merchant_id' => $merchant?->id,
                'status' => ($capturedWithoutLedger === 0 && $duplicateReferences->isEmpty()) ? 'PASS' : 'FAIL',
                'summary' => json_encode([
                    'captured_without_ledger' => $capturedWithoutLedger,
                    'duplicate_ledger_references' => $duplicateReferences->count(),
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($capturedWithoutLedger > 0) {
                DB::table('merchant_reconciliation_differences')->insert([
                    'merchant_reconciliation_run_id' => $runPk,
                    'severity' => 'CRITICAL',
                    'type' => 'CAPTURED_WITHOUT_LEDGER',
                    'difference_amount' => '0',
                    'metadata' => json_encode(['count' => $capturedWithoutLedger], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return (array) DB::table('merchant_reconciliation_runs')->where('id', $runPk)->first();
        });
    }

    public function recordWebhook(Merchant $merchant, string $eventType, string $resourceType, string $resourceId, array $payload): void
    {
        $event = MerchantWebhookEvent::query()->create([
            'event_id' => (string) Str::uuid(),
            'merchant_id' => $merchant->id,
            'event_type' => $eventType,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'payload' => $payload,
            'status' => 'PENDING',
        ]);

        $project = DeveloperProject::query()
            ->where('user_id', $merchant->user_id)
            ->where('environment', strtolower((string) $merchant->environment))
            ->first();
        if ($project) {
            $this->webhooks->enqueue($project, $eventType, $payload, (string) $event->event_id);
            $event->update(['status' => 'ENQUEUED']);
        }
    }

    private function pricingSnapshot(Merchant $merchant, string $amount, string $currency, string $paymentMethod): array
    {
        try {
            return $this->pricing->preview([
                'product' => 'EXAPAY',
                'operation' => 'PAYMENT_CAPTURE',
                'amount' => $amount,
                'currency' => $currency,
                'country' => $merchant->country,
                'merchant_tier' => $merchant->pricing_profile ?? 'STANDARD',
                'payment_method' => $paymentMethod,
            ]);
        } catch (RuntimeException) {
            $fee = FinancialDecimal::mul($amount, (string) config('fiat.fees.merchant_percent', '0'));

            return [
                'source' => 'LEGACY_CONFIG_FALLBACK',
                'product' => 'EXAPAY',
                'operation' => 'PAYMENT_CAPTURE',
                'gross_amount' => FinancialDecimal::normalize($amount),
                'fee_amount' => FinancialDecimal::normalize($fee),
                'provider_fee_amount' => '0.000000000000000000',
                'net_amount' => FinancialDecimal::sub($amount, $fee),
                'pricing_rule_id' => null,
                'rule_version' => null,
            ];
        }
    }

    private function assertActive(Merchant $merchant): void
    {
        if ($merchant->status !== 'ACTIVE' || $merchant->kyb_status !== 'APPROVED' || $merchant->risk_status === 'RESTRICTED') {
            throw new RuntimeException('Merchant is not active for ExaPay.');
        }
    }
}
