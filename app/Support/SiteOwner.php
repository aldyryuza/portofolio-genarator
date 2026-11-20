<?php

namespace App\Support;

use App\Models\Profile;
use App\Models\SocialLink;

/**
 * The single owner's public profile, loaded once per request for the public site.
 * Registered as a scoped singleton in AppServiceProvider.
 */
class SiteOwner
{
    private ?Profile $profile = null;

    private bool $loaded = false;

    public function profile(): ?Profile
    {
        if (! $this->loaded) {
            $this->profile = Profile::query()
                ->whereHas('user', fn ($query) => $query->where('is_owner', true))
                ->with([
                    'avatar',
                    'socialLinks' => fn ($query) => $query->visible()->ordered(),
                ])
                ->first();
            $this->loaded = true;
        }

        return $this->profile;
    }

    public function name(): string
    {
        return $this->profile()->display_name ?? (string) config('app.name');
    }

    /**
     * Avatar only when it is a public image.
     */
    public function avatarUrl(string $variant = 'thumb'): ?string
    {
        $avatar = $this->profile()?->avatar;

        return $avatar !== null && $avatar->isPublic() ? $avatar->url($variant) : null;
    }

    /**
     * @return iterable<SocialLink>
     */
    public function socialLinks(): iterable
    {
        return $this->profile()->socialLinks ?? [];
    }
}
