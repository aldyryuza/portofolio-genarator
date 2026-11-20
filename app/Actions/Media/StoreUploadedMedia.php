<?php

namespace App\Actions\Media;

use App\Enums\MediaVisibility;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Media\InvalidMediaException;
use App\Services\Media\MediaRules;
use App\Services\Media\MediaUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StoreUploadedMedia
{
    public function __construct(private MediaUploader $uploader) {}

    /**
     * Validate an upload (request rules, then type-specific rules) and store it.
     * Errors are reported on $field so Livewire shows them next to the input.
     *
     * @param  array{alt_text?: string|null, caption?: string|null, credit?: string|null}  $meta
     *
     * @throws ValidationException
     */
    public function handle(mixed $file, string $field, array $meta, MediaVisibility $visibility, User $actor): MediaAsset
    {
        Validator::make([$field => $file], [$field => MediaRules::upload()])->validate();

        /** @var UploadedFile $file validated as a file above */
        $isImage = in_array($file->getMimeType(), MediaAsset::IMAGE_MIME_TYPES, true);

        Validator::make(
            [$field => $file],
            [$field => $isImage ? MediaRules::image() : MediaRules::document()],
            ["{$field}.dimensions" => __('Images must be between :min and :max pixels on each side.', [
                'min' => config('portfolio.media.image.min_dimension'),
                'max' => config('portfolio.media.image.max_dimension'),
            ])],
        )->validate();

        try {
            return $this->uploader->store($file, $meta, $visibility, $actor);
        } catch (InvalidMediaException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }
}
