<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PreventiveOutstanding;
use Illuminate\Auth\Access\HandlesAuthorization;

class PreventiveOutstandingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_preventive_outstanding');
    }

    public function view(AuthUser $authUser, PreventiveOutstanding $preventiveOutstanding): bool
    {
        return $authUser->can('view_preventive_outstanding');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_preventive_outstanding');
    }

    public function update(AuthUser $authUser, PreventiveOutstanding $preventiveOutstanding): bool
    {
        return $authUser->can('update_preventive_outstanding');
    }

    public function delete(AuthUser $authUser, PreventiveOutstanding $preventiveOutstanding): bool
    {
        return $authUser->can('delete_preventive_outstanding');
    }

}