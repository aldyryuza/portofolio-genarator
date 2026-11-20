<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-managed content that has no public-facing authorization rules:
 * only the single owner may list or change it.
 */
abstract class OwnerOnlyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_owner;
    }

    public function create(User $user): bool
    {
        return $user->is_owner;
    }

    public function update(User $user, Model $model): bool
    {
        return $user->is_owner;
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->is_owner;
    }
}
