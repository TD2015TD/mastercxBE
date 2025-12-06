<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Lookup;

class PriorityLookupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $priorities = [
            [
                'type' => 'priority',
                'code' => 'P1',
                'name_en' => 'P1 - Critical',
                'name_ms' => 'P1 - Kritikal',
                'description_en' => 'Critical priority - requires immediate attention',
                'description_ms' => 'Keutamaan kritikal - memerlukan perhatian segera',
                'sort_order' => 1,
                'is_active' => true,
                'color' => '#dc2626',
                'icon' => 'AlertCircle',
                'metadata' => [
                    'sla_hours' => 4,
                    'escalation_threshold' => 80,
                    'auto_escalate' => true
                ]
            ],
            [
                'type' => 'priority',
                'code' => 'P2',
                'name_en' => 'P2 - High',
                'name_ms' => 'P2 - Tinggi',
                'description_en' => 'High priority - urgent but not critical',
                'description_ms' => 'Keutamaan tinggi - mendesak tetapi tidak kritikal',
                'sort_order' => 2,
                'is_active' => true,
                'color' => '#ea580c',
                'icon' => 'AlertTriangle',
                'metadata' => [
                    'sla_hours' => 8,
                    'escalation_threshold' => 75,
                    'auto_escalate' => true
                ]
            ],
            [
                'type' => 'priority',
                'code' => 'P3',
                'name_en' => 'P3 - Medium',
                'name_ms' => 'P3 - Sederhana',
                'description_en' => 'Medium priority - standard processing time',
                'description_ms' => 'Keutamaan sederhana - masa pemprosesan standard',
                'sort_order' => 3,
                'is_active' => true,
                'color' => '#f59e0b',
                'icon' => 'Info',
                'metadata' => [
                    'sla_hours' => 24,
                    'escalation_threshold' => 70,
                    'auto_escalate' => false
                ]
            ],
            [
                'type' => 'priority',
                'code' => 'P4',
                'name_en' => 'P4 - Low',
                'name_ms' => 'P4 - Rendah',
                'description_en' => 'Low priority - can be addressed with normal workflow',
                'description_ms' => 'Keutamaan rendah - boleh ditangani dengan aliran kerja normal',
                'sort_order' => 4,
                'is_active' => true,
                'color' => '#3b82f6',
                'icon' => 'MinusCircle',
                'metadata' => [
                    'sla_hours' => 48,
                    'escalation_threshold' => 80,
                    'auto_escalate' => false
                ]
            ],
            [
                'type' => 'priority',
                'code' => 'P5',
                'name_en' => 'P5 - Planning',
                'name_ms' => 'P5 - Perancangan',
                'description_en' => 'Planning priority - for future consideration',
                'description_ms' => 'Keutamaan perancangan - untuk pertimbangan masa hadapan',
                'sort_order' => 5,
                'is_active' => true,
                'color' => '#6b7280',
                'icon' => 'Calendar',
                'metadata' => [
                    'sla_hours' => 168,
                    'escalation_threshold' => 90,
                    'auto_escalate' => false
                ]
            ]
        ];

        foreach ($priorities as $priority) {
            Lookup::updateOrCreate(
                [
                    'type' => $priority['type'],
                    'code' => $priority['code']
                ],
                $priority
            );
        }

        $this->command->info('Priority lookups seeded successfully!');
    }
}
