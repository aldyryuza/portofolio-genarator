<?php

namespace App\Services\Media;

/**
 * Request-level validation for uploads. MediaUploader re-checks the content
 * itself, so these rules are the first of two gates, not the only one.
 */
final class MediaRules
{
    /**
     * Accepts any supported kind; follow with image() or document().
     *
     * @return list<string>
     */
    public static function upload(): array
    {
        $maxKb = max(config('portfolio.media.image.max_kb'), config('portfolio.media.document.max_kb'));

        return [
            'required',
            'file',
            // Client-supplied extension...
            'extensions:jpg,jpeg,png,webp,pdf',
            // ...and the type detected from the file content must both match.
            'mimes:jpg,jpeg,png,webp,pdf',
            'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
            "max:{$maxKb}",
        ];
    }

    /**
     * @return list<string>
     */
    public static function image(): array
    {
        $image = config('portfolio.media.image');

        return [
            'mimetypes:image/jpeg,image/png,image/webp',
            "max:{$image['max_kb']}",
            "dimensions:min_width={$image['min_dimension']},min_height={$image['min_dimension']},max_width={$image['max_dimension']},max_height={$image['max_dimension']}",
        ];
    }

    /**
     * @return list<string>
     */
    public static function document(): array
    {
        return [
            'mimetypes:application/pdf',
            'max:'.config('portfolio.media.document.max_kb'),
        ];
    }
}
