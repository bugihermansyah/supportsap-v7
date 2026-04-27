<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Auth\Access\HandlesAuthorization;

class ActivityPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_activity');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_activity');
    }

    public function update(AuthUser $authUser, Activity $activity): bool
    {
        return $authUser->can('update_activity');
    }

    public function delete(AuthUser $authUser, Activity $activity): bool
    {
        return $authUser->can('delete_activity');
    }

}