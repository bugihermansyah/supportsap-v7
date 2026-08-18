<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class StockRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_stock_request');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_stock_request');
    }

    public function update(AuthUser $authUser, StockRequest $stockRequest): bool
    {
        return $authUser->can('update_stock_request');
    }

    public function delete(AuthUser $authUser, StockRequest $stockRequest): bool
    {
        return $authUser->can('delete_stock_request');
    }
}
