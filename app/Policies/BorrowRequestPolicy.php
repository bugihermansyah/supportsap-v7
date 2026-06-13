<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BorrowRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class BorrowRequestPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_borrow_request');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_borrow_request');
    }

    public function update(AuthUser $authUser, BorrowRequest $borrowRequest): bool
    {
        return $authUser->can('update_borrow_request');
    }

    public function delete(AuthUser $authUser, BorrowRequest $borrowRequest): bool
    {
        return $authUser->can('delete_borrow_request');
    }

}