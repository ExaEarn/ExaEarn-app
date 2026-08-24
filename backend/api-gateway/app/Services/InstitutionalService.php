<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Admin;
use App\Models\InstitutionalAccount;
use App\Models\InstitutionalApplication;
use App\Models\InstitutionalAuditEvent;
use App\Models\InstitutionalFeeProfile;
use App\Models\InstitutionalMemberSubaccountPermission;
use App\Models\InstitutionalMembership;
use App\Models\InstitutionalReport;
use App\Models\InstitutionalRiskProfile;
use App\Models\InstitutionalRole;
use App\Models\InstitutionalSubaccount;
use App\Models\InstitutionalTransferApproval;
use App\Models\InstitutionalTransferRequest;
use App\Models\LedgerTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InstitutionalService
{
    public const OWNER_PERMISSIONS = [
        'VIEW_BALANCES', 'VIEW_ORDERS', 'PLACE_SPOT_ORDERS', 'PLACE_FUTURES_ORDERS', 'CANCEL_ORDERS',
        'VIEW_POSITIONS', 'VIEW_TRADES', 'INTERNAL_TRANSFER', 'REQUEST_LARGE_TRANSFER', 'APPROVE_TRANSFER',
        'CREATE_API_KEY', 'REVOKE_API_KEY', 'VIEW_REPORTS', 'EXPORT_REPORTS', 'MANAGE_TEAM',
        'MANAGE_SUBACCOUNTS', 'MANAGE_SECURITY', 'MANAGE_RISK', 'VIEW_OTC', 'REQUEST_OTC_QUOTE',
        'ACCEPT_OTC_QUOTE', 'OTC_APPROVER',
    ];

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly BalanceProjectionService $balances,
        private readonly InstitutionalRealtimeService $realtime,
    ) {
    }

    public function apply(User $user, array $payload, ?Request $request = null): InstitutionalApplication
    {
        $application = InstitutionalApplication::query()->create(array_merge($payload, [
            'application_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'state' => 'APPLICATION_PENDING',
            'kyb_status' => 'PENDING',
            'compliance_status' => 'PENDING',
            'risk_rating' => 'UNRATED',
        ]));

        $this->audit(null, null, 'user', $user->id, 'institution.application.submitted', 'institutional_application', $application->id, null, $application->toArray(), null, $request);

        return $application;
    }

    public function transitionApplication(Admin $admin, InstitutionalApplication $application, string $state, string $reason, ?Request $request = null): InstitutionalApplication
    {
        $allowed = [
            'APPLICATION_PENDING' => ['KYB_PENDING', 'KYB_REVIEW', 'RESTRICTED', 'CLOSED'],
            'KYB_PENDING' => ['KYB_REVIEW', 'RESTRICTED', 'CLOSED'],
            'KYB_REVIEW' => ['COMPLIANCE_REVIEW', 'RESTRICTED', 'CLOSED'],
            'COMPLIANCE_REVIEW' => ['RISK_REVIEW', 'RESTRICTED', 'CLOSED'],
            'RISK_REVIEW' => ['COMMERCIAL_REVIEW', 'RESTRICTED', 'CLOSED'],
            'COMMERCIAL_REVIEW' => ['APPROVED', 'RESTRICTED', 'CLOSED'],
            'APPROVED' => ['ACTIVE', 'RESTRICTED', 'SUSPENDED', 'CLOSED'],
            'ACTIVE' => ['RESTRICTED', 'SUSPENDED', 'CLOSED'],
            'RESTRICTED' => ['ACTIVE', 'SUSPENDED', 'CLOSED'],
            'SUSPENDED' => ['RESTRICTED', 'CLOSED'],
        ];
        $current = (string) $application->state;
        if (! in_array($state, $allowed[$current] ?? [], true)) {
            throw new RuntimeException("Invalid institutional application state transition {$current} -> {$state}.");
        }

        $before = $application->toArray();
        $application->forceFill([
            'state' => $state,
            'recommended_by_admin_id' => $state === 'APPROVED' ? $admin->id : $application->recommended_by_admin_id,
            'approved_at' => $state === 'APPROVED' ? now() : $application->approved_at,
        ])->save();
        $this->audit(null, null, 'admin', $admin->id, 'institution.application.transitioned', 'institutional_application', $application->id, $before, $application->fresh()->toArray(), $reason, $request);

        return $application->fresh();
    }

    public function activate(Admin $admin, InstitutionalApplication $application, string $reason, ?Request $request = null): InstitutionalAccount
    {
        if ($application->state !== 'APPROVED') {
            throw new RuntimeException('Application must be approved before activation.');
        }
        if ((int) $application->recommended_by_admin_id === (int) $admin->id) {
            throw new RuntimeException('Activation approver must be different from maker.');
        }

        return DB::transaction(function () use ($admin, $application, $reason, $request): InstitutionalAccount {
            $institution = InstitutionalAccount::query()->firstOrCreate(
                ['application_id' => $application->id],
                [
                    'institution_uuid' => (string) Str::uuid(),
                    'master_user_id' => $application->user_id,
                    'legal_name' => $application->legal_company_name,
                    'trading_name' => $application->trading_name,
                    'registration_number' => $application->registration_number,
                    'country_of_incorporation' => $application->incorporation_country,
                    'business_type' => $application->business_type,
                    'status' => 'ACTIVE',
                    'kyb_status' => 'APPROVED',
                    'compliance_status' => 'APPROVED',
                    'risk_rating' => $application->risk_rating === 'UNRATED' ? 'MEDIUM' : $application->risk_rating,
                    'vip_tier' => 'STANDARD',
                    'capability_flags' => [
                        'institutional' => true,
                        'market_maker_interest' => (bool) $application->market_making_interest,
                        'otc_interest' => (bool) $application->otc_interest,
                    ],
                    'approved_at' => $application->approved_at ?? now(),
                    'activated_at' => now(),
                ]
            );

            $ownerRole = InstitutionalRole::query()->firstOrCreate(
                ['institution_id' => $institution->id, 'name' => 'OWNER'],
                ['role_type' => 'OWNER', 'permissions' => self::OWNER_PERMISSIONS, 'system_template' => true]
            );
            InstitutionalMembership::query()->firstOrCreate(
                ['institution_id' => $institution->id, 'user_id' => $application->user_id],
                ['membership_uuid' => (string) Str::uuid(), 'role_id' => $ownerRole->id, 'status' => 'ACTIVE', 'invited_at' => now(), 'accepted_at' => now()]
            );

            foreach ([['Treasury', 'TREASURY'], ['General Trading', 'GENERAL']] as [$name, $type]) {
                InstitutionalSubaccount::query()->firstOrCreate(
                    ['institution_id' => $institution->id, 'name' => $name],
                    ['subaccount_uuid' => (string) Str::uuid(), 'type' => $type, 'status' => 'ACTIVE', 'risk_mode' => 'ISOLATED', 'product_flags' => []]
                );
            }

            $application->forceFill(['state' => 'ACTIVE', 'approved_by_admin_id' => $admin->id])->save();
            $this->audit($institution->id, null, 'admin', $admin->id, 'institution.activated', 'institutional_account', $institution->id, null, $institution->toArray(), $reason, $request);

            return $institution->fresh(['subaccounts', 'memberships']);
        });
    }

    public function createSubaccount(User $actor, InstitutionalAccount $institution, array $payload, ?Request $request = null): InstitutionalSubaccount
    {
        $this->assertInstitutionPermission($actor, $institution, 'MANAGE_SUBACCOUNTS');

        $subaccount = InstitutionalSubaccount::query()->create([
            'subaccount_uuid' => (string) Str::uuid(),
            'institution_id' => $institution->id,
            'name' => (string) $payload['name'],
            'type' => strtoupper((string) ($payload['type'] ?? 'GENERAL')),
            'status' => 'ACTIVE',
            'risk_mode' => 'ISOLATED',
            'product_flags' => $payload['product_flags'] ?? [],
            'metadata' => $payload['metadata'] ?? [],
        ]);
        $this->audit($institution->id, $subaccount->id, 'user', $actor->id, 'institution.subaccount.created', 'institutional_subaccount', $subaccount->id, null, $subaccount->toArray(), null, $request);

        return $subaccount;
    }

    public function grantSubaccountPermission(User $actor, InstitutionalMembership $membership, InstitutionalSubaccount $subaccount, string $permission, ?Request $request = null): InstitutionalMemberSubaccountPermission
    {
        if ((int) $membership->institution_id !== (int) $subaccount->institution_id) {
            throw new RuntimeException('Membership and subaccount institution mismatch.');
        }
        $this->assertInstitutionPermission($actor, $subaccount->institution, 'MANAGE_TEAM');

        $grant = InstitutionalMemberSubaccountPermission::query()->firstOrCreate([
            'membership_id' => $membership->id,
            'subaccount_id' => $subaccount->id,
            'permission' => strtoupper($permission),
        ]);
        $this->audit($subaccount->institution_id, $subaccount->id, 'user', $actor->id, 'institution.permission.granted', 'institutional_member_subaccount_permission', $grant->id, null, $grant->toArray(), null, $request);

        return $grant;
    }

    public function createTransfer(User $actor, InstitutionalAccount $institution, InstitutionalSubaccount $source, InstitutionalSubaccount $destination, array $payload, ?Request $request = null): InstitutionalTransferRequest
    {
        if ((int) $source->institution_id !== (int) $institution->id || (int) $destination->institution_id !== (int) $institution->id) {
            throw new RuntimeException('Transfer subaccounts must belong to the same institution.');
        }
        $this->assertSubaccountPermission($actor, $source, 'INTERNAL_TRANSFER');

        return DB::transaction(function () use ($actor, $destination, $institution, $payload, $request, $source): InstitutionalTransferRequest {
            $idempotencyKey = (string) $payload['idempotency_key'];
            $existing = InstitutionalTransferRequest::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing->fresh();
            }

            $asset = strtoupper((string) $payload['asset']);
            $amount = FinancialDecimal::normalize((string) $payload['amount']);
            if (FinancialDecimal::compare($amount, '0') <= 0) {
                throw new RuntimeException('Transfer amount must be greater than zero.');
            }

            $threshold = FinancialDecimal::normalize((string) ($payload['approval_threshold'] ?? config('institutional.large_transfer_threshold.'.$asset, '50000')));
            $requiresApproval = FinancialDecimal::compare($amount, $threshold) > 0;
            $transfer = InstitutionalTransferRequest::query()->create([
                'transfer_uuid' => (string) Str::uuid(),
                'institution_id' => $institution->id,
                'source_subaccount_id' => $source->id,
                'destination_subaccount_id' => $destination->id,
                'initiated_by_user_id' => $actor->id,
                'asset' => $asset,
                'amount' => $amount,
                'status' => $requiresApproval ? 'PENDING_APPROVAL' : 'PENDING',
                'idempotency_key' => $idempotencyKey,
                'approval_policy' => $requiresApproval ? 'MAKER_CHECKER' : 'AUTO',
                'approval_threshold' => $threshold,
                'reference_note' => $payload['reference_note'] ?? null,
                'metadata' => ['risk_mode' => 'ISOLATED'],
            ]);

            $this->audit($institution->id, $source->id, 'user', $actor->id, 'institution.transfer.requested', 'institutional_transfer_request', $transfer->id, null, $transfer->toArray(), $payload['reference_note'] ?? null, $request);
            if (! $requiresApproval) {
                return $this->settleTransfer($transfer, $actor, 'Auto-approved below maker-checker threshold.', $request);
            }

            return $transfer->fresh();
        });
    }

    public function approveTransfer(User $approver, InstitutionalTransferRequest $transfer, string $reason, ?Request $request = null): InstitutionalTransferRequest
    {
        if ($transfer->status !== 'PENDING_APPROVAL') {
            throw new RuntimeException('Only pending approval transfers can be approved.');
        }
        if ((int) $transfer->initiated_by_user_id === (int) $approver->id) {
            throw new RuntimeException('Requester cannot approve their own transfer.');
        }
        $source = InstitutionalSubaccount::query()->findOrFail($transfer->source_subaccount_id);
        $this->assertSubaccountPermission($approver, $source, 'APPROVE_TRANSFER');

        InstitutionalTransferApproval::query()->firstOrCreate(
            ['transfer_id' => $transfer->id, 'approver_user_id' => $approver->id],
            ['decision' => 'APPROVED', 'reason' => $reason, 'decided_at' => now()]
        );

        return $this->settleTransfer($transfer->fresh(), $approver, $reason, $request);
    }

    public function settleTransfer(InstitutionalTransferRequest $transfer, User $actor, string $reason, ?Request $request = null): InstitutionalTransferRequest
    {
        return DB::transaction(function () use ($actor, $reason, $request, $transfer): InstitutionalTransferRequest {
            $transfer = InstitutionalTransferRequest::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($transfer->status === 'COMPLETED') {
                return $transfer;
            }
            if (! in_array($transfer->status, ['PENDING', 'PENDING_APPROVAL'], true)) {
                throw new RuntimeException('Transfer is not settleable.');
            }

            $sourceAccount = $this->canonicalSubaccountLedgerAccount((int) $transfer->source_subaccount_id, (string) $transfer->asset);
            $destinationAccount = $this->canonicalSubaccountLedgerAccount((int) $transfer->destination_subaccount_id, (string) $transfer->asset);
            $available = $this->balances->accountProjection($sourceAccount)['available'];
            if (FinancialDecimal::compare($available, (string) $transfer->amount) < 0) {
                throw new RuntimeException('Insufficient source subaccount available balance.');
            }

            $reference = 'INST-TRANSFER-'.$transfer->transfer_uuid;
            $this->ledger->postDoubleEntry($reference, 'Institutional internal transfer', [
                ['account_id' => $sourceAccount->id, 'amount' => FinancialDecimal::sub('0', (string) $transfer->amount), 'asset' => $transfer->asset, 'metadata' => ['transfer_id' => $transfer->id]],
                ['account_id' => $destinationAccount->id, 'amount' => (string) $transfer->amount, 'asset' => $transfer->asset, 'metadata' => ['transfer_id' => $transfer->id]],
            ], 'institutional_internal_transfer', [
                'source_service' => 'institutional',
                'institution_id' => $transfer->institution_id,
                'source_subaccount_id' => $transfer->source_subaccount_id,
                'destination_subaccount_id' => $transfer->destination_subaccount_id,
                'initiated_by_type' => 'user',
                'initiated_by_id' => $actor->id,
            ]);

            $before = $transfer->toArray();
            $transfer->forceFill([
                'status' => 'COMPLETED',
                'approved_by_user_id' => $transfer->approved_by_user_id ?? $actor->id,
                'approved_at' => $transfer->approved_at ?? now(),
                'completed_at' => now(),
                'ledger_reference' => $reference,
            ])->save();
            $this->audit($transfer->institution_id, $transfer->source_subaccount_id, 'user', $actor->id, 'institution.transfer.completed', 'institutional_transfer_request', $transfer->id, $before, $transfer->fresh()->toArray(), $reason, $request);

            return $transfer->fresh();
        });
    }

    public function canonicalSubaccountLedgerAccount(int $subaccountId, string $asset): Account
    {
        $subaccount = InstitutionalSubaccount::query()->findOrFail($subaccountId);
        $asset = strtoupper($asset);

        return Account::query()->firstOrCreate([
            'owner_type' => 'institutional_subaccount',
            'owner_id' => $subaccount->id,
            'account_type' => 'subaccount:'.strtolower((string) $subaccount->type),
            'asset' => $asset,
        ], [
            'user_id' => null,
            'balance' => '0',
            'status' => 'active',
            'metadata' => ['institution_id' => $subaccount->institution_id, 'subaccount_uuid' => $subaccount->subaccount_uuid],
        ]);
    }

    public function adminCreditSubaccount(Admin $admin, InstitutionalSubaccount $subaccount, string $asset, string $amount, string $reason, ?Request $request = null): LedgerTransaction
    {
        $account = $this->canonicalSubaccountLedgerAccount($subaccount->id, $asset);
        $treasury = $this->ledger->getOrCreateAccount(null, 'treasury', $asset);
        $reference = 'INST-CREDIT-'.$subaccount->subaccount_uuid.'-'.Str::uuid();
        $tx = $this->ledger->postDoubleEntry($reference, 'Institutional subaccount credit', [
            ['account_id' => $treasury->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => $asset, 'metadata' => ['admin_credit' => true]],
            ['account_id' => $account->id, 'amount' => $amount, 'asset' => $asset, 'metadata' => ['admin_credit' => true]],
        ], 'institutional_credit', ['source_service' => 'institutional', 'initiated_by_type' => 'admin', 'initiated_by_id' => $admin->id]);
        $this->audit($subaccount->institution_id, $subaccount->id, 'admin', $admin->id, 'institution.subaccount.credit', 'ledger_transaction', $tx->id, null, $tx->toArray(), $reason, $request);

        return $tx;
    }

    public function report(InstitutionalAccount $institution, ?User $actor = null): InstitutionalReport
    {
        $subaccounts = $institution->subaccounts()->get();
        $rows = [];
        $totals = [];
        foreach ($subaccounts as $subaccount) {
            $accounts = Account::query()->where('owner_type', 'institutional_subaccount')->where('owner_id', $subaccount->id)->get();
            foreach ($accounts as $account) {
                $projection = $this->balances->accountProjection($account);
                $rows[] = ['subaccount_id' => $subaccount->id, 'subaccount' => $subaccount->name, 'asset' => $account->asset, 'total' => $projection['total'], 'available' => $projection['available'], 'reserved' => $projection['reserved']];
                $totals[$account->asset] = FinancialDecimal::add($totals[$account->asset] ?? '0', $projection['total']);
            }
        }

        return InstitutionalReport::query()->create([
            'report_uuid' => (string) Str::uuid(),
            'institution_id' => $institution->id,
            'requested_by_user_id' => $actor?->id,
            'report_type' => 'CONSOLIDATED_BALANCE',
            'status' => 'COMPLETED',
            'summary' => ['totals_by_asset' => $totals, 'subaccounts' => $rows, 'internal_transfers_excluded_from_revenue' => true],
        ]);
    }

    public function assertInstitutionPermission(User $user, InstitutionalAccount $institution, string $permission): InstitutionalMembership
    {
        $membership = InstitutionalMembership::query()->with('role')->where('institution_id', $institution->id)->where('user_id', $user->id)->where('status', 'ACTIVE')->first();
        if (! $membership) {
            throw new RuntimeException('Institution membership is required.');
        }
        $permissions = array_merge($membership->role?->permissions ?? [], $membership->permissions_override ?? []);
        if (! in_array($permission, $permissions, true)) {
            throw new RuntimeException("Institution permission {$permission} is required.");
        }

        return $membership;
    }

    public function assertSubaccountPermission(User $user, InstitutionalSubaccount $subaccount, string $permission): InstitutionalMembership
    {
        $membership = $this->assertInstitutionPermission($user, $subaccount->institution, $permission);
        if (($membership->role?->role_type ?? '') === 'OWNER') {
            return $membership;
        }
        $hasScoped = InstitutionalMemberSubaccountPermission::query()
            ->where('membership_id', $membership->id)
            ->where('subaccount_id', $subaccount->id)
            ->where('permission', $permission)
            ->exists();
        if (! $hasScoped) {
            throw new RuntimeException('Subaccount-scoped permission is required.');
        }

        return $membership;
    }

    public function createFeeProfile(Admin $admin, array $payload, ?Request $request = null): InstitutionalFeeProfile
    {
        $profile = InstitutionalFeeProfile::query()->create([
            'fee_profile_uuid' => (string) Str::uuid(),
            'name' => (string) $payload['name'],
            'rules' => $payload['rules'],
            'status' => 'ACTIVE',
            'created_by_admin_id' => $admin->id,
            'reason' => $payload['reason'] ?? null,
            'effective_at' => $payload['effective_at'] ?? now(),
        ]);
        $this->audit(null, null, 'admin', $admin->id, 'institution.fee_profile.created', 'institutional_fee_profile', $profile->id, null, $profile->toArray(), $payload['reason'] ?? null, $request);

        return $profile;
    }

    public function audit(?int $institutionId, ?int $subaccountId, string $actorType, ?int $actorId, string $action, ?string $resourceType, ?int $resourceId, ?array $before, ?array $after, ?string $reason, ?Request $request = null): void
    {
        $event = InstitutionalAuditEvent::query()->create([
            'event_uuid' => (string) Str::uuid(),
            'institution_id' => $institutionId,
            'subaccount_id' => $subaccountId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'ip_address' => $request?->ip(),
        ]);

        if ($actorType === 'user' && $actorId) {
            $stream = $institutionId ? "institution.{$institutionId}" : 'institution.applications';
            $this->realtime->publish((int) $actorId, $stream, $action, [
                'audit_event_id' => $event->event_uuid,
                'institution_id' => $institutionId,
                'subaccount_id' => $subaccountId,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'reason' => $reason,
            ]);
        }
    }
}
