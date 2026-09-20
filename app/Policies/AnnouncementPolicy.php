<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Announcement;
use Illuminate\Auth\Access\HandlesAuthorization;

class AnnouncementPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_announcement');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_announcement');
    }

    public function update(AuthUser $authUser, Announcement $announcement): bool
    {
        return $authUser->can('update_announcement');
    }

    public function delete(AuthUser $authUser, Announcement $announcement): bool
    {
        return $authUser->can('delete_announcement');
    }

}
