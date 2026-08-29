<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\ActivityLog;
use App\Models\CrowdfundingCampaign;
use App\Models\CrowdfundingComment;
use App\Models\CrowdfundingCreator;
use App\Models\CrowdfundingDocument;
use App\Models\CrowdfundingMilestone;
use App\Models\CrowdfundingOperationsSetting;
use App\Models\CrowdfundingPayout;
use App\Models\CrowdfundingPledge;
use App\Models\CrowdfundingRefundBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CrowdfundingService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ReservationService $reservations,
        private readonly CompliancePolicyService $compliance,
        private readonly NotificationService $notifications,
    ) {
    }

    public function list(array $filters = [])
    {
        return CrowdfundingCampaign::query()
            ->with('creator.user:id,name')
            ->whereIn('status', ['LIVE', 'GOAL_REACHED', 'FUNDING_ENDED', 'MILESTONE_EXECUTION', 'COMPLETED'])
            ->when($filters['classification'] ?? null, fn ($q, $v) => $q->where('classification', strtoupper((string) $v)))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', strtoupper((string) $v)))
            ->when($filters['country'] ?? null, fn ($q, $v) => $q->where('country', strtoupper((string) $v)))
            ->latest('published_at')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function creatorDashboard(User $user): array
    {
        $creator = CrowdfundingCreator::query()->firstOrCreate(['user_id' => $user->id], [
            'display_name' => $user->name ?: $user->email,
            'country' => $user->verified_country ?? $user->residence_country,
            'kyc_status' => $user->kyc_status ?? 'PENDING',
            'verification_state' => 'PENDING',
        ]);

        $campaigns = CrowdfundingCampaign::query()
            ->where('creator_id', $creator->id)
            ->withCount(['pledges as backer_count', 'comments as discussion_count'])
            ->with(['milestones', 'payouts', 'updates', 'documents' => fn ($query) => $query->latest()->limit(10)])
            ->latest()
            ->get();

        return [
            'creator' => $creator,
            'campaigns' => $campaigns,
            'summary' => [
                'campaigns' => $campaigns->count(),
                'live' => $campaigns->whereIn('status', ['LIVE', 'GOAL_REACHED'])->count(),
                'under_review' => $campaigns->whereIn('status', ['SUBMITTED', 'UNDER_REVIEW', 'NEEDS_INFORMATION'])->count(),
                'pending_payouts' => CrowdfundingPayout::query()->where('creator_id', $creator->id)->whereIn('status', ['PAYOUT_PENDING', 'PROCESSING'])->count(),
            ],
        ];
    }

    public function backerDashboard(User $user): array
    {
        $pledges = CrowdfundingPledge::query()
            ->where('backer_id', $user->id)
            ->with('campaign:id,public_id,title,status,asset,funding_goal,raised_amount')
            ->latest()
            ->paginate(20);

        return [
            'pledges' => $pledges,
            'summary' => [
                'total_pledges' => CrowdfundingPledge::query()->where('backer_id', $user->id)->count(),
                'held' => CrowdfundingPledge::query()->where('backer_id', $user->id)->where('status', 'HELD_IN_ESCROW')->count(),
                'refunded' => CrowdfundingPledge::query()->where('backer_id', $user->id)->where('status', 'REFUNDED')->count(),
            ],
        ];
    }

    public function createCampaign(User $user, array $payload): CrowdfundingCampaign
    {
        $ops = $this->operationsStatus()['settings'];
        if (($ops['new_campaign_creation_enabled']['enabled'] ?? true) !== true) {
            throw new RuntimeException('New crowdfunding campaign creation is temporarily unavailable.');
        }

        $creator = CrowdfundingCreator::query()->firstOrCreate(['user_id' => $user->id], [
            'display_name' => $payload['creator_display_name'] ?? ($user->name ?: $user->email),
            'country' => $user->verified_country ?? $user->residence_country,
            'kyc_status' => $user->kyc_status ?? 'PENDING',
            'verification_state' => 'PENDING',
        ]);

        $classification = strtoupper((string) ($payload['classification'] ?? 'PROJECT_SUPPORT'));
        $this->assertClassificationAllowed($classification, false);
        $asset = strtoupper((string) ($payload['asset'] ?? config('crowdfunding.default_asset', 'USDT')));
        $goal = FinancialDecimal::normalize((string) $payload['funding_goal']);

        return CrowdfundingCampaign::query()->create([
            'public_id' => 'CF-'.strtoupper(Str::random(10)),
            'creator_id' => $creator->id,
            'classification' => $classification,
            'title' => $payload['title'],
            'slug' => Str::slug($payload['title']).'-'.strtolower(Str::random(6)),
            'summary' => $payload['summary'] ?? null,
            'description' => $payload['description'] ?? null,
            'category' => $payload['category'] ?? 'General',
            'asset' => $asset,
            'funding_goal' => $goal,
            'minimum_goal' => FinancialDecimal::normalize((string) ($payload['minimum_goal'] ?? '0')),
            'maximum_goal' => isset($payload['maximum_goal']) ? FinancialDecimal::normalize((string) $payload['maximum_goal']) : null,
            'minimum_pledge' => FinancialDecimal::normalize((string) ($payload['minimum_pledge'] ?? '1')),
            'maximum_pledge' => isset($payload['maximum_pledge']) ? FinancialDecimal::normalize((string) $payload['maximum_pledge']) : null,
            'funding_model' => strtoupper((string) ($payload['funding_model'] ?? 'ALL_OR_NOTHING')),
            'country' => $payload['country'] ?? ($user->verified_country ?? $user->residence_country),
            'metadata' => ['documents' => $payload['documents'] ?? [], 'public_media' => $payload['public_media'] ?? []],
        ]);
    }

    public function transition(CrowdfundingCampaign $campaign, string $status, ?Admin $admin = null, array $metadata = []): CrowdfundingCampaign
    {
        return DB::transaction(function () use ($admin, $campaign, $metadata, $status): CrowdfundingCampaign {
            $campaign = CrowdfundingCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $status = strtoupper($status);
            $allowed = config("crowdfunding.allowed_transitions.{$campaign->status}", []);
            if (!in_array($status, $allowed, true)) {
                throw new RuntimeException("Invalid campaign transition {$campaign->status} -> {$status}.");
            }
            if (in_array($status, ['APPROVED', 'LIVE'], true)) {
                $this->assertCreatorReady($campaign->creator);
                $this->assertClassificationAllowed((string) $campaign->classification, true);
            }
            if ($status === 'SUBMITTED' && (($this->operationsStatus()['settings']['new_campaign_submission_enabled']['enabled'] ?? true) !== true)) {
                throw new RuntimeException('New crowdfunding campaign submissions are temporarily unavailable.');
            }
            $campaign->status = $status;
            if ($status === 'LIVE') {
                $campaign->published_at = $campaign->published_at ?: now();
            }
            if ($status === 'COMPLETED') {
                $campaign->completed_at = now();
            }
            if ($status === 'CANCELLED') {
                $campaign->cancelled_at = now();
            }
            $campaign->metadata = array_merge($campaign->metadata ?? [], ['last_transition' => ['admin_id' => $admin?->id, 'status' => $status, 'metadata' => $metadata, 'at' => now()->toISOString()]]);
            $campaign->save();
            $this->notifyCreator($campaign, 'crowdfunding.campaign.status_changed', ['status' => $status]);

            return $campaign->fresh(['creator', 'pledges', 'milestones']);
        });
    }

    public function pledge(User $backer, CrowdfundingCampaign $campaign, array $payload, ?string $idempotencyKey): CrowdfundingPledge
    {
        if (!$idempotencyKey) {
            throw new RuntimeException('Idempotency-Key is required for crowdfunding pledges.');
        }

        return DB::transaction(function () use ($backer, $campaign, $idempotencyKey, $payload): CrowdfundingPledge {
            $existing = CrowdfundingPledge::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            $campaign = CrowdfundingCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $this->assertPledgeAllowed($backer, $campaign);
            $amount = FinancialDecimal::normalize((string) $payload['amount']);
            $this->assertAmountAllowed($campaign, $amount);

            $newRaised = FinancialDecimal::add((string) $campaign->raised_amount, $amount);
            if ($campaign->maximum_goal !== null && FinancialDecimal::compare($newRaised, (string) $campaign->maximum_goal) > 0) {
                throw new RuntimeException('Campaign funding cap would be exceeded.');
            }

            $asset = strtoupper((string) $campaign->asset);
            $reference = 'crowdfunding:pledge:'.$idempotencyKey;
            $reservation = $this->reservations->reserveUserAccount($backer->id, 'funding', $asset, $amount, 'crowdfunding_pledge', 'crowdfunding_campaign', (string) $campaign->id, 'reserve:'.$reference, ['campaign_id' => $campaign->id]);
            $funding = $this->ledger->getOrCreateAccount($backer->id, 'funding', $asset);
            $escrow = $this->ledger->getOrCreateAccount(null, 'crowdfunding_escrow', $asset);
            $tx = $this->ledger->postDoubleEntry($reference, 'Crowdfunding pledge escrow settlement', [
                ['account_id' => $funding->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => $asset, 'user_id' => $backer->id],
                ['account_id' => $escrow->id, 'amount' => $amount, 'asset' => $asset, 'metadata' => ['campaign_id' => $campaign->id]],
            ], 'crowdfunding_pledge', ['source_service' => 'crowdfunding', 'campaign_id' => $campaign->id, 'reservation_id' => $reservation->reservation_id]);
            $this->reservations->consume((string) $reservation->reservation_id, $amount, ['ledger_reference' => $reference]);

            $pledge = CrowdfundingPledge::query()->create([
                'campaign_id' => $campaign->id,
                'backer_id' => $backer->id,
                'amount' => $amount,
                'asset' => $asset,
                'pricing_snapshot' => ['fee' => '0', 'engine' => 'PricingPolicyEngine-ready'],
                'reservation_id' => $reservation->reservation_id,
                'ledger_reference' => $tx->reference,
                'status' => 'HELD_IN_ESCROW',
                'idempotency_key' => $idempotencyKey,
                'anonymous_display' => (bool) ($payload['anonymous_display'] ?? false),
                'settled_at' => now(),
            ]);
            $campaign->update(['raised_amount' => $newRaised, 'status' => FinancialDecimal::compare($newRaised, (string) $campaign->funding_goal) >= 0 ? 'GOAL_REACHED' : $campaign->status]);
            ActivityLog::query()->create(['user_id' => $backer->id, 'type' => 'crowdfunding', 'action' => 'pledge.escrowed', 'status' => 'success', 'data' => ['entity_id' => $pledge->id, 'campaign_id' => $campaign->id, 'amount' => $amount, 'asset' => $asset]]);
            $this->notifications->emit($backer, 'crowdfunding.pledge.completed', ['campaign' => $campaign->title, 'amount' => $amount, 'asset' => $asset], 'crowdfunding-pledge-'.$pledge->id, ['in_app']);

            return $pledge->fresh(['campaign']);
        });
    }

    public function createMilestone(CrowdfundingCampaign $campaign, array $payload): CrowdfundingMilestone
    {
        return CrowdfundingMilestone::query()->create([
            'campaign_id' => $campaign->id,
            'sequence' => (int) $payload['sequence'],
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'target_amount' => FinancialDecimal::normalize((string) ($payload['target_amount'] ?? '0')),
            'release_percentage' => (string) ($payload['release_percentage'] ?? '0'),
            'due_at' => $payload['due_at'] ?? null,
        ]);
    }

    public function submitMilestone(CrowdfundingMilestone $milestone, User $creatorUser, array $payload): CrowdfundingMilestone
    {
        if ((int) $milestone->campaign->creator->user_id !== (int) $creatorUser->id) {
            abort(404);
        }
        $milestone->update(['status' => 'SUBMITTED', 'evidence' => $payload['evidence'] ?? [], 'submitted_at' => now()]);
        return $milestone->fresh();
    }

    public function reviewMilestone(CrowdfundingMilestone $milestone, Admin $admin, string $action): CrowdfundingMilestone
    {
        $action = strtoupper($action);
        if (!in_array($action, ['APPROVE', 'REJECT', 'REQUEST_INFORMATION'], true)) {
            throw new RuntimeException('Unsupported milestone review action.');
        }
        $milestone->update([
            'status' => $action === 'APPROVE' ? 'APPROVED' : ($action === 'REJECT' ? 'REJECTED' : 'REQUIRES_INFORMATION'),
            'approved_at' => $action === 'APPROVE' ? now() : null,
            'rejected_at' => $action === 'REJECT' ? now() : null,
            'reviewed_by_admin_id' => $admin->id,
        ]);
        return $milestone->fresh();
    }

    public function releaseMilestone(CrowdfundingMilestone $milestone, Admin $maker, Admin $checker): CrowdfundingPayout
    {
        if ($maker->id === $checker->id) {
            throw new RuntimeException('Maker and checker must be different admins.');
        }
        return DB::transaction(function () use ($checker, $maker, $milestone): CrowdfundingPayout {
            $milestone = CrowdfundingMilestone::query()->whereKey($milestone->id)->lockForUpdate()->firstOrFail();
            if ($milestone->status !== 'APPROVED') {
                throw new RuntimeException('Milestone is not approved for release.');
            }
            if ($milestone->release_reference) {
                return CrowdfundingPayout::query()->where('payout_reference', $milestone->release_reference)->firstOrFail();
            }
            $campaign = $milestone->campaign()->lockForUpdate()->firstOrFail();
            $amount = FinancialDecimal::compare((string) $milestone->target_amount, '0') > 0 ? (string) $milestone->target_amount : FinancialDecimal::mul((string) $campaign->raised_amount, FinancialDecimal::div((string) $milestone->release_percentage, '100'));
            if (FinancialDecimal::compare($amount, '0') <= 0 || FinancialDecimal::compare($amount, (string) $campaign->raised_amount) > 0) {
                throw new RuntimeException('Invalid milestone release amount.');
            }
            $asset = strtoupper((string) $campaign->asset);
            $escrow = $this->ledger->getOrCreateAccount(null, 'crowdfunding_escrow', $asset);
            if (FinancialDecimal::compare((string) $escrow->balance, $amount) < 0) {
                throw new RuntimeException('Crowdfunding escrow cannot cover release.');
            }
            $payable = $this->ledger->getOrCreateAccount(null, 'crowdfunding_creator_payable', $asset);
            $creatorFunding = $this->ledger->getOrCreateAccount((int) $campaign->creator->user_id, 'funding', $asset);
            $payableReference = 'crowdfunding:payable:milestone:'.$milestone->id;
            $this->ledger->postDoubleEntry($payableReference, 'Crowdfunding creator payable recognition', [
                ['account_id' => $escrow->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => $asset],
                ['account_id' => $payable->id, 'amount' => $amount, 'asset' => $asset],
            ], 'crowdfunding_creator_payable', ['campaign_id' => $campaign->id, 'milestone_id' => $milestone->id]);
            $payoutReference = 'crowdfunding:payout:milestone:'.$milestone->id;
            $this->ledger->postDoubleEntry($payoutReference, 'Crowdfunding creator payout', [
                ['account_id' => $payable->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => $asset],
                ['account_id' => $creatorFunding->id, 'amount' => $amount, 'asset' => $asset, 'user_id' => $campaign->creator->user_id],
            ], 'crowdfunding_creator_payout', ['campaign_id' => $campaign->id, 'milestone_id' => $milestone->id]);
            $milestone->update(['status' => 'RELEASED', 'released_at' => now(), 'release_reference' => $payoutReference]);

            $payout = CrowdfundingPayout::query()->create([
                'payout_uuid' => (string) Str::uuid(),
                'campaign_id' => $campaign->id,
                'milestone_id' => $milestone->id,
                'creator_id' => $campaign->creator_id,
                'amount' => $amount,
                'asset' => $asset,
                'status' => 'COMPLETED',
                'payable_reference' => $payableReference,
                'payout_reference' => $payoutReference,
                'maker_admin_id' => $maker->id,
                'checker_admin_id' => $checker->id,
                'completed_at' => now(),
            ]);
            ActivityLog::query()->create(['user_id' => $campaign->creator->user_id, 'type' => 'crowdfunding', 'action' => 'payout.completed', 'status' => 'success', 'data' => ['entity_id' => $payout->id, 'campaign_id' => $campaign->id, 'amount' => $amount, 'asset' => $asset]]);

            return $payout;
        });
    }

    public function refundCampaign(CrowdfundingCampaign $campaign, string $reason): CrowdfundingRefundBatch
    {
        return DB::transaction(function () use ($campaign, $reason): CrowdfundingRefundBatch {
            $campaign = CrowdfundingCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $existing = CrowdfundingRefundBatch::query()
                ->where('campaign_id', $campaign->id)
                ->where('reason', $reason)
                ->whereIn('status', ['PROCESSING', 'COMPLETED'])
                ->latest()
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }
            if ($campaign->status === 'REFUNDED') {
                return CrowdfundingRefundBatch::query()->where('campaign_id', $campaign->id)->latest()->firstOrFail();
            }
            if (!in_array($campaign->status, ['FAILED', 'CANCELLED', 'REFUNDING', 'SUSPENDED'], true)) {
                throw new RuntimeException('Campaign is not eligible for refunds.');
            }
            $batch = CrowdfundingRefundBatch::query()->create(['batch_uuid' => (string) Str::uuid(), 'campaign_id' => $campaign->id, 'reason' => $reason, 'status' => 'PROCESSING']);
            $pledges = CrowdfundingPledge::query()->where('campaign_id', $campaign->id)->where('status', 'HELD_IN_ESCROW')->lockForUpdate()->get();
            foreach ($pledges as $pledge) {
                $reference = 'crowdfunding:refund:pledge:'.$pledge->id;
                $escrow = $this->ledger->getOrCreateAccount(null, 'crowdfunding_escrow', (string) $pledge->asset);
                $backer = $this->ledger->getOrCreateAccount((int) $pledge->backer_id, 'funding', (string) $pledge->asset);
                $this->ledger->postDoubleEntry($reference, 'Crowdfunding pledge refund', [
                    ['account_id' => $escrow->id, 'amount' => FinancialDecimal::sub('0', (string) $pledge->amount), 'asset' => $pledge->asset],
                    ['account_id' => $backer->id, 'amount' => (string) $pledge->amount, 'asset' => $pledge->asset, 'user_id' => $pledge->backer_id],
                ], 'crowdfunding_refund', ['campaign_id' => $campaign->id, 'pledge_id' => $pledge->id, 'reason' => $reason]);
                $pledge->update(['status' => 'REFUNDED', 'refund_reference' => $reference, 'refunded_at' => now()]);
                ActivityLog::query()->create(['user_id' => $pledge->backer_id, 'type' => 'crowdfunding', 'action' => 'pledge.refunded', 'status' => 'success', 'data' => ['entity_id' => $pledge->id, 'campaign_id' => $campaign->id, 'amount' => (string) $pledge->amount, 'asset' => $pledge->asset]]);
                $batch->increment('processed_items');
            }
            $batch->update(['total_items' => $pledges->count(), 'status' => 'COMPLETED']);
            $campaign->update(['status' => 'REFUNDED']);
            return $batch->fresh();
        });
    }

    public function publishUpdate(CrowdfundingCampaign $campaign, User $creatorUser, array $payload)
    {
        if ((int) $campaign->creator->user_id !== (int) $creatorUser->id) {
            abort(404);
        }
        $update = $campaign->updates()->create(['creator_id' => $campaign->creator_id, 'title' => $payload['title'], 'body' => $payload['body'], 'published_at' => now()]);
        $campaign->pledges()->with('backer')->get()->unique('backer_id')->each(function (CrowdfundingPledge $pledge) use ($campaign): void {
            if ($pledge->backer) {
                $this->notifications->emit($pledge->backer, 'crowdfunding.campaign.update_published', ['campaign' => $campaign->title], 'crowdfunding-update-'.$campaign->id.'-'.$pledge->backer_id.'-'.now()->timestamp, ['in_app']);
            }
        });
        return $update;
    }

    public function comments(CrowdfundingCampaign $campaign, int $perPage = 20)
    {
        $this->assertCampaignVisible($campaign);

        return CrowdfundingComment::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'ACTIVE')
            ->whereNull('parent_id')
            ->with(['user:id,name', 'replies' => fn ($query) => $query->where('status', 'ACTIVE')->with('user:id,name')->latest()])
            ->latest()
            ->paginate($perPage);
    }

    public function createComment(User $user, CrowdfundingCampaign $campaign, array $payload): CrowdfundingComment
    {
        $this->assertCampaignVisible($campaign);
        $this->compliance->assertAllowed($user, 'CROWDFUNDING', ['action' => 'COMMENT', 'classification' => $campaign->classification]);

        $parentId = $payload['parent_id'] ?? null;
        if ($parentId && !CrowdfundingComment::query()->where('campaign_id', $campaign->id)->whereKey($parentId)->exists()) {
            throw new RuntimeException('Comment parent does not belong to this campaign.');
        }

        $comment = CrowdfundingComment::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'type' => strtoupper((string) ($payload['type'] ?? 'COMMENT')),
            'body' => trim((string) $payload['body']),
            'is_creator_reply' => (int) $campaign->creator->user_id === (int) $user->id,
        ]);

        $event = $comment->type === 'QUESTION'
            ? 'crowdfunding.question.received'
            : ($comment->is_creator_reply ? 'crowdfunding.creator.replied' : 'crowdfunding.comment.created');
        $recipient = $comment->is_creator_reply ? null : $campaign->creator->user;
        if ($recipient) {
            $this->notifications->emit($recipient, $event, ['campaign' => $campaign->title], 'crowdfunding-comment-'.$comment->id, ['in_app']);
        }
        ActivityLog::query()->create(['user_id' => $user->id, 'type' => 'crowdfunding', 'action' => 'comment.created', 'status' => 'success', 'data' => ['entity_id' => $comment->id, 'campaign_id' => $campaign->id]]);

        return $comment->fresh(['user:id,name', 'replies']);
    }

    public function reportComment(User $user, CrowdfundingComment $comment, string $reason): CrowdfundingComment
    {
        if (!in_array(strtoupper($reason), ['SPAM', 'FRAUD', 'HARASSMENT', 'MISLEADING_INFORMATION', 'UNSAFE_CONTENT', 'OTHER'], true)) {
            throw new RuntimeException('Unsupported report reason.');
        }
        $comment->update(['status' => 'UNDER_REVIEW', 'reported_at' => now(), 'report_metadata' => ['reason' => strtoupper($reason), 'reported_by' => $user->id]]);
        ActivityLog::query()->create(['user_id' => $user->id, 'type' => 'crowdfunding', 'action' => 'comment.reported', 'status' => 'success', 'data' => ['entity_id' => $comment->id, 'reason' => strtoupper($reason)]]);
        return $comment->fresh();
    }

    public function moderateComment(Admin $admin, CrowdfundingComment $comment, string $status, string $reason): CrowdfundingComment
    {
        $status = strtoupper($status);
        if (!in_array($status, ['ACTIVE', 'HIDDEN', 'REMOVED', 'UNDER_REVIEW'], true)) {
            throw new RuntimeException('Unsupported comment moderation status.');
        }
        $comment->update(['status' => $status, 'moderated_at' => now(), 'moderated_by_admin_id' => $admin->id, 'moderation_reason' => $reason]);
        ActivityLog::query()->create(['admin_id' => $admin->id, 'type' => 'crowdfunding', 'action' => 'comment.moderated', 'status' => 'success', 'data' => ['entity_id' => $comment->id, 'moderation_status' => $status]]);
        $this->notifications->emit($comment->user, 'crowdfunding.comment.moderated', ['campaign' => $comment->campaign->title, 'status' => $status], 'crowdfunding-comment-moderated-'.$comment->id.'-'.$status, ['in_app']);
        return $comment->fresh();
    }

    public function uploadDocument(User $user, CrowdfundingCampaign $campaign, UploadedFile $file, string $type, string $visibility): CrowdfundingDocument
    {
        if ((int) $campaign->creator->user_id !== (int) $user->id) {
            abort(404);
        }
        $type = strtoupper($type);
        $visibility = strtoupper($visibility);
        $allowedTypes = ['PUBLIC_CAMPAIGN_MEDIA', 'PRIVATE_CREATOR_DOCUMENT', 'PRIVATE_COMPLIANCE_DOCUMENT', 'MILESTONE_EVIDENCE', 'FINANCIAL_DOCUMENT', 'LEGAL_DISCLOSURE', 'OTHER'];
        if (!in_array($type, $allowedTypes, true) || !in_array($visibility, ['PUBLIC', 'PRIVATE'], true)) {
            throw new RuntimeException('Unsupported crowdfunding document type or visibility.');
        }
        if ($visibility === 'PUBLIC' && !in_array($type, ['PUBLIC_CAMPAIGN_MEDIA', 'LEGAL_DISCLOSURE', 'OTHER'], true)) {
            throw new RuntimeException('Private document type cannot be uploaded as public media.');
        }
        $mime = (string) $file->getMimeType();
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'mp4', 'mov'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'video/mp4', 'video/quicktime'];
        if (!in_array($extension, $allowedExtensions, true) || !in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('Unsupported crowdfunding document file type.');
        }
        if ($file->getSize() > 20 * 1024 * 1024) {
            throw new RuntimeException('Crowdfunding document is too large.');
        }

        $disk = config('crowdfunding.storage.disk', 'local');
        $directory = $visibility === 'PUBLIC' ? 'crowdfunding/public/'.$campaign->id : 'crowdfunding/private/'.$campaign->id;
        $path = $file->store($directory, $disk);

        $document = CrowdfundingDocument::query()->create([
            'campaign_id' => $campaign->id,
            'owner_id' => $user->id,
            'document_type' => $type,
            'visibility' => $visibility,
            'storage_disk' => $disk,
            'storage_reference' => $path,
            'safe_filename' => Str::limit(preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName()), 180, ''),
            'mime_type' => $mime,
            'size_bytes' => $file->getSize(),
            'status' => $visibility === 'PUBLIC' ? 'APPROVED' : 'PENDING_REVIEW',
            'uploaded_at' => now(),
        ]);
        ActivityLog::query()->create(['user_id' => $user->id, 'type' => 'crowdfunding', 'action' => 'document.uploaded', 'status' => 'success', 'data' => ['entity_id' => $document->id, 'campaign_id' => $campaign->id, 'visibility' => $visibility]]);

        return $document;
    }

    public function documentAccess(User|Admin $actor, CrowdfundingDocument $document): string
    {
        if ($actor instanceof User && (int) $actor->id !== (int) $document->owner_id && $document->visibility !== 'PUBLIC') {
            abort(403);
        }
        ActivityLog::query()->create([
            'user_id' => $actor instanceof User ? $actor->id : null,
            'admin_id' => $actor instanceof Admin ? $actor->id : null,
            'type' => 'crowdfunding',
            'action' => 'document.accessed',
            'status' => 'success',
            'data' => ['entity_id' => $document->id],
        ]);
        return Storage::disk((string) $document->storage_disk)->path((string) $document->storage_reference);
    }

    public function reviewDocument(Admin $admin, CrowdfundingDocument $document, string $status, string $reason): CrowdfundingDocument
    {
        $status = strtoupper($status);
        if (!in_array($status, ['APPROVED', 'REJECTED', 'REPLACEMENT_REQUIRED', 'EXPIRED'], true)) {
            throw new RuntimeException('Unsupported document review status.');
        }
        $document->update(['status' => $status, 'reviewed_at' => now(), 'reviewed_by_admin_id' => $admin->id, 'review_reason' => $reason]);
        ActivityLog::query()->create(['admin_id' => $admin->id, 'type' => 'crowdfunding', 'action' => 'document.reviewed', 'status' => 'success', 'data' => ['entity_id' => $document->id, 'review_status' => $status]]);
        $this->notifications->emit($document->owner, 'crowdfunding.document.reviewed', ['campaign' => $document->campaign->title, 'status' => $status], 'crowdfunding-document-'.$document->id.'-'.$status, ['in_app']);
        return $document->fresh();
    }

    public function operationsStatus(): array
    {
        $defaults = (array) config('crowdfunding.operations_defaults', []);
        $stored = CrowdfundingOperationsSetting::query()->pluck('value', 'key')->all();
        $settings = array_replace($defaults, $stored);

        return [
            'settings' => $settings,
            'review_backlog' => CrowdfundingCampaign::query()->whereIn('status', ['SUBMITTED', 'UNDER_REVIEW', 'NEEDS_INFORMATION'])->count(),
            'live_campaigns' => CrowdfundingCampaign::query()->whereIn('status', ['LIVE', 'GOAL_REACHED'])->count(),
            'milestones_awaiting_review' => CrowdfundingMilestone::query()->where('status', 'SUBMITTED')->count(),
            'pending_payouts' => CrowdfundingPayout::query()->whereIn('status', ['PAYOUT_PENDING', 'PROCESSING'])->count(),
            'refund_batches' => CrowdfundingRefundBatch::query()->whereIn('status', ['PROCESSING', 'FAILED'])->count(),
        ];
    }

    public function updateOperationsSetting(Admin $admin, string $key, array $value): CrowdfundingOperationsSetting
    {
        if ($key === 'investment_campaigns_enabled' && ($value['enabled'] ?? false)) {
            throw new RuntimeException('Investment crowdfunding cannot be enabled without external legal/product approval.');
        }
        $setting = CrowdfundingOperationsSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by_admin_id' => $admin->id, 'updated_at' => now()]);
        ActivityLog::query()->create(['admin_id' => $admin->id, 'type' => 'crowdfunding', 'action' => 'operations.setting_updated', 'status' => 'success', 'data' => ['key' => $key]]);
        return $setting;
    }

    public function assignReview(Admin $admin, string $entityType, int $entityId, int $assigneeAdminId, string $reason): array
    {
        $entityType = strtoupper($entityType);
        $assignment = ['assigned_by' => $admin->id, 'assigned_to' => $assigneeAdminId, 'reason' => $reason, 'assigned_at' => now()->toISOString()];

        if ($entityType === 'CAMPAIGN') {
            $campaign = CrowdfundingCampaign::query()->findOrFail($entityId);
            $campaign->update(['metadata' => array_merge($campaign->metadata ?? [], ['review_assignment' => $assignment])]);
        } elseif ($entityType === 'DOCUMENT') {
            $document = CrowdfundingDocument::query()->findOrFail($entityId);
            $document->update(['metadata' => array_merge($document->metadata ?? [], ['review_assignment' => $assignment])]);
        } elseif ($entityType === 'MILESTONE') {
            $milestone = CrowdfundingMilestone::query()->findOrFail($entityId);
            $milestone->update(['evidence' => array_merge($milestone->evidence ?? [], ['review_assignment' => $assignment])]);
        } else {
            throw new RuntimeException('Unsupported crowdfunding review assignment type.');
        }

        ActivityLog::query()->create(['admin_id' => $admin->id, 'type' => 'crowdfunding', 'action' => 'review.assigned', 'status' => 'success', 'data' => ['entity_type' => $entityType, 'entity_id' => $entityId, 'assignee_admin_id' => $assigneeAdminId]]);

        return ['entity_type' => $entityType, 'entity_id' => $entityId, 'assignment' => $assignment];
    }

    public function assertNoClosureBlockers(int $userId): array
    {
        $creatorIds = CrowdfundingCreator::query()->where('user_id', $userId)->pluck('id');
        $creator = CrowdfundingCampaign::query()->whereIn('creator_id', $creatorIds)->whereIn('status', ['LIVE', 'GOAL_REACHED', 'FUNDING_ENDED', 'MILESTONE_EXECUTION', 'REFUNDING', 'SUSPENDED'])->exists();
        $backer = CrowdfundingPledge::query()->where('backer_id', $userId)->whereIn('status', ['HELD_IN_ESCROW', 'REFUND_PENDING'])->exists();
        $blockers = [];
        if ($creator) $blockers[] = ['product' => 'crowdfunding', 'reason' => 'creator_campaign_obligations'];
        if ($backer) $blockers[] = ['product' => 'crowdfunding', 'reason' => 'active_pledge_or_refund'];
        return $blockers;
    }

    private function assertPledgeAllowed(User $user, CrowdfundingCampaign $campaign): void
    {
        $ops = $this->operationsStatus()['settings'];
        if (($ops['new_pledges_enabled']['enabled'] ?? true) !== true) {
            throw new RuntimeException('New crowdfunding pledges are temporarily unavailable.');
        }
        if (!in_array($campaign->status, ['LIVE', 'GOAL_REACHED'], true)) {
            throw new RuntimeException('Campaign is not open for pledges.');
        }
        $this->assertClassificationAllowed((string) $campaign->classification, true);
        $this->compliance->assertAllowed($user, 'CROWDFUNDING', ['action' => 'PLEDGE', 'classification' => $campaign->classification, 'jurisdiction' => $user->verified_country ?? $user->residence_country]);
    }

    private function assertCampaignVisible(CrowdfundingCampaign $campaign): void
    {
        if (!in_array($campaign->status, ['LIVE', 'GOAL_REACHED', 'FUNDING_ENDED', 'MILESTONE_EXECUTION', 'COMPLETED'], true)) {
            throw new RuntimeException('Campaign is not publicly visible.');
        }
    }

    private function assertAmountAllowed(CrowdfundingCampaign $campaign, string $amount): void
    {
        if (FinancialDecimal::compare($amount, (string) $campaign->minimum_pledge) < 0) throw new RuntimeException('Pledge is below campaign minimum.');
        if ($campaign->maximum_pledge !== null && FinancialDecimal::compare($amount, (string) $campaign->maximum_pledge) > 0) throw new RuntimeException('Pledge exceeds campaign maximum.');
    }

    private function assertCreatorReady(CrowdfundingCreator $creator): void
    {
        if ($creator->status !== 'ACTIVE' || !in_array($creator->verification_state, ['VERIFIED', 'APPROVED'], true)) {
            throw new RuntimeException('Campaign creator is not verified.');
        }
    }

    private function assertClassificationAllowed(string $classification, bool $public): void
    {
        $investment = in_array($classification, config('crowdfunding.classifications.investment', []), true);
        if ($investment && (!config('crowdfunding.investment_campaigns_enabled') || $public)) {
            throw new RuntimeException('Investment crowdfunding campaigns require external legal/product enablement.');
        }
    }

    private function notifyCreator(CrowdfundingCampaign $campaign, string $event, array $payload): void
    {
        $this->notifications->emit($campaign->creator->user, $event, $payload + ['campaign' => $campaign->title], 'crowdfunding-'.$event.'-'.$campaign->id.'-'.now()->timestamp, ['in_app']);
    }
}
