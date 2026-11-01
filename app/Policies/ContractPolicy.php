<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    public function view(User $user, Contract $contract): bool
    {
        return ! $user->hasRole(Role::Executor) || $this->owns($user, $contract);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Executor);
    }

    public function update(User $user, Contract $contract): bool
    {
        return $this->owns($user, $contract) && $contract->isDraft();
    }

    public function activate(User $user, Contract $contract): bool
    {
        return $this->owns($user, $contract);
    }

    public function deliver(User $user, Contract $contract): bool
    {
        return $this->owns($user, $contract);
    }

    public function decidePenalty(User $user, Contract $contract): bool
    {
        return $user->hasRole(Role::Boss);
    }

    public function viewAccounting(User $user): bool
    {
        return $user->hasRole(Role::Boss) || $user->hasRole(Role::Accountant);
    }

    public function pay(User $user): bool
    {
        return $user->hasRole(Role::Accountant);
    }

    private function owns(User $user, Contract $contract): bool
    {
        return $user->hasRole(Role::Executor) && $contract->owner_id === $user->id;
    }
}
