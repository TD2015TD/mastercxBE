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
     */
    public function run(): void
    {
        // Get role IDs
        $agentRole = DB::table('roles')->where('slug', 'agent')->first();
        $supervisorRole = DB::table('roles')->where('slug', 'supervisor')->first();
        $adminRole = DB::table('roles')->where('slug', 'admin')->first();

        // Create sample users with agent profiles
        $agents = [
            [
                'user' => [
                    'name' => 'Ahmad Rahman',
                    'email' => 'ahmad.rahman@jkdm.gov.my',
                    'password' => Hash::make('Agent@123'),
                    'role_id' => $agentRole->id,
                    'ignite_id' => 'IGN-001',
                ],
                'agent' => [
                    'employee_id' => 'EMP-001',
                    'department' => 'Customer Service',
                    'designation' => 'Senior Agent',
                    'mitel_agent_id' => 'MITEL-001',
                    'mitel_extension' => '1001',
                    'mitel_queue_ids' => ['Q001', 'Q002'],
                    'skills' => ['customs', 'gst', 'trade'],
                    'languages' => ['en', 'ms'],
                    'max_concurrent_tickets' => 5,
                    'daily_target' => 15,
                    'tickets_resolved_today' => 12,
                    'avg_response_time' => 8.5,
                    'sla_compliance_rate' => 94.0,
                    'customer_satisfaction' => 4.3,
                    'shift_start' => '08:00',
                    'shift_end' => '17:00',
                    'working_days' => [1, 2, 3, 4, 5],
                    'status' => 'active',
                    'availability' => 'available',
                ],
            ],
            [
                'user' => [
                    'name' => 'Siti Nurhaliza',
                    'email' => 'siti.nurhaliza@jkdm.gov.my',
                    'password' => Hash::make('Super@123'),
                    'role_id' => $supervisorRole->id,
                    'ignite_id' => 'IGN-002',
                ],
                'agent' => [
                    'employee_id' => 'EMP-002',
                    'department' => 'Customs Operations',
                    'designation' => 'Team Supervisor',
                    'mitel_agent_id' => 'MITEL-002',
                    'mitel_extension' => '1002',
                    'mitel_queue_ids' => ['Q001', 'Q003'],
                    'skills' => ['customs', 'compliance', 'supervision'],
                    'languages' => ['en', 'ms'],
                    'max_concurrent_tickets' => 10,
                    'daily_target' => 20,
                    'tickets_resolved_today' => 18,
                    'avg_response_time' => 7.2,
                    'sla_compliance_rate' => 96.5,
                    'customer_satisfaction' => 4.5,
                    'shift_start' => '08:00',
                    'shift_end' => '17:00',
                    'working_days' => [1, 2, 3, 4, 5],
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
                    'daily_target' => 15,
                    'tickets_resolved_today' => 8,
                    'avg_response_time' => 10.3,
                    'sla_compliance_rate' => 88.0,
                    'customer_satisfaction' => 4.0,
                    'shift_start' => '09:00',
                    'shift_end' => '18:00',
                    'working_days' => [1, 2, 3, 4, 5],
                    'status' => 'inactive',
                    'availability' => 'offline',
                ],
            ],
            [
                'user' => [
                    'name' => 'Lim Wei Ming',
                    'email' => 'lim.weiming@jkdm.gov.my',
                    'password' => Hash::make('Admin@123'),
                    'role_id' => $adminRole->id,
                    'ignite_id' => 'IGN-004',
                ],
                'agent' => [
                    'employee_id' => 'EMP-004',
                    'department' => 'IT Operations',
                    'designation' => 'System Administrator',
                    'mitel_agent_id' => 'MITEL-004',
                    'mitel_extension' => '1004',
                    'mitel_queue_ids' => ['Q001', 'Q002', 'Q003'],
                    'skills' => ['system-admin', 'technical-support', 'configuration'],
                    'languages' => ['en', 'ms', 'zh'],
                    'max_concurrent_tickets' => 8,
                    'daily_target' => 10,
                    'tickets_resolved_today' => 10,
                    'avg_response_time' => 5.8,
                    'sla_compliance_rate' => 99.0,
                    'customer_satisfaction' => 4.7,
                    'shift_start' => '08:00',
                    'shift_end' => '17:00',
                    'working_days' => [1, 2, 3, 4, 5],
                    'status' => 'active',
                    'availability' => 'available',
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
    }
}
