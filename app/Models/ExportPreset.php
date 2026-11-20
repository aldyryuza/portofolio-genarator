<?php

namespace App\Models;

use App\Enums\ExportFormat;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property ExportFormat $format
 * @property array<string, mixed> $configuration_json
 * @property string $template_key
 * @property int|null $created_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['name', 'format', 'configuration_json', 'template_key'])]
class ExportPreset extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => ExportFormat::class,
            'configuration_json' => 'array',
        ];
    }
}
