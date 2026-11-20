<?php

namespace App\Models;

use App\Enums\EmploymentType;
use Carbon\CarbonInterface;
use Database\Factories\ExperienceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $company
 * @property string $title
 * @property EmploymentType $employment_type
 * @property string|null $location
 * @property CarbonInterface $start_date
 * @property CarbonInterface|null $end_date
 * @property bool $is_current
 * @property string|null $description
 * @property list<string>|null $achievements_json
 * @property int $sort_order
 * @property bool $is_published
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'company', 'title', 'employment_type', 'location', 'start_date', 'end_date',
    'is_current', 'description', 'achievements_json', 'sort_order', 'is_published',
])]
class Experience extends Model
{
    /** @use HasFactory<ExperienceFactory> */
    use HasFactory;

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Current roles first, then most recently ended, then most recently started.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function chronological(Builder $query): void
    {
        $query->orderByDesc('is_current')
            ->orderByDesc('end_date')
            ->orderByDesc('start_date')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
            'achievements_json' => 'array',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }
}
