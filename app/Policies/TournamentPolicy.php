<?php

namespace App\Policies;

use App\Models\Tournament;
use App\Models\User;

class TournamentPolicy
{
    /**
     * Official tournaments are admin-only; clandestine ones may be administered
     * by any signed-in user who isn't an ephemeral guest.
     */
    public function manage(?User $user, Tournament $tournament): bool
    {
        if (! $user) {
            return false;
        }

        return $tournament->isOfficial()
            ? (bool) $user->is_admin
            : ! $user->is_guest;
    }

    public function createOfficial(?User $user): bool
    {
        return (bool) $user?->is_admin;
    }

    public function createClandestine(?User $user): bool
    {
        return $user !== null && ! $user->is_guest;
    }
}
