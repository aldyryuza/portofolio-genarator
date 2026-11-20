<?php

namespace App\Models;

use App\Enums\MediaVisibility;
use Carbon\CarbonInterface;
use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Files live under `{path}/` on `disk`: `original.{extension}` plus, for images,
 * re-encoded `large.webp` and `thumb.webp`. Visitors only ever get derivatives.
 *
 * @property int $id
 * @property string $uuid
 * @property string $disk
 * @property string $path
 * @property string $original_filename
 * @property string $mime_type
 * @property string $extension
 * @property int $bytes
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt_text
 * @property string|null $caption
 * @property string|null $credit
 * @property MediaVisibility $visibility
 * @property int|null $uploaded_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
#[Fillable(['alt_text', 'caption', 'credit', 'visibility'])]
class MediaAsset extends Model
{
    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    public const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public const DOCUMENT_MIME_TYPES = ['application/pdf'];

    public const IMAGE_VARIANTS = ['large', 'thumb'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'visibility' => 'private',
    ];

    /**
     * Only `uuid` is generated; the numeric primary key stays auto-increment.
     *
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

    public function isImage(): bool
    {
        return in_array($this->mime_type, self::IMAGE_MIME_TYPES, true);
    }

    public function isPublic(): bool
    {
        return $this->visibility === MediaVisibility::Public;
    }

    public function originalPath(): string
    {
        return "{$this->path}/original.{$this->extension}";
    }

    public function variantPath(string $variant): string
    {
        return "{$this->path}/{$variant}.webp";
    }

    /**
     * Pixel size of a derivative (never upscaled), for width/height attributes.
     *
     * @return array{0: int, 1: int}|null
     */
    public function variantSize(string $variant): ?array
    {
        $maxWidth = config("portfolio.media.image.variants.{$variant}");

        if ($this->width === null || $this->height === null || ! is_int($maxWidth)) {
            return null;
        }

        $width = min($this->width, $maxWidth);

        return [$width, (int) round($this->height * $width / $this->width)];
    }

    public function url(string $variant = 'large'): string
    {
        return route('media.show', ['media' => $this, 'variant' => $variant]);
    }

    /**
     * Whether a project, gallery, profile, certification or CV still points at this asset.
     */
    public function isReferenced(): bool
    {
        return Project::withTrashed()->where('cover_media_id', $this->id)->exists()
            || ProjectMedia::where('media_id', $this->id)->exists()
            || Profile::where('avatar_media_id', $this->id)->exists()
            || Certification::where('file_media_id', $this->id)->exists()
            || Resume::where('file_media_id', $this->id)->exists();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function images(Builder $query): void
    {
        $query->whereIn('mime_type', self::IMAGE_MIME_TYPES);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function documents(Builder $query): void
    {
        $query->whereIn('mime_type', self::DOCUMENT_MIME_TYPES);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => MediaVisibility::class,
            'bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }
}
