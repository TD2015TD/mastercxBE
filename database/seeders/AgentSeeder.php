<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Agent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Note: This seeder creates users WITH agent profiles (supervisor, agent roles only).
     * Users without agent profiles (super_admin, admin) are created by UserSeeder.
     * Maintains one-to-one User-Agent relationship.
     */
    public function run(): void
    {
        // Get role IDs for users WITH agent profiles
        $agentRole = DB::table('roles')->where('slug', 'agent')->first();
        $supervisorRole = DB::table('roles')->where('slug', 'supervisor')->first();

        // Create users with agent profiles (supervisor and agent roles only)
        $agents = [
            [
                'user' => [
                    'name' => 'Siti Nurhaliza',
                    'email' => 'siti.nurhaliza@jkdm.gov.my',
                    'password' => Hash::make('Super@123'),
                    'role_id' => $supervisorRole->id,
                    'ignite_id' => 'IGN-001',
                ],
                'agent' => [
                    'employee_id' => 'EMP-001',
                    'department' => 'Customs Operations',
                    'designation' => 'Team Supervisor',
                    'mitel_agent_id' => 'MITEL-001',
                    'mitel_extension' => '1001',
                    'mitel_queue_ids' => ['Q001', 'Q003'],
                    'skills' => ['customs', 'compliance', 'supervision'],
                    'languages' => ['en', 'ms'],
                    'max_concurrent_tickets' => 10,
                    'status' => 'active',
                    'availability' => 'available',
                ],
            ],
            [
                'user' => [
                    'name' => 'Ahmad Rahman',
                    'email' => 'ahmad.rahman@jkdm.gov.my',
                    'password' => Hash::make('Agent@123'),
                    'role_id' => $agentRole->id,
                    'ignite_id' => 'IGN-002',
                ],
                'agent' => [
                    'employee_id' => 'EMP-002',
                    'department' => 'Customer Service',
                    'designation' => 'Senior Agent',
                    'mitel_agent_id' => 'MITEL-002',
                    'mitel_extension' => '1002',
                    'mitel_queue_ids' => ['Q001', 'Q002'],
                    'skills' => ['customs', 'gst', 'trade'],
                    'languages' => ['en', 'ms'],
                    'max_concurrent_tickets' => 5,
                    'status' => 'active',
                    'availability' => 'available',
                ],
            ],
            [
                'user' => [
                    'name' => 'Raj Kumar',
                    'email' => 'raj.kumar@jkdm.gov.my',
                    'password' => Hash::make('Agent@123'),
                    'role_id' => $agentRole->id,
                    'ignite_id' => 'IGN-003',
                ],
                'agent' => [
                    'employee_id' => 'EMP-003',
                    'department' => 'Trade Compliance',
                    'designation' => 'Agent',
                    'mitel_agent_id' => 'MITEL-003',
                    'mitel_extension' => '1003',
                    'mitel_queue_ids' => ['Q002'],
                    'skills' => ['trade', 'compliance', 'documentation'],
                    'languages' => ['en', 'ms', 'ta'],
                    'max_concurrent_tickets' => 5,
                    'status' => 'inactive',
                    'availability' => 'offline',
                ],
            ],
        ];

        foreach ($agents as $data) {
            // Check if user already exists
            $user = User::where('email', $data['user']['email'])->first();
            
            if (!$user) {
                $user = User::create($data['user']);
            }
            
            // Check if agent profile already exists
            $existingAgent = Agent::where('user_id', $user->id)->first();
            
            if (!$existingAgent) {
                $agentData = $data['agent'];
                $agentData['user_id'] = $user->id;
                
                // Generate agent code
                $departmentCode = strtoupper(substr($agentData['department'], 0, 3));
                $agentData['agent_code'] = $departmentCode . '-' . strtoupper(substr(md5($user->email), 0, 6));
                
                Agent::create($agentData);
            }
        }

        $this->command->info('Sample agents created successfully!');
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('Users WITH Agent Profiles (One-to-One):');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('1. Siti Nurhaliza (Team Supervisor)');
        $this->command->info('   Email: siti.nurhaliza@jkdm.gov.my');
        $this->command->info('   Password: Super@123');
        $this->command->info('   Role: Supervisor | Status: Active');
        $this->command->info('');
        $this->command->info('2. Ahmad Rahman (Senior Agent)');
        $this->command->info('   Email: ahmad.rahman@jkdm.gov.my');
        $this->command->info('   Password: Agent@123');
        $this->command->info('   Role: Agent | Status: Active');
        $this->command->info('');
        $this->command->info('3. Raj Kumar (Agent)');
        $this->command->info('   Email: raj.kumar@jkdm.gov.my');
        $this->command->info('   Password: Agent@123');
        $this->command->info('   Role: Agent | Status: Inactive');
        $this->command->info('═══════════════════════════════════════════════════════════');
    }
}
