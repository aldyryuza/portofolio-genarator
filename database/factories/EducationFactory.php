<?php

namespace Database\Factories;

use App\Models\Education;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Education>
 */
class EducationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution' => '[DEMO] University '.fake()->unique()->numberBetween(1, 99999),
            'degree' => '[DEMO] Bachelor',
            'field_of_study' => '[DEMO] Computer Science',
            'start_date' => '2014-08-01',
            'end_date' => '2018-07-31',
            'sort_order' => 0,
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => true]);
    }
}
