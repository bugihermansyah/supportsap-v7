<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ReportingEmail;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReportingEmailPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_reporting_email');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_reporting_email');
    }

    public function update(AuthUser $authUser, ReportingEmail $reportingEmail): bool
    {
        return $authUser->can('update_reporting_email');
    }

    public function delete(AuthUser $authUser, ReportingEmail $reportingEmail): bool
    {
        return $authUser->can('delete_reporting_email');
    }

}