<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Unit;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnitPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_unit');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_unit');
    }

    public function update(AuthUser $authUser, Unit $unit): bool
    {
        return $authUser->can('update_unit');
    }

    public function delete(AuthUser $authUser, Unit $unit): bool
    {
        return $authUser->can('delete_unit');
    }

}