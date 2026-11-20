<?php

namespace App\Policies;

use App\Models\Resume;
use App\Models\User;

class ResumePolicy extends OwnerOnlyPolicy
{
    /**
     * Visitors may download public, published CVs; the owner may download any.
     */
    public function download(?User $user, Resume $resume): bool
    {
        return $resume->isDownloadable() || $user?->is_owner === true;
    }
}
