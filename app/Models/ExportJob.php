<?php

namespace App\Models;

use App\Enums\ExportFormat;
use App\Enums\ExportState;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One generated export file. Written only by GeneratePortfolioExport; downloads are owner-only.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $export_preset_id
 * @property ExportFormat $format
 * @property ExportState $state
 * @property array<string, mixed> $configuration_json
 * @property string|null $output_disk
 * @property string|null $output_path
 * @property int|null $output_bytes
 * @property string|null $failed_reason
 * @property int|null $requested_by
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class ExportJob extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isReady(): bool
    {
        return $this->state === ExportState::Completed && $this->output_path !== null;
    }

    /**
     * @return BelongsTo<ExportPreset, $this>
     */
    public function preset(): BelongsTo
    {
        return $this->belongsTo(ExportPreset::class, 'export_preset_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => ExportFormat::class,
            'state' => ExportState::class,
            'configuration_json' => 'array',
            'output_bytes' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
