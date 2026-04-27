<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Customer;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_customer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_customer');
    }

    public function update(AuthUser $authUser, Customer $customer): bool
    {
        return $authUser->can('update_customer');
    }

    public function delete(AuthUser $authUser, Customer $customer): bool
    {
        return $authUser->can('delete_customer');
    }

}