<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Models\Experience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Experience>
 */
class ExperienceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company' => '[DEMO] Company '.fake()->unique()->numberBetween(1, 99999),
            'title' => '[DEMO] Software Engineer',
            'employment_type' => EmploymentType::FullTime,
            'start_date' => '2020-01-01',
            'end_date' => '2022-12-31',
            'is_current' => false,
            'achievements_json' => null,
            'sort_order' => 0,
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => true]);
    }

    public function current(): static
    {
        return $this->state(fn (array $attributes) => ['is_current' => true, 'end_date' => null]);
    }
}
