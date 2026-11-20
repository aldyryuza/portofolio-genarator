<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * No user is seeded here on purpose: create the owner with
     * `php artisan portfolio:create-owner` so no known default password exists.
     */
    public function run(): void
    {
        //
    }
}
