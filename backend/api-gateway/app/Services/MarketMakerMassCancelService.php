<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\MarketMakerProfile;
use App\Models\MarketMakerQuote;
use App\Services\InstitutionalService;

class MarketMakerMassCancelService
{
    public function __construct(private readonly InstitutionalService $institutions)
    {
    }

    public function cancelQuotes(Admin $admin, MarketMakerProfile $profile, ?string $marketSymbol, string $reason): array
    {
        $query = MarketMakerQuote::query()
            ->where('status', 'ACTIVE')
            ->where('metadata->phase15c_profile_id', $profile->id);

        if ($marketSymbol !== null) {
            $query->where('market_symbol', strtoupper($marketSymbol));
        }

        $count = (clone $query)->count();
        $query->update(['status' => 'CANCELLED', 'updated_at' => now()]);
        $this->institutions->audit($profile->institution_id, $profile->subaccount_id, 'admin', $admin->id, 'market_maker.mass_cancel', 'market_maker_profile', $profile->id, null, ['cancelled_quotes' => $count, 'market_symbol' => $marketSymbol], $reason);

        return [
            'cancelled_quotes' => $count,
            'market_symbol' => $marketSymbol ? strtoupper($marketSymbol) : 'ALL',
            'oms_mass_cancel_required_for_live_orders' => true,
        ];
    }
}
