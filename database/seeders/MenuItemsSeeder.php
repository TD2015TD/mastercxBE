<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuItemsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menuItems = [
            [
                'label' => 'Dashboard',
                'path' => '/agent-dashboard',
                'icon' => 'LayoutDashboard',
                'description' => 'View your daily workspace and active assignments',
                'tooltip' => 'View your daily workspace and active assignments',
                'order' => 1,
                'parent_id' => null,
                'is_active' => true,
                'metadata' => json_encode(['type' => 'dashboard']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Employee Inbox',
                'path' => '/employee-inbox',
                'icon' => 'Inbox',
                'description' => 'Manage conversations, calls, emails, and chats',
                'tooltip' => 'Manage all your conversations in one place',
                'order' => 2,
                'parent_id' => null,
                'is_active' => true,
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Supervisor Dashboard',
                'path' => '/supervisor-dashboard',
                'icon' => 'LayoutDashboard',
                'description' => 'Supervisor workspace and team overview',
                'tooltip' => 'Supervisor workspace and team overview',
                'order' => 3,
                'parent_id' => null,
                'is_active' => true,
                'metadata' => json_encode(['type' => 'dashboard']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Tickets',
                'path' => '/ticket-management',
                'icon' => 'Ticket',
                'description' => 'Manage customer tickets and cases',
                'tooltip' => 'Manage customer tickets and cases',
                'order' => 4,
                'parent_id' => null,
                'is_active' => true,
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Customer 360',
                'path' => '/customer-360-profile',
                'icon' => 'Users',
                'description' => 'Complete customer profile and interaction history',
                'tooltip' => 'Complete customer profile and interaction history',
                'order' => 5,
                'parent_id' => null,
                'is_active' => true,
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Knowledge Base',
                'path' => '/knowledge-base-portal',
                'icon' => 'BookOpen',
                'description' => 'Access articles and documentation',
                'tooltip' => 'Access articles and documentation',
                'order' => 6,
                'parent_id' => null,
                'is_active' => true,
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Analytics',
                'path' => '/analytics-and-reporting',
                'icon' => 'BarChart3',
                'description' => 'Performance monitoring and reports',
                'tooltip' => 'Performance monitoring and reports',
                'order' => 7,
                'parent_id' => null,
                'is_active' => true,
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Administration',
                'path' => '/system-administration',
                'icon' => 'Settings',
                'description' => 'System configuration and user management',
                'tooltip' => 'System configuration and user management',
                'order' => 8,
                'parent_id' => null,
                'is_active' => true,
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'KB Creator Portal',
                'path' => '/knowledge-base-content-creator-portal',
                'icon' => 'Edit',
                'description' => 'Knowledge base content creation',
                'tooltip' => 'Knowledge base content creation',
                'order' => 9,
                'parent_id' => null,
                'is_active' => true,
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('menu_items')->insert($menuItems);
    }
}
