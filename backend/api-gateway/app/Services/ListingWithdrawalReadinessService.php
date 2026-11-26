<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\BlockchainAsset;
use App\Models\BlockchainNetwork;
use App\Models\ListingApplication;
use App\Models\ListingAssetNetworkConfiguration;
use App\Models\ListingWithdrawalActivationApproval;
use App\Models\ListingWithdrawalReadinessCheck;
use App\Services\Custody\BlockchainProviderManager;
use App\Services\Custody\DevelopmentSigningProvider;
use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ListingWithdrawalReadinessService
{
    public const REQUIRED_GATES = [
        'CUSTODY_PROVIDER_READY', 'SIGNER_READY', 'HOT_WALLET_READY', 'WALLET_LIQUIDITY_READY',
        'RPC_READY', 'ASSET_NETWORK_MAPPING_READY', 'DESTINATION_VALIDATION_READY', 'MEMO_TAG_POLICY_READY',
        'FEE_POLICY_READY', 'LIMIT_POLICY_READY', 'SECURITY_CONTROLS_READY', 'ACCOUNT_RISK_CONTROLS_READY',
        'WITHDRAWAL_RISK_ENGINE_READY', 'LEDGER_RESERVATION_READY', 'BROADCAST_READY',
        'CONFIRMATION_TRACKING_READY', 'RECONCILIATION_READY', 'OPERATIONAL_RUNBOOK_READY',
    ];

    public function __construct(
        private readonly BlockchainProviderManager $providers,
        private readonly DevelopmentSigningProvider $signer,
    ) {}

    public function fingerprint(ListingApplication $application): string
    {
        $signer = (string) config('custody.signing.provider', 'development_local');
        $rows = $application->networkConfigurations()->orderBy('id')->get()->map(function ($config) use ($signer): array {
            $network = BlockchainNetwork::query()->find($config->blockchain_network_id);
            $asset = BlockchainAsset::query()->find($config->blockchain_asset_id);
            $wallet = DB::table('custody_wallets')->where('network', strtolower((string) $config->network))->where('asset', $asset?->asset)->where('classification', 'HOT')->where('status', 'ACTIVE')->first();
            return [
                'network' => strtolower((string) $config->network), 'chain_id' => $network?->chain_id,
                'contract' => strtolower((string) $config->contract_address), 'decimals' => (int) $config->decimals,
                'token_standard' => $config->token_standard, 'required_confirmations' => (int) $config->required_confirmations,
                'finality_confirmations' => (int) $config->finality_confirmations, 'memo_required' => (bool) $config->memo_required,
                'minimum_withdrawal' => (string) $config->minimum_withdrawal, 'withdrawal_fee' => (string) $config->withdrawal_fee,
                'asset_id' => $asset?->id, 'asset_fee_policy' => $asset?->fee_policy, 'asset_metadata' => $asset?->metadata,
                'network_policy' => $network?->policy, 'signer' => $signer,
                'wallet' => $wallet ? ['id' => $wallet->id, 'wallet_id' => $wallet->wallet_id, 'policy' => $wallet->policy, 'metadata' => $wallet->metadata] : null,
            ];
        })->all();
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function evaluate(ListingApplication $application, ?Admin $admin = null): array
    {
        $application->loadMissing('networkConfigurations');
        $fingerprint = $this->fingerprint($application);
        $checks = $application->networkConfigurations->map(fn ($config) => $this->evaluateNetwork($application, $config, $fingerprint, $admin))->all();
        if ($checks === []) return ['overall_status' => 'BLOCKED', 'configuration_fingerprint' => $fingerprint, 'networks' => [], 'blocking_gates' => ['ASSET_NETWORK_MAPPING_READY']];
        $blocking = collect($checks)->flatMap(fn (array $check) => collect($check['gates'])->filter(fn (array $gate) => $gate['status'] !== 'PASS')->keys())->unique()->values()->all();
        return ['overall_status' => $blocking === [] ? 'PASS' : 'BLOCKED', 'configuration_fingerprint' => $fingerprint, 'networks' => $checks, 'blocking_gates' => $blocking];
    }

    public function requestApproval(ListingApplication $application, Admin $requester, string $reason): ListingWithdrawalActivationApproval
    {
        $snapshot = $this->evaluate($application, $requester);
        if ($snapshot['overall_status'] !== 'PASS') throw new RuntimeException('Withdrawal readiness is blocked by: '.implode(', ', $snapshot['blocking_gates']));
        ListingWithdrawalActivationApproval::query()->where('application_id', $application->id)->whereIn('status', ['REQUESTED', 'APPROVED'])->update(['status' => 'INVALIDATED']);
        return ListingWithdrawalActivationApproval::query()->create([
            'approval_uuid' => (string) Str::uuid(), 'application_id' => $application->id,
            'configuration_fingerprint' => $snapshot['configuration_fingerprint'], 'status' => 'REQUESTED',
            'requested_by_admin_id' => $requester->id, 'reason' => $reason,
            'readiness_snapshot' => $snapshot, 'requested_at' => now(),
        ]);
    }

    public function approve(ListingApplication $application, Admin $approver): ListingWithdrawalActivationApproval
    {
        $approval = ListingWithdrawalActivationApproval::query()->where('application_id', $application->id)->where('status', 'REQUESTED')->latest('id')->first();
        if (! $approval) throw new RuntimeException('A withdrawal activation request is required.');
        if ((int) $approval->requested_by_admin_id === (int) $approver->id) throw new RuntimeException('Withdrawal activation approver must differ from requester.');
        $snapshot = $this->evaluate($application, $approver);
        if ($snapshot['overall_status'] !== 'PASS' || ! hash_equals($approval->configuration_fingerprint, $snapshot['configuration_fingerprint'])) {
            $approval->update(['status' => 'INVALIDATED']);
            throw new RuntimeException('Withdrawal readiness changed; revalidation and a new request are required.');
        }
        $approval->update(['status' => 'APPROVED', 'approved_by_admin_id' => $approver->id, 'approved_at' => now(), 'readiness_snapshot' => $snapshot]);
        return $approval->fresh();
    }

    public function activationDecision(ListingApplication $application): array
    {
        $snapshot = $this->evaluate($application);
        $approval = ListingWithdrawalActivationApproval::query()->where('application_id', $application->id)->where('status', 'APPROVED')->latest('id')->first();
        $valid = $approval && $snapshot['overall_status'] === 'PASS' && hash_equals($approval->configuration_fingerprint, $snapshot['configuration_fingerprint']);
        return $snapshot + ['approved' => (bool) $valid, 'approval' => $approval];
    }

    private function evaluateNetwork(ListingApplication $application, ListingAssetNetworkConfiguration $config, string $fingerprint, ?Admin $admin): array
    {
        $networkName = strtolower((string) $config->network);
        $network = BlockchainNetwork::query()->find($config->blockchain_network_id);
        $asset = BlockchainAsset::query()->find($config->blockchain_asset_id);
        $metadata = (array) ($config->metadata ?? []); $operations = (array) ($metadata['withdrawal_readiness'] ?? []);
        $explicit = fn (string $key): string => in_array(($operations[$key] ?? null), ['PASS', 'FAIL', 'BLOCKED', 'NOT_CONNECTED', 'NOT_CONFIGURED', 'ERROR', 'NOT_APPLICABLE'], true) ? $operations[$key] : 'NOT_CONFIGURED';
        $providerStatus = 'NOT_CONFIGURED'; $rpcStatus = 'NOT_CONFIGURED'; $destinationStatus = 'NOT_CONFIGURED'; $rpcEvidence = [];
        try {
            $provider = $this->providers->providerFor($networkName);
            $providerStatus = $provider->providerState($networkName) === 'HEALTHY' && (bool) config('custody.production_enabled') ? 'PASS' : 'NOT_CONNECTED';
            if ($provider->providerState($networkName) === 'HEALTHY') {
                $block = $provider->getLatestBlock($networkName);
                $rpcStatus = ! empty($block['height']) && ! empty($block['hash']) ? 'PASS' : 'FAIL';
                $rpcEvidence = ['height' => $block['height'] ?? null, 'block_hash' => $block['hash'] ?? null];
                $testAddress = (string) ($operations['destination_test_address'] ?? '');
                $destinationStatus = $testAddress !== '' && $provider->validateAddress($networkName, $testAddress, $config->memo_required ? (string) ($operations['destination_test_memo'] ?? '') : null) ? 'PASS' : 'NOT_CONFIGURED';
            }
        } catch (Throwable $e) { $providerStatus = 'ERROR'; $rpcStatus = 'ERROR'; $destinationStatus = 'ERROR'; $rpcEvidence = ['error_class' => $e::class]; }

        $allowedSigners = (array) config('custody.signing.production_allowed_providers', []);
        $signerId = (string) config('custody.signing.provider', 'development_local');
        $signerHealth = $this->signer->healthCheck();
        $signerStatus = (bool) config('custody.production_enabled') && in_array($signerId, $allowedSigners, true) && ($signerHealth['state'] ?? null) === 'HEALTHY' ? 'PASS' : 'NOT_CONNECTED';
        $wallet = DB::table('custody_wallets')->where('network', $networkName)->where('asset', $asset?->asset)->where('classification', 'HOT')->where('status', 'ACTIVE')->first();
        $walletPolicy = $wallet ? (json_decode((string) ($wallet->policy ?? '{}'), true) ?: []) : [];
        $mapping = $network && $asset && (int) $asset->blockchain_network_id === (int) $network->id && strtolower((string) $asset->contract_address) === strtolower((string) $config->contract_address) && (int) $asset->decimals === (int) $config->decimals;
        $feeConfigured = (string) $config->withdrawal_fee !== '' && FinancialDecimal::compare((string) $config->withdrawal_fee, '0') >= 0 && (array) ($asset?->fee_policy ?? []) !== [];
        $limitsConfigured = FinancialDecimal::compare((string) $config->minimum_withdrawal, '0') > 0 && FinancialDecimal::compare((string) ($asset?->maximum_withdrawal ?? '0'), '0') > 0;
        $gates = [
            'CUSTODY_PROVIDER_READY' => ['status' => $providerStatus], 'SIGNER_READY' => ['status' => $signerStatus, 'evidence' => ['provider' => $signerId]],
            'HOT_WALLET_READY' => ['status' => $wallet && ($walletPolicy['signer_provider'] ?? null) === $signerId ? 'PASS' : 'NOT_CONFIGURED'],
            'WALLET_LIQUIDITY_READY' => ['status' => $wallet ? $explicit('wallet_liquidity') : 'NOT_CONFIGURED'],
            'RPC_READY' => ['status' => $rpcStatus, 'evidence' => $rpcEvidence], 'ASSET_NETWORK_MAPPING_READY' => ['status' => $mapping ? 'PASS' : 'FAIL'],
            'DESTINATION_VALIDATION_READY' => ['status' => $destinationStatus],
            'MEMO_TAG_POLICY_READY' => ['status' => (bool) $config->memo_required ? $explicit('memo_tag_policy') : 'PASS'],
            'FEE_POLICY_READY' => ['status' => $feeConfigured ? 'PASS' : 'NOT_CONFIGURED'], 'LIMIT_POLICY_READY' => ['status' => $limitsConfigured ? 'PASS' : 'NOT_CONFIGURED'],
            'SECURITY_CONTROLS_READY' => ['status' => $explicit('security_controls')], 'ACCOUNT_RISK_CONTROLS_READY' => ['status' => $explicit('account_risk_controls')],
            'WITHDRAWAL_RISK_ENGINE_READY' => ['status' => $explicit('withdrawal_risk_engine')], 'LEDGER_RESERVATION_READY' => ['status' => $explicit('ledger_reservation')],
            'BROADCAST_READY' => ['status' => $explicit('broadcast')], 'CONFIRMATION_TRACKING_READY' => ['status' => (int) $config->finality_confirmations > 0 ? $explicit('confirmation_tracking') : 'NOT_CONFIGURED'],
            'RECONCILIATION_READY' => ['status' => $explicit('reconciliation')], 'OPERATIONAL_RUNBOOK_READY' => ['status' => $explicit('operational_runbook')],
        ];
        $overall = collect($gates)->every(fn (array $gate) => $gate['status'] === 'PASS') ? 'PASS' : 'BLOCKED'; $correlation = (string) Str::uuid();
        ListingWithdrawalReadinessCheck::query()->create([
            'check_uuid' => (string) Str::uuid(), 'application_id' => $application->id, 'listing_asset_network_configuration_id' => $config->id,
            'configuration_fingerprint' => $fingerprint, 'overall_status' => $overall, 'gates' => $gates,
            'evidence' => ['asset_id' => $asset?->id, 'network_id' => $network?->id, 'provider_environment' => app()->environment()],
            'correlation_id' => $correlation, 'checked_by_admin_id' => $admin?->id, 'checked_at' => now(),
        ]);
        return ['network_configuration_id' => $config->id, 'network' => $config->network, 'overall_status' => $overall, 'gates' => $gates, 'correlation_id' => $correlation];
    }
}
