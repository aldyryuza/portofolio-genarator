<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @mixin Model
 */
trait HasUniqueSlug
{
    /**
     * Build a URL slug from $source that is unique in the model's table,
     * soft-deleted rows included (they still hold the unique index).
     */
    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($source), 160, '') ?: 'item';
        $slug = $base;

        for ($suffix = 2; static::slugExists($slug, $ignoreId); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    protected static function slugExists(string $slug, ?int $ignoreId): bool
    {
        return static::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
