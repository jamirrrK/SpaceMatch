<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['owner', 'admin'], true);
    }

    public function view(User $user, Property $property): bool
    {
        return $user->isAdministrator() || $this->update($user, $property);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('owner');
    }

    public function update(User $user, Property $property): bool
    {
        return $user->hasRole('owner') && (int) $property->owner_id === (int) $user->id;
    }

    public function moderate(User $user, Property $property): bool
    {
        return $user->isAdministrator();
    }
}
