<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy extends OwnerOnlyPolicy
{
    /**
     * Visitors may only see published projects; drafts are owner-only.
     */
    public function view(?User $user, Project $project): bool
    {
        return $project->isPublished() || $user?->is_owner === true;
    }

    public function restore(User $user, Project $project): bool
    {
        return $user->is_owner;
    }
}
