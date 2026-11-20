<?php

namespace Database\Factories;

use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_name' => '[DEMO] '.fake()->name(),
            'sender_email' => fake()->safeEmail(),
            'subject' => '[DEMO] Project enquiry',
            'body' => '[DEMO] Hello, I would like to talk about a project.',
            'status' => 'new',
        ];
    }
}
