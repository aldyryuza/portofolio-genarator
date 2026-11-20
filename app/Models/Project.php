<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Models\Concerns\HasUniqueSlug;
use Carbon\CarbonInterface;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * `status` and `published_at` are not fillable: change them with publish()/unpublish().
 *
 * @property int $id
 * @property int|null $category_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $summary
 * @property string|null $challenge
 * @property string|null $solution
 * @property string|null $outcome
 * @property string|null $role
 * @property CarbonInterface|null $start_date
 * @property CarbonInterface|null $end_date
 * @property string|null $client_label
 * @property int|null $cover_media_id
 * @property ProjectStatus $status
 * @property CarbonInterface|null $published_at
 * @property bool $is_featured
 * @property int $sort_order
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property-read Category|null $category
 * @property-read MediaAsset|null $cover
 * @property-read Collection<int, ProjectMedia> $mediaItems
 * @property-read Collection<int, ProjectVideo> $videos
 * @property-read Collection<int, Skill> $skills
 * @property-read Collection<int, Tag> $tags
 */
#[Fillable([
    'category_id',
    'title',
    'slug',
    'excerpt',
    'summary',
    'challenge',
    'solution',
    'outcome',
    'role',
    'start_date',
    'end_date',
    'client_label',
    'is_featured',
    'meta_title',
    'meta_description',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUniqueSlug, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'is_featured' => false,
        'sort_order' => 0,
    ];

    public function isPublished(): bool
    {
        return $this->status === ProjectStatus::Published
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    /**
     * Mark as published, keeping the original publish date on re-publish.
     */
    public function publish(): void
    {
        $this->status = ProjectStatus::Published;
        $this->published_at ??= now();
    }

    public function unpublish(): void
    {
        $this->status = ProjectStatus::Draft;
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    /**
     * Gallery entries in display order.
     *
     * @return HasMany<ProjectMedia, $this>
     */
    public function mediaItems(): HasMany
    {
        return $this->hasMany(ProjectMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<ProjectSlugRedirect, $this>
     */
    public function slugRedirects(): HasMany
    {
        return $this->hasMany(ProjectSlugRedirect::class);
    }

    /**
     * @return HasMany<ProjectVideo, $this>
     */
    public function videos(): HasMany
    {
        return $this->hasMany(ProjectVideo::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Only projects visitors may see. Every public query, sitemap and export must use this.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', ProjectStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'published_at' => 'datetime',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
