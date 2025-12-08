<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleMenuItemsSeeder extends Seeder
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
        $superAdminRole = DB::table('roles')->where('slug', 'super_admin')->first();

        // Get menu item IDs
        $dashboardMenu = DB::table('menu_items')->where('path', '/agent-dashboard')->first();
        $supervisorDashboardMenu = DB::table('menu_items')->where('path', '/supervisor-dashboard')->first();
        $ticketsMenu = DB::table('menu_items')->where('path', '/ticket-management')->first();
        $customerMenu = DB::table('menu_items')->where('path', '/customer-360-profile')->first();
        $kbMenu = DB::table('menu_items')->where('path', '/knowledge-base-portal')->first();
        $analyticsMenu = DB::table('menu_items')->where('path', '/analytics-and-reporting')->first();
        $adminMenu = DB::table('menu_items')->where('path', '/system-administration')->first();
        $kbCreatorMenu = DB::table('menu_items')->where('path', '/knowledge-base-content-creator-portal')->first();

        $roleMenuItems = [];

        // Agent Role Menus
        if ($agentRole) {
            $roleMenuItems[] = [
                'role_id' => $agentRole->id,
                'menu_item_id' => $dashboardMenu->id,
                'can_view' => true,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $roleMenuItems[] = [
                'role_id' => $agentRole->id,
                'menu_item_id' => $ticketsMenu->id,
                'can_view' => true,
                'can_create' => true,
                'can_edit' => true,
                'can_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $roleMenuItems[] = [
                'role_id' => $agentRole->id,
                'menu_item_id' => $customerMenu->id,
                'can_view' => true,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $roleMenuItems[] = [
                'role_id' => $agentRole->id,
                'menu_item_id' => $kbMenu->id,
                'can_view' => true,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Supervisor Role Menus
        if ($supervisorRole) {
            $roleMenuItems[] = [
                'role_id' => $supervisorRole->id,
                'menu_item_id' => $supervisorDashboardMenu->id,
                'can_view' => true,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $roleMenuItems[] = [
                'role_id' => $supervisorRole->id,
                'menu_item_id' => $ticketsMenu->id,
                'can_view' => true,
                'can_create' => true,
                'can_edit' => true,
                'can_delete' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $roleMenuItems[] = [
                'role_id' => $supervisorRole->id,
                'menu_item_id' => $customerMenu->id,
                'can_view' => true,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $roleMenuItems[] = [
                'role_id' => $supervisorRole->id,
                'menu_item_id' => $kbMenu->id,
                'can_view' => true,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $roleMenuItems[] = [
                'role_id' => $supervisorRole->id,
                'menu_item_id' => $analyticsMenu->id,
                'can_view' => true,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Admin Role Menus (All access)
        if ($adminRole) {
            $allMenus = [
                $dashboardMenu, $supervisorDashboardMenu, $ticketsMenu, $customerMenu, 
                $kbMenu, $analyticsMenu, $adminMenu, $kbCreatorMenu
            ];
            
            foreach ($allMenus as $menu) {
                $roleMenuItems[] = [
                    'role_id' => $adminRole->id,
                    'menu_item_id' => $menu->id,
                    'can_view' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Super Admin Role Menus (All access)
        if ($superAdminRole) {
            $allMenus = [
                $dashboardMenu, $supervisorDashboardMenu, $ticketsMenu, $customerMenu, 
                $kbMenu, $analyticsMenu, $adminMenu, $kbCreatorMenu
            ];
            
            foreach ($allMenus as $menu) {
                $roleMenuItems[] = [
                    'role_id' => $superAdminRole->id,
                    'menu_item_id' => $menu->id,
                    'can_view' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('role_menu_items')->insert($roleMenuItems);
    }
}
