<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and users first
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
        ]);

        // Seed agents (with their users)
        $this->call([
            AgentSeeder::class,
            CustomerSeeder::class,
            TicketSeeder::class,
        ]);
    }
}
