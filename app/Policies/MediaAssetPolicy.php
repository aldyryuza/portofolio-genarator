<?php

namespace App\Policies;

use App\Models\MediaAsset;
use App\Models\User;

class MediaAssetPolicy extends OwnerOnlyPolicy
{
    /**
     * Visitors may see derivatives of public assets only.
     */
    public function view(?User $user, MediaAsset $media): bool
    {
        return $media->isPublic() || $user?->is_owner === true;
    }

    /**
     * Unprocessed originals are owner-only, regardless of visibility.
     */
    public function viewOriginal(User $user, MediaAsset $media): bool
    {
        return $user->is_owner;
    }
}
