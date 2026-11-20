<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A gallery entry linking a project to a media asset.
 *
 * @property int $id
 * @property int $project_id
 * @property int $media_id
 * @property string $role
 * @property string|null $caption
 * @property bool $is_confidential Hidden from visitors and from non-internal exports
 * @property int $sort_order
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Project $project
 * @property-read MediaAsset $media
 */
#[Fillable(['media_id', 'role', 'caption', 'is_confidential', 'sort_order'])]
class ProjectMedia extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_confidential' => false,
    ];

    protected $table = 'project_media';

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_confidential' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
