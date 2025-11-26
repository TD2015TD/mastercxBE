<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Super Administrator with full system access',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'System Administrator with administrative privileges',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Supervisor',
                'slug' => 'supervisor',
                'description' => 'Supervisor with team management capabilities',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Agent',
                'slug' => 'agent',
                'description' => 'Customer service agent',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'User',
                'slug' => 'user',
                'description' => 'Regular user with basic access',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        // Only insert roles that don't exist
        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $role['slug']],
                $role
            );
        }

        $this->command->info('Roles seeded successfully!');
    }
}
