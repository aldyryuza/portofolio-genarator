<?php

namespace App\Policies;

use App\Models\ExportJob;
use App\Models\User;

class ExportJobPolicy extends OwnerOnlyPolicy
{
    /**
     * Exports may contain drafts, so only the owner can ever download them.
     */
    public function view(User $user, ExportJob $exportJob): bool
    {
        return $user->is_owner;
    }
}
