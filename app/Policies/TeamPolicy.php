<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Team;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_team');
    }

    public function view(AuthUser $authUser, Team $team): bool
    {
        return $authUser->can('view_team');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_team');
    }

    public function update(AuthUser $authUser, Team $team): bool
    {
        return $authUser->can('update_team');
    }

    public function delete(AuthUser $authUser, Team $team): bool
    {
        return $authUser->can('delete_team');
    }

}