<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Creates the first admin account.
     * Set ADMIN_USERNAME and ADMIN_PASSWORD in .env before running this seeder.
     */
    public function run(): void
    {
        $username = env('ADMIN_USERNAME', 'admin');
        $password = env('ADMIN_PASSWORD');

        if (! $password) {
            $this->command->error('ADMIN_PASSWORD is not set in .env. No admin was created.');

            return;
        }

        User::updateOrCreate(
            ['username' => $username],
            ['name' => 'Administrator', 'role' => 'admin', 'password' => $password]
        );

        $this->command->info("Admin account '{$username}' is ready.");
    }
}
