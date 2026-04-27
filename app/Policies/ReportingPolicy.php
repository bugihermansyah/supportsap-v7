<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Reporting;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReportingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_reporting');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_reporting');
    }

    public function update(AuthUser $authUser, Reporting $reporting): bool
    {
        return $authUser->can('update_reporting');
    }

    public function delete(AuthUser $authUser, Reporting $reporting): bool
    {
        return $authUser->can('delete_reporting');
    }

}