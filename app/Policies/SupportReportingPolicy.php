<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SupportReporting;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupportReportingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_support_reporting');
    }

    public function view(AuthUser $authUser, SupportReporting $supportReporting): bool
    {
        return $authUser->can('view_support_reporting');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_support_reporting');
    }

    public function update(AuthUser $authUser, SupportReporting $supportReporting): bool
    {
        return $authUser->can('update_support_reporting');
    }

    public function delete(AuthUser $authUser, SupportReporting $supportReporting): bool
    {
        return $authUser->can('delete_support_reporting');
    }

}