<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\QuizSession;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuizSessionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_quiz_session');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_quiz_session');
    }

    public function update(AuthUser $authUser, QuizSession $quizSession): bool
    {
        return $authUser->can('update_quiz_session');
    }

    public function delete(AuthUser $authUser, QuizSession $quizSession): bool
    {
        return $authUser->can('delete_quiz_session');
    }

}
