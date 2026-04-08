<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Ticket Management
            [
                'name' => 'View Tickets',
                'slug' => 'ticket.view',
                'module' => 'Ticket Management',
                'description' => 'Ability to view tickets',
                'order' => 1,
            ],
            [
                'name' => 'Create Tickets',
                'slug' => 'ticket.create',
                'module' => 'Ticket Management',
                'description' => 'Ability to create new tickets',
                'order' => 2,
            ],
            [
                'name' => 'Edit Tickets',
                'slug' => 'ticket.edit',
                'module' => 'Ticket Management',
                'description' => 'Ability to edit existing tickets',
                'order' => 3,
            ],
            [
                'name' => 'Delete Tickets',
                'slug' => 'ticket.delete',
                'module' => 'Ticket Management',
                'description' => 'Ability to delete tickets',
                'order' => 4,
            ],
            [
                'name' => 'Assign Tickets',
                'slug' => 'ticket.assign',
                'module' => 'Ticket Management',
                'description' => 'Ability to assign tickets to agents',
                'order' => 5,
            ],
            [
                'name' => 'Escalate Tickets',
                'slug' => 'ticket.escalate',
                'module' => 'Ticket Management',
                'description' => 'Ability to escalate tickets',
                'order' => 6,
            ],
            [
                'name' => 'Close Tickets',
                'slug' => 'ticket.close',
                'module' => 'Ticket Management',
                'description' => 'Ability to close tickets',
                'order' => 7,
            ],

            // Customer Management
            [
                'name' => 'View Customers',
                'slug' => 'customer.view',
                'module' => 'Customer Management',
                'description' => 'Ability to view customer information',
                'order' => 1,
            ],
            [
                'name' => 'Create Customers',
                'slug' => 'customer.create',
                'module' => 'Customer Management',
                'description' => 'Ability to create new customers',
                'order' => 2,
            ],
            [
                'name' => 'Edit Customers',
                'slug' => 'customer.edit',
                'module' => 'Customer Management',
                'description' => 'Ability to edit customer information',
                'order' => 3,
            ],
            [
                'name' => 'Delete Customers',
                'slug' => 'customer.delete',
                'module' => 'Customer Management',
                'description' => 'Ability to delete customers',
                'order' => 4,
            ],
            [
                'name' => 'Export Customer Data',
                'slug' => 'customer.export',
                'module' => 'Customer Management',
                'description' => 'Ability to export customer data',
                'order' => 5,
            ],

            // Reporting & Analytics
            [
                'name' => 'View Reports',
                'slug' => 'report.view',
                'module' => 'Reporting & Analytics',
                'description' => 'Ability to view reports',
                'order' => 1,
            ],
            [
                'name' => 'Create Reports',
                'slug' => 'report.create',
                'module' => 'Reporting & Analytics',
                'description' => 'Ability to create custom reports',
                'order' => 2,
            ],
            [
                'name' => 'Export Reports',
                'slug' => 'report.export',
                'module' => 'Reporting & Analytics',
                'description' => 'Ability to export reports',
                'order' => 3,
            ],
            [
                'name' => 'View Analytics',
                'slug' => 'analytics.view',
                'module' => 'Reporting & Analytics',
                'description' => 'Ability to view analytics dashboards',
                'order' => 4,
            ],
            [
                'name' => 'View KPIs',
                'slug' => 'kpi.view',
                'module' => 'Reporting & Analytics',
                'description' => 'Ability to view key performance indicators',
                'order' => 5,
            ],

            // System Administration
            [
                'name' => 'Manage Users',
                'slug' => 'user.manage',
                'module' => 'System Administration',
                'description' => 'Ability to manage system users',
                'order' => 1,
            ],
            [
                'name' => 'Manage Roles',
                'slug' => 'role.manage',
                'module' => 'System Administration',
                'description' => 'Ability to manage roles and permissions',
                'order' => 2,
            ],
            [
                'name' => 'Manage SLA',
                'slug' => 'sla.manage',
                'module' => 'System Administration',
                'description' => 'Ability to manage SLA configurations',
                'order' => 3,
            ],
            [
                'name' => 'Manage Categories',
                'slug' => 'category.manage',
                'module' => 'System Administration',
                'description' => 'Ability to manage ticket categories',
                'order' => 4,
            ],
            [
                'name' => 'System Configuration',
                'slug' => 'system.config',
                'module' => 'System Administration',
                'description' => 'Ability to configure system settings',
                'order' => 5,
            ],
            [
                'name' => 'View Audit Logs',
                'slug' => 'audit.view',
                'module' => 'System Administration',
                'description' => 'Ability to view audit logs',
                'order' => 6,
            ],

            // Knowledge Base
            [
                'name' => 'View Knowledge Base',
                'slug' => 'kb.view',
                'module' => 'Knowledge Base',
                'description' => 'Ability to view knowledge base articles',
                'order' => 1,
            ],
            [
                'name' => 'Create Articles',
                'slug' => 'kb.create',
                'module' => 'Knowledge Base',
                'description' => 'Ability to create knowledge base articles',
                'order' => 2,
            ],
            [
                'name' => 'Edit Articles',
                'slug' => 'kb.edit',
                'module' => 'Knowledge Base',
                'description' => 'Ability to edit knowledge base articles',
                'order' => 3,
            ],
            [
                'name' => 'Delete Articles',
                'slug' => 'kb.delete',
                'module' => 'Knowledge Base',
                'description' => 'Ability to delete knowledge base articles',
                'order' => 4,
            ],
            [
                'name' => 'Approve Articles',
                'slug' => 'kb.approve',
                'module' => 'Knowledge Base',
                'description' => 'Ability to approve knowledge base articles',
                'order' => 5,
            ],

            // Telephony (Mitel)
            [
                'name' => 'View Call Data',
                'slug' => 'mitel.view',
                'module' => 'Telephony (Mitel)',
                'description' => 'Ability to view call data and history',
                'order' => 1,
            ],
            [
                'name' => 'Make Calls',
                'slug' => 'mitel.call',
                'module' => 'Telephony (Mitel)',
                'description' => 'Ability to make outbound calls',
                'order' => 2,
            ],
            [
                'name' => 'Transfer Calls',
                'slug' => 'mitel.transfer',
                'module' => 'Telephony (Mitel)',
                'description' => 'Ability to transfer calls',
                'order' => 3,
            ],
            [
                'name' => 'Access Recordings',
                'slug' => 'mitel.recording',
                'module' => 'Telephony (Mitel)',
                'description' => 'Ability to access call recordings',
                'order' => 4,
            ],
            [
                'name' => 'Manage Queues',
                'slug' => 'mitel.queue',
                'module' => 'Telephony (Mitel)',
                'description' => 'Ability to manage call queues',
                'order' => 5,
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }

        $this->command->info('Permissions seeded successfully!');
    }
}
