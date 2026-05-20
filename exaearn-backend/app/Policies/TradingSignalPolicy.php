<?php

namespace App\Policies;

use App\Models\TradingSignal;
use App\Models\User;

class TradingSignalPolicy
{
    public function view(User $user, TradingSignal $signal): bool
    {
        return $user->id === $signal->user_id;
    }
}
