<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /**
     * Serve an image derivative or (owner only) the original file.
     * Unauthorized requests get 404 so private assets are not confirmed to exist.
     */
    public function __invoke(MediaAsset $media, string $variant): StreamedResponse
    {
        $isOriginal = $variant === 'original';

        abort_unless(Gate::allows($isOriginal ? 'viewOriginal' : 'view', $media), 404);
        abort_if(! $isOriginal && ! $media->isImage(), 404);

        $path = $isOriginal ? $media->originalPath() : $media->variantPath($variant);
        $disk = Storage::disk($media->disk);

        abort_unless($disk->exists($path), 404);

        $headers = [
            'Content-Type' => $isOriginal ? $media->mime_type : 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            // A file opened directly must never run scripts or load other resources.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => $media->isPublic() && ! $isOriginal ? 'public, max-age=3600' : 'private, no-store',
        ];

        $disposition = $isOriginal && ! $media->isImage() ? 'attachment' : 'inline';
        $filename = $isOriginal ? $media->original_filename : pathinfo($media->original_filename, PATHINFO_FILENAME)."-{$variant}.webp";

        return $disk->response($path, $filename, $headers, $disposition);
    }
}
