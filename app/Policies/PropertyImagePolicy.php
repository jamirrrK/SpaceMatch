<?php

namespace App\Policies;

use App\Models\PropertyImage;
use App\Models\User;

class PropertyImagePolicy
{
    public function update(User $user, PropertyImage $image): bool
    {
        return $user->hasRole('owner') && (int) $image->property->owner_id === (int) $user->id;
    }

    public function delete(User $user, PropertyImage $image): bool
    {
        return $this->update($user, $image);
    }
}
