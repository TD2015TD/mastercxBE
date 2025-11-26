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
    public function run(): void
    {
        // Get super_admin role ID
        $superAdminRole = DB::table('roles')->where('slug', 'super_admin')->first();
        $adminRole = DB::table('roles')->where('slug', 'admin')->first();
        $supervisorRole = DB::table('roles')->where('slug', 'supervisor')->first();
        $agentRole = DB::table('roles')->where('slug', 'agent')->first();

        $users = [
            [
                'name' => 'Super Administrator',
                'email' => 'superadmin@jkm.gov.my',
                'password' => Hash::make('SuperAdmin@123'),
                'role_id' => $superAdminRole?->id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'System Admin',
                'email' => 'admin@jkm.gov.my',
                'password' => Hash::make('Admin@123'),
                'role_id' => $adminRole?->id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Supervisor User',
                'email' => 'supervisor@jkm.gov.my',
                'password' => Hash::make('Supervisor@123'),
                'role_id' => $supervisorRole?->id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Ahmad Smith',
                'email' => 'ahmad.smith@jkm.gov.my',
                'password' => Hash::make('password123'),
                'role_id' => $agentRole?->id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        // Only insert users that don't exist
        foreach ($users as $user) {
            if (!DB::table('users')->where('email', $user['email'])->exists()) {
                DB::table('users')->insert($user);
            }
        }

        $this->command->info('Users seeded successfully!');
        $this->command->info('');
        $this->command->info('Default Login Credentials:');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('Super Admin:');
        $this->command->info('  Email: superadmin@jkm.gov.my');
        $this->command->info('  Password: SuperAdmin@123');
        $this->command->info('');
        $this->command->info('Admin:');
        $this->command->info('  Email: admin@jkm.gov.my');
        $this->command->info('  Password: Admin@123');
        $this->command->info('');
        $this->command->info('Supervisor:');
        $this->command->info('  Email: supervisor@jkm.gov.my');
        $this->command->info('  Password: Supervisor@123');
        $this->command->info('');
        $this->command->info('Agent:');
        $this->command->info('  Email: ahmad.smith@jkm.gov.my');
        $this->command->info('  Password: password123');
        $this->command->info('═══════════════════════════════════════════════════════════');
    }
}
