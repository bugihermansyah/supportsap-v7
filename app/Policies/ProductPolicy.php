<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Product;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_product');
    }

    public function view(AuthUser $authUser, Product $product): bool
    {
        return $authUser->can('view_product');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_product');
    }

    public function update(AuthUser $authUser, Product $product): bool
    {
        return $authUser->can('update_product');
    }

    public function delete(AuthUser $authUser, Product $product): bool
    {
        return $authUser->can('delete_product');
    }

}