<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupportHoliday;
use Illuminate\Foundation\Auth\User as AuthUser;

class SupportHolidayPolicy
{
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_support_holiday');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_support_holiday');
    }

    public function update(AuthUser $authUser, SupportHoliday $supportHoliday): bool
    {
        return $authUser->can('update_support_holiday');
    }

    public function delete(AuthUser $authUser, SupportHoliday $supportHoliday): bool
    {
        return $authUser->can('delete_support_holiday');
    }
}
