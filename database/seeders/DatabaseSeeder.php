<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The pilot data creates a super admin and demo accounts with phone numbers
        // published in the README. Never create those in production.
        if (app()->environment('production')) {
            $this->command?->warn('Skipping PilotSchoolSeeder in production.');

            return;
        }

        $this->call(PilotSchoolSeeder::class);
    }
}
