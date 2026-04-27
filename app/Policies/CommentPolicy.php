<?php

namespace App\Policies;

use Tilto\Commentable\Contracts\Commenter;
use Tilto\Commentable\Models\Comment;
use Tilto\Commentable\Policies\CommentPolicy as CommentablePolicy;

class CommentPolicy extends CommentablePolicy
{
    public function create(Commenter $user): bool
    {
        return true;
    }

    public function update(Commenter $user, Comment $comment): bool
    {
        return true;
    }

    public function reply(Commenter $user, Comment $comment): bool
    {
        return true;
    }

    public function delete(Commenter $user, Comment $comment): bool
    {
        return true;
    }
}