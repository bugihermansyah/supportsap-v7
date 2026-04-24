<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Company;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_company');
    }

    public function view(AuthUser $authUser, Company $company): bool
    {
        return $authUser->can('view_company');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_company');
    }

    public function update(AuthUser $authUser, Company $company): bool
    {
        return $authUser->can('update_company');
    }

    public function delete(AuthUser $authUser, Company $company): bool
    {
        return $authUser->can('delete_company');
    }

}