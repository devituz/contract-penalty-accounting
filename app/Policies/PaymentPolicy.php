<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class PaymentPolicy
{
    public function void(User $user): bool
    {
        return $user->hasRole(Role::Accountant);
    }
}
