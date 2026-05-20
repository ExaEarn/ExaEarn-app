<?php

namespace App\Policies;

use App\Models\AutoTradingStrategy;
use App\Models\User;

class AutoTradingStrategyPolicy
{
    public function view(User $user, AutoTradingStrategy $strategy): bool
    {
        return $user->id === $strategy->user_id;
    }

    public function update(User $user, AutoTradingStrategy $strategy): bool
    {
        return $user->id === $strategy->user_id;
    }

    public function delete(User $user, AutoTradingStrategy $strategy): bool
    {
        return $user->id === $strategy->user_id;
    }
}
