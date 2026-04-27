<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Outstanding;
use Illuminate\Auth\Access\HandlesAuthorization;

class OutstandingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_outstanding');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_outstanding');
    }

    public function update(AuthUser $authUser, Outstanding $outstanding): bool
    {
        return $authUser->can('update_outstanding');
    }

    public function delete(AuthUser $authUser, Outstanding $outstanding): bool
    {
        return $authUser->can('delete_outstanding');
    }

}