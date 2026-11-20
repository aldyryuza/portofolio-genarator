<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An old slug of a renamed project, answered with a 301 to the current URL.
 *
 * @property int $id
 * @property int $project_id
 * @property string $slug
 * @property CarbonInterface $created_at
 * @property-read Project $project
 */
#[Fillable(['slug'])]
class ProjectSlugRedirect extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
