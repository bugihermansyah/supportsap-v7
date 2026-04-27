<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Location;
use Illuminate\Auth\Access\HandlesAuthorization;

class LocationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_location');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_location');
    }

    public function update(AuthUser $authUser, Location $location): bool
    {
        return $authUser->can('update_location');
    }

    public function delete(AuthUser $authUser, Location $location): bool
    {
        return $authUser->can('delete_location');
    }

}