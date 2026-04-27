<?php

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_user');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_user');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('update_user');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('delete_user');
    }

}