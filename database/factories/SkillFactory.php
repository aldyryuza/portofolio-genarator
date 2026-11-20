<?php

namespace Database\Factories;

use App\Models\Skill;
use App\Models\SkillGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = '[DEMO] '.fake()->unique()->word();

        return [
            'skill_group_id' => SkillGroup::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'sort_order' => 0,
            'is_published' => true,
        ];
    }
}
