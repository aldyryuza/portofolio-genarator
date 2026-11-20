<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use App\Models\Resume;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resume>
 */
class ResumeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => '[DEMO] Curriculum Vitae',
            'language' => 'en',
            'version' => '2026-'.fake()->unique()->numberBetween(10, 99),
            'file_media_id' => MediaAsset::factory()->pdf(),
            'is_public' => false,
            'is_default' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
            'published_at' => now()->subDay(),
        ]);
    }
}
