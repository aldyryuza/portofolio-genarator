<?php

namespace Database\Factories;

use App\Enums\SocialPlatform;
use App\Models\Profile;
use App\Models\SocialLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialLink>
 */
class SocialLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'platform' => SocialPlatform::GitHub,
            'url' => 'https://github.com/demo-'.fake()->unique()->numberBetween(1, 99999),
            'sort_order' => 0,
            'is_visible' => true,
        ];
    }
}
