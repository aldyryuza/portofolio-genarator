<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\EducationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $institution
 * @property string|null $degree
 * @property string|null $field_of_study
 * @property CarbonInterface|null $start_date
 * @property CarbonInterface|null $end_date
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_published
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['institution', 'degree', 'field_of_study', 'start_date', 'end_date', 'description', 'sort_order', 'is_published'])]
class Education extends Model
{
    /** @use HasFactory<EducationFactory> */
    use HasFactory;

    protected $table = 'educations';

    public function isOngoing(): bool
    {
        return $this->end_date === null && $this->start_date !== null;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Ongoing studies (no end date) first, then by end and start date, newest first.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function chronological(Builder $query): void
    {
        $query->orderByRaw('CASE WHEN end_date IS NULL THEN 0 ELSE 1 END')
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
            'start_date' => 'date',
            'end_date' => 'date',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }
}
