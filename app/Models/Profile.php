<?php

namespace App\Models;

use App\Enums\AvailabilityStatus;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $display_name
 * @property string|null $headline
 * @property string|null $summary
 * @property string|null $biography
 * @property string|null $location_public
 * @property string|null $public_email
 * @property int|null $avatar_media_id
 * @property AvailabilityStatus $availability_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MediaAsset|null $avatar
 */
#[Fillable([
    'display_name',
    'headline',
    'summary',
    'biography',
    'location_public',
    'public_email',
    'availability_status',
    'avatar_media_id',
])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<SocialLink, $this>
     */
    public function socialLinks(): HasMany
    {
        return $this->hasMany(SocialLink::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function avatar(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'avatar_media_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'availability_status' => AvailabilityStatus::class,
        ];
    }
}
