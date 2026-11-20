<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ResumeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A CV version. Visitors may download it only while it is public and published;
 * `is_public` / `is_default` / `published_at` change only via publish(), unpublish() and makeDefault().
 *
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property string $language
 * @property string $version
 * @property int $file_media_id
 * @property bool $is_public
 * @property bool $is_default
 * @property CarbonInterface|null $published_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read MediaAsset|null $file null only if the media row was soft-deleted outside the app
 */
#[Fillable(['title', 'language', 'version', 'file_media_id'])]
class Resume extends Model
{
    /** @use HasFactory<ResumeFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_public' => false,
        'is_default' => false,
    ];

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

    public function isDownloadable(): bool
    {
        return $this->is_public && $this->published_at !== null && $this->published_at->lte(now());
    }

    public function publish(): void
    {
        $this->is_public = true;
        $this->published_at ??= now();
    }

    public function unpublish(): void
    {
        $this->is_public = false;
        $this->is_default = false;
    }

    /**
     * Publish this version and make it the only default, atomically.
     */
    public function makeDefault(): void
    {
        DB::transaction(function (): void {
            self::query()->whereKeyNot($this->id)->where('is_default', true)->update(['is_default' => false]);

            $this->publish();
            $this->is_default = true;
            $this->save();
        });
    }

    /**
     * Download filename built from our own data only, e.g. "jane-doe-cv-en-2026-11.pdf".
     */
    public function downloadFilename(string $ownerName): string
    {
        return Str::slug("{$ownerName} cv {$this->language} {$this->version}").'.pdf';
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'file_media_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function downloadable(Builder $query): void
    {
        $query->where('is_public', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_default' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
