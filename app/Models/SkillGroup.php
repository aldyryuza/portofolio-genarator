<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\SkillGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'sort_order'])]
class SkillGroup extends Model
{
    /** @use HasFactory<SkillGroupFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @return HasMany<Skill, $this>
     */
    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }

    /**
     * Groups that have at least one published skill, with only those skills loaded.
     *
     * @return Collection<int, self>
     */
    public static function withPublishedSkills(): Collection
    {
        return self::query()
            ->whereHas('skills', fn ($query) => $query->where('is_published', true))
            ->with(['skills' => fn ($query) => $query->where('is_published', true)->ordered()])
            ->ordered()
            ->get();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
