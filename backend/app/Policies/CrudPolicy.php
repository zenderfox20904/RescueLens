<?php

namespace App\Policies;

use App\Models\User;

/** Admins manage every entity. Moderators manage incidents and comments only. Citizens and responders manage none here. */
class CrudPolicy
{
    public const MODERATOR = ['incidents', 'comments'];

    public function manage(User $u, string $entity, string $action = 'view'): bool
    {
        if ($u->isAdmin()) return true;
        return $u->isModerator() && in_array($entity, self::MODERATOR, true) && $action !== 'force';
    }
}
