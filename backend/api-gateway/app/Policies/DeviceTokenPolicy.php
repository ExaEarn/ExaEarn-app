<?php

namespace App\Policies;

use App\Models\DeviceToken;
use App\Models\User;

class DeviceTokenPolicy
{
    /**
     * Determine whether the user can delete the device token.
     */
    public function delete(User $user, DeviceToken $deviceToken): bool
    {
        return $user->id === $deviceToken->user_id;
    }
}
