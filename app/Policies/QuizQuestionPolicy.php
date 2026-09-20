<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\QuizQuestion;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuizQuestionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_quiz_question');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_quiz_question');
    }

    public function update(AuthUser $authUser, QuizQuestion $quizQuestion): bool
    {
        return $authUser->can('update_quiz_question');
    }

    public function delete(AuthUser $authUser, QuizQuestion $quizQuestion): bool
    {
        return $authUser->can('delete_quiz_question');
    }

}
