<?php

namespace Database\Factories;

use App\Enums\AvailabilityStatus;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->owner(),
            'display_name' => '[DEMO] '.fake()->name(),
            'headline' => '[DEMO] Software Engineer',
            'summary' => '[DEMO] Placeholder summary.',
            'availability_status' => AvailabilityStatus::NotAvailable,
        ];
    }
}
