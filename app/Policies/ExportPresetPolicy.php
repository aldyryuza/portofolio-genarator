<?php

namespace App\Policies;

use App\Models\ExportPreset;
use App\Models\User;

class ExportPresetPolicy extends OwnerOnlyPolicy
{
    public function view(User $user, ExportPreset $exportPreset): bool
    {
        return $user->is_owner;
    }
}
