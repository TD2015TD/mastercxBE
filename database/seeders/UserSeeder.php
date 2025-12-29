<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    /**
     * Run the database seeds.
     * 
     * Note: This seeder creates users WITHOUT agent profiles (super_admin, admin).
     * Users with agent profiles (supervisor, agent) are created by AgentSeeder.
     */
    public function run(): void
    {
        // Get role IDs for users WITHOUT agent profiles
        $superAdminRole = DB::table('roles')->where('slug', 'super_admin')->first();
        $adminRole = DB::table('roles')->where('slug', 'admin')->first();

        // Only create users that don't need agent profiles
        $users = [
            [
                'name' => 'Super Administrator',
                'username' => 'superadmin@jkdm.gov.my',
                'email' => 'superadmin@jkdm.gov.my',
                'password' => Hash::make('SuperAdmin@123'),
                'role_id' => $superAdminRole?->id,
                'status' => 'active',
                'ignite_id' => null,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'System Admin',
                'username' => 'admin@jkdm.gov.my',
                'email' => 'admin@jkdm.gov.my',
                'password' => Hash::make('Admin@123'),
                'role_id' => $adminRole?->id,
                'status' => 'active',
                'ignite_id' => null,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];

        // Only insert users that don't exist
        foreach ($users as $user) {
            if (!DB::table('users')->where('username', $user['username'])->exists()) {
                DB::table('users')->insert($user);
            }
        }

        $this->command->info('Users seeded successfully!');
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('Users WITHOUT Agent Profiles:');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('Super Admin:');
        $this->command->info('  Email: superadmin@jkdm.gov.my');
        $this->command->info('  Password: SuperAdmin@123');
        $this->command->info('');
        $this->command->info('Admin:');
        $this->command->info('  Email: admin@jkdm.gov.my');
        $this->command->info('  Password: Admin@123');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('Note: Supervisor and Agent users are created by AgentSeeder');
        $this->command->info('      with their corresponding agent profiles (one-to-one).');
        $this->command->info('═══════════════════════════════════════════════════════════');
    }
}
