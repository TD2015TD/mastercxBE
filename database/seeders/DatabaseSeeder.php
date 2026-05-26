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
    // Seed roles and permissions first
    $this->call([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
        UserSeeder::class,
    ]);

    // Seed agents and sample data
    $this->call([
        AgentSeeder::class,
        CustomerSeeder::class,
        CategorySeeder::class,
        TicketNumberSequenceSeeder::class,
        TicketSeeder::class,
        EmailTemplateSeeder::class,
        KnowledgeBaseCategorySeeder::class,
    ]);

    // Seed menu items and role menu permissions
    $this->call([
        MenuItemsSeeder::class,
        RoleMenuItemsSeeder::class,
    ]);
}
}
