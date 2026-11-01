<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class BudgetLinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Boss) || $user->hasRole(Role::Accountant);
    }

    public function manage(User $user): bool
    {
        return $user->hasRole(Role::Accountant);
    }
}
