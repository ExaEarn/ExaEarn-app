<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\BlockchainAsset;
use App\Models\BlockchainNetwork;
use App\Models\ListingApplication;
use App\Models\ListingAssetNetworkConfiguration;
use App\Models\ListingDepositActivationApproval;
use App\Models\ListingDepositReadinessCheck;
use App\Services\Custody\BlockchainProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ListingDepositReadinessService
{
    public const REQUIRED_GATES = [
        'CUSTODY_PROVIDER_READY', 'RPC_READY', 'ASSET_NETWORK_MAPPING_READY',
        'ADDRESS_GENERATION_READY', 'MEMO_TAG_POLICY_READY', 'DEPOSIT_MONITOR_READY',
        'CONFIRMATION_POLICY_READY', 'LEDGER_POSTING_READY', 'RECONCILIATION_READY',
        'RISK_CONTROLS_READY', 'SECURITY_CONTROLS_READY', 'OPERATIONAL_RUNBOOK_READY',
    ];

    public function __construct(private readonly BlockchainProviderManager $providers) {}

    public function fingerprint(ListingApplication $application): string
    {
        $rows = $application->networkConfigurations()->with([])->orderBy('id')->get()->map(function ($config): array {
            $network = BlockchainNetwork::query()->find($config->blockchain_network_id);
            $asset = BlockchainAsset::query()->find($config->blockchain_asset_id);
            return [
                'network' => strtolower((string) $config->network), 'chain_id' => $network?->chain_id,
                'contract' => strtolower((string) $config->contract_address), 'decimals' => (int) $config->decimals,
                'token_standard' => $config->token_standard, 'required_confirmations' => (int) $config->required_confirmations,
                'finality_confirmations' => (int) $config->finality_confirmations, 'memo_required' => (bool) $config->memo_required,
                'minimum_deposit' => (string) $config->minimum_deposit, 'asset_id' => $asset?->id,
                'asset_metadata' => $asset?->metadata, 'network_policy' => $network?->policy,
            ];
        })->all();

        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function evaluate(ListingApplication $application, ?Admin $admin = null): array
    {
        $application->loadMissing('networkConfigurations');
        $fingerprint = $this->fingerprint($application);
        $checks = [];
        foreach ($application->networkConfigurations as $config) {
            $checks[] = $this->evaluateNetwork($application, $config, $fingerprint, $admin);
        }
        if ($checks === []) {
            return ['overall_status' => 'BLOCKED', 'configuration_fingerprint' => $fingerprint, 'networks' => [], 'blocking_gates' => ['ASSET_NETWORK_MAPPING_READY']];
        }
        $blocking = collect($checks)->flatMap(fn (array $check) => collect($check['gates'])->filter(fn (array $gate) => $gate['status'] !== 'PASS')->keys())->unique()->values()->all();

        return ['overall_status' => $blocking === [] ? 'PASS' : 'BLOCKED', 'configuration_fingerprint' => $fingerprint, 'networks' => $checks, 'blocking_gates' => $blocking];
    }

    public function requestApproval(ListingApplication $application, Admin $requester, string $reason): ListingDepositActivationApproval
    {
        $snapshot = $this->evaluate($application, $requester);
        if ($snapshot['overall_status'] !== 'PASS') {
            throw new RuntimeException('Deposit readiness is blocked by: '.implode(', ', $snapshot['blocking_gates']));
        }
        ListingDepositActivationApproval::query()->where('application_id', $application->id)->whereIn('status', ['REQUESTED', 'APPROVED'])->update(['status' => 'INVALIDATED']);
        return ListingDepositActivationApproval::query()->create([
            'approval_uuid' => (string) Str::uuid(), 'application_id' => $application->id,
            'configuration_fingerprint' => $snapshot['configuration_fingerprint'], 'status' => 'REQUESTED',
            'requested_by_admin_id' => $requester->id, 'reason' => $reason,
            'readiness_snapshot' => $snapshot, 'requested_at' => now(),
        ]);
    }

    public function approve(ListingApplication $application, Admin $approver): ListingDepositActivationApproval
    {
        $approval = ListingDepositActivationApproval::query()->where('application_id', $application->id)->where('status', 'REQUESTED')->latest('id')->first();
        if (! $approval) throw new RuntimeException('A deposit activation request is required.');
        if ((int) $approval->requested_by_admin_id === (int) $approver->id) throw new RuntimeException('Deposit activation approver must differ from requester.');
        $snapshot = $this->evaluate($application, $approver);
        if ($snapshot['overall_status'] !== 'PASS' || $approval->configuration_fingerprint !== $snapshot['configuration_fingerprint']) {
            $approval->update(['status' => 'INVALIDATED']);
            throw new RuntimeException('Deposit readiness changed; revalidation and a new request are required.');
        }
        $approval->update(['status' => 'APPROVED', 'approved_by_admin_id' => $approver->id, 'approved_at' => now(), 'readiness_snapshot' => $snapshot]);
        return $approval->fresh();
    }

    public function activationDecision(ListingApplication $application): array
    {
        $snapshot = $this->evaluate($application);
        $approval = ListingDepositActivationApproval::query()->where('application_id', $application->id)->where('status', 'APPROVED')->latest('id')->first();
        $valid = $approval && $snapshot['overall_status'] === 'PASS' && hash_equals($approval->configuration_fingerprint, $snapshot['configuration_fingerprint']);
        return $snapshot + ['approved' => (bool) $valid, 'approval' => $approval];
    }

    private function evaluateNetwork(ListingApplication $application, ListingAssetNetworkConfiguration $config, string $fingerprint, ?Admin $admin): array
    {
        $network = BlockchainNetwork::query()->find($config->blockchain_network_id);
        $asset = BlockchainAsset::query()->find($config->blockchain_asset_id);
        $metadata = (array) ($config->metadata ?? []);
        $operations = (array) ($metadata['deposit_readiness'] ?? []);
        $providerState = 'NOT_CONFIGURED'; $rpcStatus = 'NOT_CONFIGURED'; $rpcEvidence = [];
        try {
            $provider = $this->providers->providerFor(strtolower((string) $config->network));
            $providerState = $provider->providerState(strtolower((string) $config->network)) === 'HEALTHY' ? 'PASS' : 'NOT_CONNECTED';
            if ($providerState === 'PASS') {
                $block = $provider->getLatestBlock(strtolower((string) $config->network));
                $rpcStatus = ! empty($block['height']) && ! empty($block['hash']) ? 'PASS' : 'FAIL';
                $rpcEvidence = ['height' => $block['height'] ?? null, 'block_hash' => $block['hash'] ?? null];
            }
        } catch (Throwable $e) { $providerState = 'ERROR'; $rpcStatus = 'ERROR'; $rpcEvidence = ['error_class' => $e::class]; }

        $wallet = DB::table('custody_wallets')->where('network', strtolower((string) $config->network))->whereIn('classification', ['USER_DEPOSIT', 'HOT'])->where('status', 'ACTIVE')->first();
        $mapping = $network && $asset && (int) $asset->blockchain_network_id === (int) $network->id
            && strtolower((string) $asset->contract_address) === strtolower((string) $config->contract_address)
            && (int) $asset->decimals === (int) $config->decimals;
        $explicit = fn (string $key): string => in_array(($operations[$key] ?? null), ['PASS', 'FAIL', 'BLOCKED', 'NOT_CONNECTED', 'NOT_CONFIGURED', 'ERROR', 'NOT_APPLICABLE'], true) ? $operations[$key] : 'NOT_CONFIGURED';
        $gates = [
            'CUSTODY_PROVIDER_READY' => ['status' => (bool) config('custody.production_enabled') ? $providerState : 'NOT_CONNECTED'],
            'RPC_READY' => ['status' => $rpcStatus, 'evidence' => $rpcEvidence],
            'ASSET_NETWORK_MAPPING_READY' => ['status' => $mapping ? 'PASS' : 'FAIL'],
            'ADDRESS_GENERATION_READY' => ['status' => $wallet ? $explicit('address_generation') : 'NOT_CONFIGURED'],
            'MEMO_TAG_POLICY_READY' => ['status' => (bool) $config->memo_required ? $explicit('memo_tag_policy') : 'PASS'],
            'DEPOSIT_MONITOR_READY' => ['status' => $explicit('deposit_monitor')],
            'CONFIRMATION_POLICY_READY' => ['status' => (int) $config->required_confirmations > 0 && (int) $config->finality_confirmations >= (int) $config->required_confirmations ? 'PASS' : 'NOT_CONFIGURED'],
            'LEDGER_POSTING_READY' => ['status' => $explicit('ledger_posting')],
            'RECONCILIATION_READY' => ['status' => $explicit('reconciliation')],
            'RISK_CONTROLS_READY' => ['status' => $explicit('risk_controls')],
            'SECURITY_CONTROLS_READY' => ['status' => $explicit('security_controls')],
            'OPERATIONAL_RUNBOOK_READY' => ['status' => $explicit('operational_runbook')],
        ];
        $overall = collect($gates)->every(fn (array $gate) => $gate['status'] === 'PASS') ? 'PASS' : 'BLOCKED';
        $correlation = (string) Str::uuid();
        ListingDepositReadinessCheck::query()->create([
            'check_uuid' => (string) Str::uuid(), 'application_id' => $application->id,
            'listing_asset_network_configuration_id' => $config->id, 'configuration_fingerprint' => $fingerprint,
            'overall_status' => $overall, 'gates' => $gates,
            'evidence' => ['asset_id' => $asset?->id, 'network_id' => $network?->id, 'provider_environment' => app()->environment()],
            'correlation_id' => $correlation, 'checked_by_admin_id' => $admin?->id, 'checked_at' => now(),
        ]);
        return ['network_configuration_id' => $config->id, 'network' => $config->network, 'overall_status' => $overall, 'gates' => $gates, 'correlation_id' => $correlation];
    }
}
