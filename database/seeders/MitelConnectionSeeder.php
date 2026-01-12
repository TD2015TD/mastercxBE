<?php

namespace Database\Seeders;

use App\Models\MitelConnection;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MitelConnectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MitelConnection::create([
            'name' => 'Primary CCMData',
            'description' => 'Main Mitel CCMData database connection',
            'host' => '10.0.0.84',
            'port' => 1433,
            'database' => 'CCMData',
            'username' => 'your_username', // Update this
            'password' => 'your_password', // Update this - will be encrypted
            'encrypt' => true,
            'trust_server_certificate' => true,
            'is_active' => true,
        ]);
    }
}
