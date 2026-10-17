<?php

namespace App\Policies;

use App\Models\Space;
use App\Models\User;

class SpacePolicy
{
    public function update(User $user, Space $space): bool
    {
        return $user->hasRole('owner') && (int) $space->property->owner_id === (int) $user->id;
    }
}
