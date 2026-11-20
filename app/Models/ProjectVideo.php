<?php

namespace App\Models;

use App\Enums\VideoProvider;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property VideoProvider $provider
 * @property string $video_id
 * @property string|null $title
 * @property int $sort_order
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['provider', 'video_id', 'title', 'sort_order'])]
class ProjectVideo extends Model
{
    public function embedUrl(): string
    {
        return $this->provider->embedUrl($this->video_id);
    }

    public function watchUrl(): string
    {
        return $this->provider->watchUrl($this->video_id);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => VideoProvider::class,
            'sort_order' => 'integer',
        ];
    }
}
