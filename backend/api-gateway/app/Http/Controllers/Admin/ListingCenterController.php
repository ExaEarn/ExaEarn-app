<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\BlockchainAsset;
use App\Models\BlockchainNetwork;
use App\Models\ListingApplication;
use App\Models\ListingAuditLog;
use App\Models\ListingLaunchSchedule;
use App\Models\ListingLaunchEvent;
use App\Models\ListingContractValidation;
use App\Models\ListingTestRun;
use App\Models\ListingTokenMigration;
use App\Models\ListingOrganization;
use App\Models\ListingOrganizationKybReview;
use App\Models\Market;
use App\Services\ListingLifecycleService;
use App\Services\ListingLaunchDispatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class ListingCenterController extends Controller
{
    public function __construct(private readonly ListingLifecycleService $listings)
    {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['data' => [
            'applications' => ListingApplication::query()->count(),
            'submitted' => ListingApplication::query()->where('application_status', 'SUBMITTED')->count(),
            'approved' => ListingApplication::query()->where('application_status', 'APPROVED')->count(),
            'integration' => ListingApplication::query()->whereIn('integration_status', ['INTEGRATION', 'ASSET_CONFIGURATION', 'MARKET_CREATED', 'TESTING', 'READY_FOR_LISTING'])->count(),
            'scheduled' => ListingApplication::query()->where('integration_status', 'SCHEDULED')->count(),
            'live' => ListingApplication::query()->where('integration_status', 'LIVE')->count(),
            'networks' => BlockchainNetwork::query()->count(),
            'registered_assets' => BlockchainAsset::query()->count(),
            'markets' => Market::query()->count(),
        ]]);
    }

    public function documentSecurity(\App\Services\ListingDocumentSecurityService $security): JsonResponse
    {
        return response()->json(['data' => $security->health()]);
    }

    public function applications(Request $request): JsonResponse
    {
        $query = ListingApplication::query()->with(['organization', 'reviews', 'assetConfiguration', 'networkConfigurations', 'contractValidations', 'marketConfigurations', 'schedule'])->latest();
        if ($request->filled('status')) {
            $query->where('application_status', strtoupper((string) $request->query('status')));
        }
        if ($request->filled('integration_status')) {
            $query->where('integration_status', strtoupper((string) $request->query('integration_status')));
        }

        return response()->json(['data' => $query->paginate((int) $request->query('per_page', 25))]);
    }

    public function kybOrganizations(Request $request): JsonResponse
    {
        $query=ListingOrganization::query()->with(['people','kybDocuments','kybReviews'])->latest();
        if($request->filled('status'))$query->where('kyb_status',strtoupper((string)$request->query('status')));
        return response()->json(['data'=>$query->paginate((int)$request->query('per_page',25))]);
    }

    public function kybDecision(Request $request, int $organizationId): JsonResponse
    {
        $payload=$request->validate(['status'=>['required','in:UNDER_REVIEW,INFORMATION_REQUESTED,ENHANCED_DUE_DILIGENCE,APPROVED,REJECTED,RESTRICTED,EXPIRED,REVERIFICATION_REQUIRED'],'public_message'=>['nullable','string','max:5000'],'internal_notes'=>['nullable','string','max:5000'],'manual_verification_evidence'=>['nullable','array']]);
        $organization=ListingOrganization::query()->findOrFail($organizationId); $allowed=['SUBMITTED'=>['UNDER_REVIEW','INFORMATION_REQUESTED','ENHANCED_DUE_DILIGENCE','APPROVED','REJECTED','RESTRICTED'],'UNDER_REVIEW'=>['INFORMATION_REQUESTED','ENHANCED_DUE_DILIGENCE','APPROVED','REJECTED','RESTRICTED'],'INFORMATION_REQUESTED'=>['UNDER_REVIEW','REJECTED'],'ENHANCED_DUE_DILIGENCE'=>['INFORMATION_REQUESTED','APPROVED','REJECTED','RESTRICTED'],'APPROVED'=>['EXPIRED','REVERIFICATION_REQUIRED','RESTRICTED'],'REVERIFICATION_REQUIRED'=>['UNDER_REVIEW','RESTRICTED']];
        if(!in_array($payload['status'],$allowed[$organization->kyb_status]??[],true))return response()->json(['message'=>'Illegal KYB state transition.'],422);
        if($payload['status']==='APPROVED') { try { app(\App\Services\ListingDocumentSecurityService::class)->assertKybDocumentsClean($organization); } catch(RuntimeException $e) { return response()->json(['message'=>$e->getMessage()],422); } }
        if($payload['status']==='APPROVED'&&(!config('listing.screening_provider_connected')||!config('listing.registry_provider_connected'))&&empty($payload['manual_verification_evidence']))return response()->json(['message'=>'Manual verification evidence is required while providers are not connected.'],422);
        $admin=$this->admin($request, str_contains($payload['status'], 'APPROVED') ? 'kyb.approve' : (str_contains($payload['status'], 'REJECTED') ? 'kyb.reject' : 'kyb.review')); $from=$organization->kyb_status; $review=ListingOrganizationKybReview::query()->create(['review_uuid'=>(string)Str::uuid(),'organization_id'=>$organization->id,'reviewer_admin_id'=>$admin->id,'from_status'=>$from,'to_status'=>$payload['status'],'public_message'=>$payload['public_message']??null,'internal_notes'=>$payload['internal_notes']??null,'manual_verification_evidence'=>$payload['manual_verification_evidence']??null,'decided_at'=>now()]); $organization->forceFill(['kyb_status'=>$payload['status'],'kyb_current_stage'=>$payload['status'],'kyb_reviewed_at'=>now()])->save();
        ListingAuditLog::query()->create(['audit_uuid'=>(string)Str::uuid(),'actor_type'=>'admin','actor_id'=>$admin->id,'action'=>'listing.kyb.decision','resource_type'=>'listing_organization','resource_id'=>$organization->id,'before'=>['kyb_status'=>$from],'after'=>['kyb_status'=>$payload['status'],'review_id'=>$review->id],'reason'=>$payload['internal_notes']??$payload['public_message']??null,'ip_address'=>$request->ip(),'user_agent'=>$request->userAgent()]);
        return response()->json(['data'=>$organization->fresh(['people','kybDocuments','kybReviews'])]);
    }

    public function assignReview(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate([
            'review_domain' => ['required', 'string'],
            'reviewer_admin_id' => ['required', 'integer', 'exists:admins,id'],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);

        return $this->handle(fn () => $this->listings->assignReview($this->admin($request, 'listing.review.assign'), $this->application($reference), $payload, $request), 201);
    }

    public function review(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate([
            'review_type' => ['required', 'string'],
            'status' => ['required', 'string', 'in:NOT_STARTED,IN_REVIEW,PASSED,PASSED_WITH_CONDITIONS,FAILED'],
            'expected_revision' => ['nullable', 'integer', 'min:1'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'scorecard' => ['nullable', 'array'],
            'risk_flags' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->handle(fn () => $this->listings->completeReview($this->admin($request, $this->reviewPermission((string) $payload['review_type'])), $this->application($reference), $payload, $request), 201);
    }

    public function recommend(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->handle(fn () => $this->listings->recommendApproval($this->admin($request, 'listing.approve'), $this->application($reference), $payload['reason'], $request));
    }

    public function requestInformation(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate([
            'subject' => ['required', 'string', 'max:140'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        return $this->handle(fn () => $this->listings->requestInformation($this->admin($request, 'listing.review'), $this->application($reference), $payload, $request), 201);
    }

    public function reject(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);

        return $this->handle(fn () => $this->listings->rejectApplication($this->admin($request, 'listing.reject'), $this->application($reference), $payload['reason'], $request));
    }

    public function approve(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->handle(fn () => $this->listings->approveApplication($this->admin($request, 'listing.approve'), $this->application($reference), $payload['reason'], $request));
    }

    public function createAssetConfiguration(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'symbol' => ['required', 'string', 'max:24'],
            'slug' => ['nullable', 'string', 'max:120'],
            'asset_type' => ['nullable', 'string', 'max:40'],
            'network' => ['required', 'string', 'max:64'],
            'token_standard' => ['required', 'string', 'max:40'],
            'contract_address' => ['nullable', 'string', 'max:160'],
            'decimals' => ['required', 'integer', 'min:0', 'max:36'],
            'explorer_url' => ['nullable', 'url', 'max:255'],
            'supply_metadata' => ['nullable', 'array'],
            'upgradeable' => ['nullable', 'boolean'],
            'proxy' => ['nullable', 'boolean'],
            'pausable' => ['nullable', 'boolean'],
            'blacklist_capability' => ['nullable', 'boolean'],
            'transfer_restriction' => ['nullable', 'boolean'],
            'fee_on_transfer' => ['nullable', 'boolean'],
            'mintable' => ['nullable', 'boolean'],
            'freeze_authority' => ['nullable', 'boolean'],
            'owner_privileges' => ['nullable', 'boolean'],
            'unusual_behavior' => ['nullable', 'boolean'],
        ]);

        return $this->handle(fn () => $this->listings->createAssetConfiguration($this->admin($request, 'listing.technical.review'), $this->application($reference), $payload, $request), 201);
    }

    public function createMarket(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate([
            'quote_asset' => ['required', 'string', 'max:24'],
            'base_asset' => ['nullable', 'string', 'max:24'],
            'tick_size' => ['nullable', 'numeric', 'gt:0'],
            'quantity_step' => ['nullable', 'numeric', 'gt:0'],
            'min_quantity' => ['nullable', 'numeric', 'gte:0'],
            'max_quantity' => ['nullable', 'numeric', 'gte:0'],
            'min_notional' => ['nullable', 'numeric', 'gte:0'],
            'maker_fee' => ['nullable', 'numeric', 'gte:0'],
            'taker_fee' => ['nullable', 'numeric', 'gte:0'],
            'manual_price' => ['prohibited'],
            'reference_launch_price' => ['nullable', 'numeric', 'gt:0'],
            'liquidity_arrangement' => ['nullable', 'string', 'max:64'],
            'required_base_liquidity' => ['nullable', 'numeric', 'gte:0'],
            'required_quote_liquidity' => ['nullable', 'numeric', 'gte:0'],
            'maximum_spread_bps' => ['nullable', 'numeric', 'gte:0'],
            'minimum_depth' => ['nullable', 'numeric', 'gte:0'],
            'liquidity_status' => ['nullable', 'string', 'max:64'],
        ]);

        return $this->handle(fn () => $this->listings->createMarketConfiguration($this->admin($request, 'market.manage'), $this->application($reference), $payload, $request), 201);
    }

    public function createNetworkConfiguration(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate([
            'network' => ['required', 'string', 'max:64'],
            'token_standard' => ['required', 'string', 'max:40'],
            'contract_address' => ['nullable', 'string', 'max:180'],
            'decimals' => ['nullable', 'integer', 'min:0', 'max:36'],
            'minimum_deposit' => ['nullable', 'numeric', 'gte:0'],
            'minimum_withdrawal' => ['nullable', 'numeric', 'gte:0'],
            'maximum_withdrawal' => ['nullable', 'numeric', 'gte:0'],
            'withdrawal_fee' => ['nullable', 'numeric', 'gte:0'],
            'explorer_url' => ['nullable', 'url', 'max:255'],
            'upgradeable' => ['nullable', 'boolean'],
            'proxy' => ['nullable', 'boolean'],
            'pausable' => ['nullable', 'boolean'],
            'blacklist_capability' => ['nullable', 'boolean'],
            'transfer_restriction' => ['nullable', 'boolean'],
            'fee_on_transfer' => ['nullable', 'boolean'],
            'mintable' => ['nullable', 'boolean'],
            'freeze_authority' => ['nullable', 'boolean'],
            'owner_privileges' => ['nullable', 'boolean'],
            'unusual_behavior' => ['nullable', 'boolean'],
        ]);

        return $this->handle(fn () => $this->listings->addNetworkConfiguration($this->admin($request, 'listing.technical.review'), $this->application($reference), $payload, $request), 201);
    }

    public function runTests(Request $request, string $reference): JsonResponse
    {
        return $this->handle(fn () => $this->listings->runListingTests($this->admin($request, 'listing.technical.review'), $this->application($reference), $request), 202);
    }

    public function requestFinalApproval(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->handle(fn () => $this->listings->requestFinalApproval($this->admin($request, 'listing.approve'), $this->application($reference), $payload['reason'], $request));
    }

    public function schedule(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate([
            'announcement_at' => ['nullable', 'date'],
            'deposit_open_at' => ['nullable', 'date'],
            'trading_open_at' => ['required', 'date'],
            'withdrawal_open_at' => ['nullable', 'date'],
            'announcement_metadata' => ['nullable', 'array'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->handle(fn () => $this->listings->schedule($this->admin($request, 'listing.launch.schedule'), $this->application($reference), $payload, $request), 201);
    }

    public function launch(Request $request, string $reference): JsonResponse
    {
        return $this->handle(function () use ($request, $reference): array {
            $this->admin($request, 'listing.launch.recover');
            $application = $this->application($reference);
            if ($application->integration_status !== 'SCHEDULED') {
                throw new RuntimeException('Only scheduled listings can be dispatched.');
            }
            $event = $application->launchEvents()->where('event_type', 'TRADING_OPEN')->firstOrFail();
            $dispatch = app(ListingLaunchDispatcherService::class)->dispatchDue();
            return ['operation' => $event->fresh(), 'dispatch' => $dispatch];
        }, 202);
    }

    public function processDueLaunchEvents(Request $request): JsonResponse
    {
        return $this->handle(fn () => $this->listings->processDueLaunchEvents($this->admin($request, 'listing.launch.recover'), $request), 202);
    }

    public function launchOperations(Request $request): JsonResponse
    {
        $query = ListingLaunchEvent::query()->with('application')->latest();
        if ($request->filled('status')) {
            $query->where('operation_status', strtoupper((string) $request->query('status')));
        }

        return response()->json(['data' => [
            'metrics' => [
                'scheduled' => ListingLaunchEvent::query()->where('operation_status', 'SCHEDULED')->count(),
                'overdue' => ListingLaunchEvent::query()->whereIn('operation_status', ['SCHEDULED', 'RETRY_PENDING'])->where('due_at', '<', now())->count(),
                'executing' => ListingLaunchEvent::query()->where('operation_status', 'EXECUTING')->count(),
                'stale_executing' => ListingLaunchEvent::query()->where('operation_status', 'EXECUTING')->where('lease_expires_at', '<=', now())->count(),
                'succeeded' => ListingLaunchEvent::query()->where('operation_status', 'SUCCEEDED')->count(),
                'blocked' => ListingLaunchEvent::query()->where('operation_status', 'BLOCKED')->count(),
                'failed' => ListingLaunchEvent::query()->where('operation_status', 'FAILED')->count(),
                'retry_pending' => ListingLaunchEvent::query()->where('operation_status', 'RETRY_PENDING')->count(),
            ],
            'operations' => $query->paginate((int) $request->query('per_page', 50)),
        ]]);
    }

    public function cancelLaunchOperation(Request $request, string $reference, string $eventUuid): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $application = $this->application($reference);
        $event = ListingLaunchEvent::query()->where('application_id', $application->id)->where('event_uuid', $eventUuid)->firstOrFail();

        return $this->handle(fn () => $this->listings->cancelLaunchEvent($this->admin($request, 'listing.launch.cancel'), $event, $data['reason'], $request));
    }

    public function depositReadiness(Request $request, string $reference): JsonResponse
    {
        return $this->handle(fn () => $this->listings->depositReadiness($this->application($reference), $this->admin($request)));
    }

    public function requestDepositActivation(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:2000']]);
        return $this->handle(fn () => $this->listings->requestDepositActivation($this->admin($request, 'listing.deposit.approve'), $this->application($reference), $payload['reason'], $request), 202);
    }

    public function approveDepositActivation(Request $request, string $reference): JsonResponse
    {
        return $this->handle(fn () => $this->listings->approveDepositActivation($this->admin($request, 'listing.deposit.approve'), $this->application($reference), $request));
    }

    public function withdrawalReadiness(Request $request, string $reference): JsonResponse
    {
        return $this->handle(fn () => $this->listings->withdrawalReadiness($this->application($reference), $this->admin($request)));
    }

    public function requestWithdrawalActivation(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:2000']]);
        return $this->handle(fn () => $this->listings->requestWithdrawalActivation($this->admin($request, 'listing.withdrawal.approve'), $this->application($reference), $payload['reason'], $request), 202);
    }

    public function approveWithdrawalActivation(Request $request, string $reference): JsonResponse
    {
        return $this->handle(fn () => $this->listings->approveWithdrawalActivation($this->admin($request, 'listing.withdrawal.approve'), $this->application($reference), $request));
    }

    public function tokenMigration(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate([
            'migration_type' => ['required', 'string', 'in:TOKEN_REBRAND,CONTRACT_MIGRATION,NETWORK_MIGRATION,TOKEN_SWAP'],
            'old_network' => ['nullable', 'string', 'max:64'],
            'old_contract_address' => ['nullable', 'string', 'max:180'],
            'new_network' => ['nullable', 'string', 'max:64'],
            'new_contract_address' => ['nullable', 'string', 'max:180'],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
            'plan' => ['nullable', 'array'],
        ]);

        return $this->handle(fn () => $this->listings->createTokenMigration($this->admin($request, 'listing.technical.review'), $this->application($reference), $payload, $request), 202);
    }

    public function emergencyControl(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate([
            'control' => ['required', 'string', 'in:PAUSE_DEPOSITS,RESUME_DEPOSITS,PAUSE_WITHDRAWALS,RESUME_WITHDRAWALS,PAUSE_TRADING,HALT_ALL'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $permission = str_starts_with((string) $payload['control'], 'RESUME') ? 'market.resume' : 'listing.emergency.pause';
        return $this->handle(fn () => $this->listings->emergencyControl($this->admin($request, $permission), $this->application($reference), $payload, $request));
    }

    public function liveAssets(): JsonResponse
    {
        return response()->json(['data' => ListingApplication::query()
            ->where('integration_status', 'LIVE')
            ->with(['assetConfiguration', 'marketConfigurations'])
            ->latest()
            ->get()]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        return response()->json(['data' => ListingAuditLog::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function testRuns(Request $request): JsonResponse
    {
        return response()->json(['data' => ListingTestRun::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function schedules(Request $request): JsonResponse
    {
        return response()->json(['data' => ListingLaunchSchedule::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function contractValidations(Request $request): JsonResponse
    {
        return response()->json(['data' => ListingContractValidation::query()->latest('checked_at')->paginate((int) $request->query('per_page', 50))]);
    }

    public function tokenMigrations(Request $request): JsonResponse
    {
        return response()->json(['data' => ListingTokenMigration::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    private function application(string $reference): ListingApplication
    {
        return ListingApplication::query()->where('reference', $reference)->with(['organization', 'assetConfiguration', 'networkConfigurations', 'contractValidations', 'marketConfigurations', 'schedule'])->firstOrFail();
    }

    private function admin(Request $request, string $permission = 'listing.view'): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            throw new RuntimeException('Admin authentication is required.');
        }
        if ($admin->status !== 'active') {
            throw new RuntimeException('Admin account is disabled.');
        }
        if (! $admin->hasPermission($permission)) {
            throw new RuntimeException('Permission denied: '.$permission);
        }

        return $admin;
    }

    private function reviewPermission(string $reviewType): string
    {
        return match (strtoupper($reviewType)) {
            'COMPLIANCE' => 'listing.compliance.review',
            'SECURITY' => 'listing.security.review',
            'TECHNICAL' => 'listing.technical.review',
            'LIQUIDITY' => 'listing.liquidity.review',
            default => 'listing.review',
        };
    }

    private function handle(\Closure $callback, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()], $status);
        } catch (RuntimeException $exception) {
            if (str_starts_with($exception->getMessage(), 'Permission denied:') || $exception->getMessage() === 'Admin account is disabled.') {
                return response()->json(['message' => $exception->getMessage()], 403);
            }
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
