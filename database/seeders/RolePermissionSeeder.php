<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Map permissions to roles as per the original static data
        $rolePermissions = [
            'super_admin' => [
                // All permissions
                'ticket.view', 'ticket.create', 'ticket.edit', 'ticket.delete', 'ticket.assign', 'ticket.escalate', 'ticket.close',
                'customer.view', 'customer.create', 'customer.edit', 'customer.delete', 'customer.export',
                'report.view', 'report.create', 'report.export', 'analytics.view', 'kpi.view',
                'user.manage', 'role.manage', 'sla.manage', 'category.manage', 'system.config', 'audit.view',
                'kb.view', 'kb.create', 'kb.edit', 'kb.delete', 'kb.approve',
                'mitel.view', 'mitel.call', 'mitel.transfer', 'mitel.recording', 'mitel.queue',
            ],
            'admin' => [
                // All permissions (same as super_admin)
                'ticket.view', 'ticket.create', 'ticket.edit', 'ticket.delete', 'ticket.assign', 'ticket.escalate', 'ticket.close',
                'customer.view', 'customer.create', 'customer.edit', 'customer.delete', 'customer.export',
                'report.view', 'report.create', 'report.export', 'analytics.view', 'kpi.view',
                'user.manage', 'role.manage', 'sla.manage', 'category.manage', 'system.config', 'audit.view',
                'kb.view', 'kb.create', 'kb.edit', 'kb.delete', 'kb.approve',
                'mitel.view', 'mitel.call', 'mitel.transfer', 'mitel.recording', 'mitel.queue',
            ],
            'supervisor' => [
                'ticket.view', 'ticket.create', 'ticket.edit', 'ticket.delete', 'ticket.assign', 'ticket.escalate', 'ticket.close',
                'customer.view', 'customer.create', 'customer.edit', 'customer.export',
                'report.view', 'report.create', 'report.export', 'analytics.view', 'kpi.view',
                'sla.manage', 'category.manage', 'audit.view',
                'kb.view', 'kb.create', 'kb.edit', 'kb.approve',
                'mitel.view', 'mitel.call', 'mitel.transfer', 'mitel.recording', 'mitel.queue',
            ],
            'agent' => [
                'ticket.view', 'ticket.create', 'ticket.edit', 'ticket.escalate', 'ticket.close',
                'customer.view', 'customer.create', 'customer.edit',
                'report.view', 'report.export', 'kpi.view',
                'kb.view', 'kb.create', 'kb.edit',
                'mitel.view', 'mitel.call', 'mitel.transfer',
            ],
            'customer' => [
                'ticket.view', 'ticket.create',
                'kb.view',
            ],
        ];

        foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
            $role = Role::where('slug', $roleSlug)->first();
            
            if (!$role) {
                $this->command->warn("Role '{$roleSlug}' not found. Skipping...");
                continue;
            }

            $permissions = Permission::whereIn('slug', $permissionSlugs)->pluck('id');
            
            if ($permissions->isEmpty()) {
                $this->command->warn("No permissions found for role '{$roleSlug}'. Skipping...");
                continue;
            }

            $role->permissions()->sync($permissions);
            
            $this->command->info("Assigned {$permissions->count()} permissions to role '{$role->name}'");
        }

        $this->command->info('Role permissions seeded successfully!');
    }
}
