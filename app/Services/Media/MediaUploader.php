<?php

namespace App\Services\Media;

use App\Enums\MediaVisibility;
use App\Models\ActivityLog;
use App\Models\MediaAsset;
use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MediaUploader
{
    /** @var array<int, array{mime: string, extension: string}> */
    private const IMAGE_TYPES = [
        IMAGETYPE_JPEG => ['mime' => 'image/jpeg', 'extension' => 'jpg'],
        IMAGETYPE_PNG => ['mime' => 'image/png', 'extension' => 'png'],
        IMAGETYPE_WEBP => ['mime' => 'image/webp', 'extension' => 'webp'],
    ];

    /**
     * Store an upload that already passed MediaRules. Images are decoded and
     * re-encoded into WebP derivatives, which strips metadata and anything
     * appended to the file; PDFs are stored as-is but only after a signature check.
     *
     * @param  array{alt_text?: string|null, caption?: string|null, credit?: string|null}  $meta
     *
     * @throws InvalidMediaException
     */
    public function store(UploadedFile $file, array $meta, MediaVisibility $visibility, User $actor): MediaAsset
    {
        $realPath = $file->getRealPath();

        if ($realPath === false || ! is_readable($realPath)) {
            throw new InvalidMediaException(__('The uploaded file could not be read.'));
        }

        $uuid = (string) Str::uuid();
        $directory = 'media/'.$uuid;
        $disk = Storage::disk(config('portfolio.media.disk'));

        $details = $file->getMimeType() === 'application/pdf'
            ? $this->storeDocument($realPath, $directory)
            : $this->storeImage($realPath, $directory);

        try {
            return DB::transaction(function () use ($file, $meta, $visibility, $actor, $uuid, $directory, $details): MediaAsset {
                $media = new MediaAsset([
                    'alt_text' => $meta['alt_text'] ?? null,
                    'caption' => $meta['caption'] ?? null,
                    'credit' => $meta['credit'] ?? null,
                    'visibility' => $visibility,
                ]);
                $media->forceFill([
                    'uuid' => $uuid,
                    'disk' => config('portfolio.media.disk'),
                    'path' => $directory,
                    'original_filename' => $this->safeFilename($file->getClientOriginalName()),
                    'uploaded_by' => $actor->id,
                    ...$details,
                ])->save();

                ActivityLog::record($actor, 'media.uploaded', $media, ['mime_type' => $media->mime_type]);

                return $media;
            });
        } catch (Throwable $exception) {
            $disk->deleteDirectory($directory);

            throw $exception;
        }
    }

    /**
     * @return array{mime_type: string, extension: string, bytes: int, width: int, height: int}
     */
    private function storeImage(string $realPath, string $directory): array
    {
        $config = config('portfolio.media.image');
        $info = @getimagesize($realPath);

        if ($info === false || ! isset(self::IMAGE_TYPES[$info[2]])) {
            throw new InvalidMediaException(__('Only JPG, PNG and WebP images are allowed.'));
        }

        [$width, $height] = $info;

        if ($width * $height > $config['max_pixels']) {
            throw new InvalidMediaException(__('The image has too many pixels.'));
        }

        $contents = (string) file_get_contents($realPath);
        $image = @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            throw new InvalidMediaException(__('The image could not be decoded.'));
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $image = $this->applyExifOrientation($image, $realPath);
        }

        $type = self::IMAGE_TYPES[$info[2]];
        $disk = Storage::disk(config('portfolio.media.disk'));

        try {
            $disk->put("{$directory}/original.{$type['extension']}", $contents);

            foreach ($config['variants'] as $variant => $maxWidth) {
                $disk->put("{$directory}/{$variant}.webp", $this->encodeWebp($image, $maxWidth, $config['quality']));
            }
        } catch (Throwable $exception) {
            $disk->deleteDirectory($directory);

            throw $exception;
        }

        return [
            'mime_type' => $type['mime'],
            'extension' => $type['extension'],
            'bytes' => strlen($contents),
            'width' => imagesx($image),
            'height' => imagesy($image),
        ];
    }

    /**
     * @return array{mime_type: string, extension: string, bytes: int, width: null, height: null}
     */
    private function storeDocument(string $realPath, string $directory): array
    {
        if (! $this->hasPdfSignature($realPath)) {
            throw new InvalidMediaException(__('The file is not a valid PDF document.'));
        }

        $contents = (string) file_get_contents($realPath);

        Storage::disk(config('portfolio.media.disk'))->put("{$directory}/original.pdf", $contents);

        return [
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'bytes' => strlen($contents),
            'width' => null,
            'height' => null,
        ];
    }

    private function hasPdfSignature(string $realPath): bool
    {
        $handle = fopen($realPath, 'rb');
        $header = $handle === false ? '' : (string) fread($handle, 5);

        if ($handle !== false) {
            fclose($handle);
        }

        return $header === '%PDF-';
    }

    private function encodeWebp(GdImage $image, int $maxWidth, int $quality): string
    {
        $source = imagesx($image) > $maxWidth ? imagescale($image, $maxWidth, -1, IMG_BICUBIC) : $image;

        if (! $source instanceof GdImage) {
            throw new InvalidMediaException(__('The image could not be resized.'));
        }

        imagepalettetotruecolor($source);
        imagealphablending($source, false);
        imagesavealpha($source, true);

        ob_start();
        imagewebp($source, null, $quality);

        return (string) ob_get_clean();
    }

    private function applyExifOrientation(GdImage $image, string $realPath): GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($realPath) : false;
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    /**
     * Display-only copy of the client filename; never used as a storage path.
     */
    private function safeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);

        return Str::limit($name !== '' ? $name : 'upload', 200, '');
    }
}
