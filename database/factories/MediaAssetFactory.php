<?php

namespace Database\Factories;

use App\Enums\MediaVisibility;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Database rows only; no files are written. Use MediaUploader in tests that
 * need real files on disk.
 *
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'disk' => 'local',
            'path' => 'media/'.$uuid,
            'original_filename' => 'demo-image.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'bytes' => 1024,
            'width' => 1200,
            'height' => 800,
            'alt_text' => '[DEMO] Placeholder image',
            'visibility' => MediaVisibility::Private,
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => MediaVisibility::Public,
        ]);
    }

    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_filename' => 'demo-document.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'width' => null,
            'height' => null,
            'alt_text' => null,
        ]);
    }
}
