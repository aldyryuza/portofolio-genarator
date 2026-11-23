<?php

namespace App\Models;

use App\Enums\AnalyticsEventType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only, privacy-preserving visit or download record. Written only by AnalyticsRecorder.
 *
 * @property int $id
 * @property AnalyticsEventType $type
 * @property string $path
 * @property int|null $project_id
 * @property int|null $resume_id
 * @property string|null $referrer_host
 * @property string|null $source
 * @property string $device
 * @property string $visitor_hash
 * @property CarbonInterface $created_at
 */
class AnalyticsEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Resume, $this>
     */
    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AnalyticsEventType::class,
            'created_at' => 'datetime',
        ];
    }
}
