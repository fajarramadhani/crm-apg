<?php

namespace App\Services;

use App\Models\User;

final class PermissionRegistry
{
    /** @return list<string> */
    public function forUser(User $user): array
    {
        $roleKey = $user->role?->key;

        if ($roleKey === null) {
            return [];
        }

        return array_values(array_unique(config("permissions.roles.{$roleKey}", [])));
    }

    public function userHas(User $user, string $permission): bool
    {
        return in_array($permission, $this->forUser($user), true);
    }
}
