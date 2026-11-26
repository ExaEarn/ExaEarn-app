<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\ListingApplication;
use App\Models\ListingLaunchEvent;
use App\Models\ListingMarketConfiguration;
use App\Models\Market;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Sole production authority for transitions into a tradeable Market state. */
class MarketActivationService
{
    public const LISTING_GOVERNED = 'LISTING_GOVERNED';
    public const UNKNOWN_REVIEW_REQUIRED = 'UNKNOWN_REVIEW_REQUIRED';

    public function createConfiguration(array $attributes, string $source = self::UNKNOWN_REVIEW_REQUIRED, ?ListingApplication $application = null): Market
    {
        if (! in_array($source, [self::LISTING_GOVERNED, self::UNKNOWN_REVIEW_REQUIRED], true)) {
            throw new AuthorizationException('Market governance source is server controlled.');
        }
        if ($source === self::LISTING_GOVERNED && ! $application) {
            throw new AuthorizationException('A canonical listing application is required.');
        }
        unset($attributes['status'], $attributes['trading_status'], $attributes['governance_source'], $attributes['listing_application_id'], $attributes['listing_launch_event_id'], $attributes['activated_at']);
        $attributes = array_merge($attributes, [
            'symbol' => strtoupper((string) $attributes['symbol']),
            'base_currency' => strtoupper((string) $attributes['base_currency']),
            'quote_currency' => strtoupper((string) $attributes['quote_currency']),
            'status' => 'pre_launch', 'trading_status' => 'PRE_LAUNCH',
            'governance_source' => $source, 'listing_application_id' => $application?->id,
        ]);

        $market = Market::query()->where('symbol', $attributes['symbol'])->first();
        if ($market && $this->isTradeable($market)) {
            throw new AuthorizationException('Generic configuration cannot modify an active market.');
        }
        if ($market) {
            $market->fill($attributes)->save();
        } else {
            $market = Market::query()->create($attributes);
        }
        $this->audit('market.configured', $market, null, 'pre_launch', ['governance_source' => $source]);
        return $market->fresh();
    }

    public function activateListing(ListingApplication $application, ListingMarketConfiguration $configuration, ?ListingLaunchEvent $event, ?Admin $actor = null): Market
    {
        if ((int) $configuration->application_id !== (int) $application->id || ! $configuration->market_id) {
            throw new AuthorizationException('Market is not bound to the governed listing.');
        }
        if ($application->application_status !== 'APPROVED' || $application->integration_status !== 'SCHEDULED' || ! $application->schedule?->approved_at) {
            throw new AuthorizationException('Governed listing launch authority is not valid.');
        }
        if ($event && ((int) $event->application_id !== (int) $application->id || $event->event_type !== 'TRADING_OPEN' || $event->operation_status !== ListingLaunchEvent::EXECUTING)) {
            throw new AuthorizationException('Durable launch operation is not authorized.');
        }
        app(ListingLifecycleService::class)->assertMarketActivationReady($application);

        return DB::transaction(function () use ($actor, $application, $configuration, $event): Market {
            $market = Market::query()->lockForUpdate()->findOrFail($configuration->market_id);
            if ($market->activated_at && $market->governance_source === self::LISTING_GOVERNED && (int) $market->listing_application_id === (int) $application->id) {
                return $market;
            }
            if (! in_array(strtolower((string) $market->status), ['pre_launch', 'configured', 'draft'], true)) {
                throw new RuntimeException('Illegal initial market activation transition.');
            }
            $previous = (string) $market->status;
            $market->forceFill(['status' => 'active', 'trading_status' => 'TRADING', 'governance_source' => self::LISTING_GOVERNED,
                'listing_application_id' => $application->id, 'listing_launch_event_id' => $event?->id, 'activated_at' => now()])->save();
            $this->audit('market.activated', $market, $previous, 'active', ['actor_admin_id' => $actor?->id, 'listing_reference' => $application->reference, 'launch_event_uuid' => $event?->event_uuid]);
            return $market->fresh();
        });
    }

    public function resume(Market $market, Admin $actor, string $reason): Market
    {
        if (! $market->activated_at || ! in_array($market->governance_source, [self::LISTING_GOVERNED, 'SYSTEM_MANAGED', 'LEGACY_APPROVED'], true)) {
            $this->audit('market.activation_denied', $market, (string) $market->trading_status, 'TRADING', ['actor_admin_id' => $actor->id, 'reason' => $reason]);
            throw new AuthorizationException('Market has no valid prior activation authority.');
        }
        $previous = (string) $market->trading_status;
        $market->forceFill(['status' => 'active', 'trading_status' => 'TRADING'])->save();
        $this->audit('market.resumed', $market, $previous, 'TRADING', ['actor_admin_id' => $actor->id, 'reason' => $reason]);
        return $market->fresh();
    }

    public function assertTradeable(Market $market): void
    {
        if (! $this->isTradeable($market)) throw new RuntimeException('Market is not active for trading.');
    }

    public function isTradeable(Market $market): bool
    {
        return strtolower((string) $market->status) === 'active' && strtoupper((string) $market->trading_status) === 'TRADING';
    }

    private function audit(string $action, Market $market, ?string $previous, string $next, array $metadata): void
    {
        AuditLog::query()->create(['user_id' => null, 'action' => $action, 'ip_address' => request()?->ip(), 'device' => request()?->userAgent(),
            'metadata' => array_merge($metadata, ['market_id' => $market->id, 'symbol' => $market->symbol, 'previous_state' => $previous, 'new_state' => $next,
                'governance_source' => $market->governance_source, 'correlation_id' => request()?->header('X-Correlation-ID') ?: (string) Str::uuid()]), 'created_at' => now()]);
    }
}
