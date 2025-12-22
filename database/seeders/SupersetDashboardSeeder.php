<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SupersetDashboard;
use App\Models\Role;
use Illuminate\Support\Str;

class SupersetDashboardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seed Superset dashboards based on the SQL reports created in crm-reporting
     * Dashboard IDs are left empty - fill them with actual Superset UUIDs after creation
     */
    public function run(): void
    {
        // Clear existing dashboards (optional - comment out if you want to keep existing data)
        // SupersetDashboard::truncate();

        $dashboards = [
            // ===============================================
            // OPERATIONAL KPIs
            // ===============================================
            [
                'superset_id' => '1', // Agent Performance dashboard (using numeric ID)
                'slug' => 'agent-performance',
                'title_en' => 'KPI - Average Handle Time (AHT)',
                'title_ms' => 'KPI - Purata Masa Pengendalian',
                'description_en' => 'Track agent average handle time including talk time and after-call work. Industry benchmark: 5-6 minutes.',
                'description_ms' => 'Jejaki purata masa pengendalian ejen termasuk masa bercakap dan kerja selepas panggilan. Penanda aras industri: 5-6 minit.',
                'category' => 'Operations',
                'icon' => 'Timer',
                'order' => 1,
                'is_active' => true,
                'is_public' => false,
                'ui_config' => [
                    'hideTitle' => false,
                    'hideTab' => false,
                    'hideChartControls' => false,
                    'filtersExpanded' => true,
                    'filtersVisible' => true,
                ],
                'tags' => 'KPI, AHT, Performance, Operations, Call Center',
                'sql_source' => 'sql/kpi_aht.sql',
                'roles' => ['Manager', 'Supervisor', 'Admin'],
            ],
            [
                'superset_id' => '',
                'slug' => 'kpi-average-speed-of-answer',
                'title_en' => 'KPI - Average Speed of Answer (ASA)',
                'title_ms' => 'KPI - Purata Kelajuan Menjawab',
                'description_en' => 'Monitor queue wait times and service level compliance. Target: Answer 80% of calls within 20 seconds.',
                'description_ms' => 'Pantau masa menunggu dalam baris gilir dan pematuhan tahap perkhidmatan. Sasaran: Jawab 80% panggilan dalam 20 saat.',
                'category' => 'Operations',
                'icon' => 'Phone',
                'order' => 2,
                'is_active' => true,
                'is_public' => false,
                'ui_config' => [
                    'hideTitle' => false,
                    'hideTab' => false,
                    'hideChartControls' => false,
                    'filtersExpanded' => true,
                    'filtersVisible' => true,
                ],
                'tags' => 'KPI, ASA, Service Level, Queue, Call Center',
                'sql_source' => 'sql/kpi_asa.sql',
                'roles' => ['Manager', 'Supervisor', 'Admin'],
            ],
            [
                'superset_id' => '',
                'slug' => 'kpi-after-call-work',
                'title_en' => 'KPI - After Call Work (ACW)',
                'title_ms' => 'KPI - Kerja Selepas Panggilan',
                'description_en' => 'Analyze post-call processing time efficiency. Optimize ACW without compromising quality. Target: 1-2 minutes.',
                'description_ms' => 'Analisis kecekapan masa pemprosesan selepas panggilan. Optimumkan ACW tanpa menjejaskan kualiti.',
                'category' => 'Operations',
                'icon' => 'Edit3',
                'order' => 3,
                'is_active' => true,
                'is_public' => false,
                'ui_config' => [
                    'hideTitle' => false,
                    'hideTab' => false,
                    'hideChartControls' => false,
                    'filtersExpanded' => true,
                    'filtersVisible' => true,
                ],
                'tags' => 'KPI, ACW, Efficiency, Operations, Productivity',
                'sql_source' => 'sql/kpi_acw.sql',
                'roles' => ['Manager', 'Supervisor', 'Admin'],
            ],
            [
                'superset_id' => '',
                'slug' => 'kpi-abandon-rate',
                'title_en' => 'KPI - Call Abandon Rate',
                'title_ms' => 'KPI - Kadar Panggilan Terbiar',
                'description_en' => 'Track call abandonment patterns and reasons. Target: Keep abandon rate below 5%. Critical metric for service quality.',
                'description_ms' => 'Jejaki corak panggilan terbiar dan sebab-sebabnya. Sasaran: Kekalkan kadar terbiar di bawah 5%.',
                'category' => 'Operations',
                'icon' => 'PhoneOff',
                'order' => 4,
                'is_active' => true,
                'is_public' => false,
                'ui_config' => [
                    'hideTitle' => false,
                    'hideTab' => false,
                    'hideChartControls' => false,
                    'filtersExpanded' => true,
                    'filtersVisible' => true,
                ],
                'tags' => 'KPI, Abandon Rate, Queue, Critical, Service Level',
                'sql_source' => 'sql/kpi_abandon_rate.sql',
                'roles' => ['Manager', 'Supervisor', 'Admin'],
            ],
            [
                'superset_id' => '',
                'slug' => 'kpi-short-abandon',
                'title_en' => 'KPI - Short Abandon Analysis',
                'title_ms' => 'KPI - Analisis Panggilan Terbiar Pendek',
                'description_en' => 'Analyze impatient abandons (customers who hang up within 5-10 seconds). Helps identify service vs. impatience issues.',
                'description_ms' => 'Analisis panggilan terbiar tidak sabar (pelanggan menutup dalam 5-10 saat). Membantu kenal pasti isu perkhidmatan vs. ketidaksabaran.',
                'category' => 'Operations',
                'icon' => 'Zap',
                'order' => 5,
                'is_active' => true,
                'is_public' => false,
                'ui_config' => [
                    'hideTitle' => false,
                    'hideTab' => false,
                    'hideChartControls' => false,
                    'filtersExpanded' => true,
                    'filtersVisible' => true,
                ],
                'tags' => 'KPI, Short Abandon, Queue, Analysis, Patterns',
                'sql_source' => 'sql/kpi_short_abandon.sql',
                'roles' => ['Manager', 'Supervisor', 'Admin'],
            ],
            [
                'superset_id' => '',
                'slug' => 'kpi-first-contact-resolution',
                'title_en' => 'KPI - First Contact Resolution (FCR)',
                'title_ms' => 'KPI - Penyelesaian Sentuhan Pertama',
                'description_en' => 'Measure issue resolution on first contact without follow-up. Target: 70-75% FCR rate. Key quality indicator.',
                'description_ms' => 'Ukur penyelesaian isu pada sentuhan pertama tanpa susulan. Sasaran: 70-75% kadar FCR.',
                'category' => 'Quality',
                'icon' => 'CheckCircle',
                'order' => 6,
                'is_active' => true,
                'is_public' => false,
                'ui_config' => [
                    'hideTitle' => false,
                    'hideTab' => false,
                    'hideChartControls' => false,
                    'filtersExpanded' => true,
                    'filtersVisible' => true,
                ],
                'tags' => 'KPI, FCR, Quality, Resolution, Customer Experience',
                'sql_source' => 'sql/kpi_fcr.sql',
                'roles' => ['Manager', 'Supervisor', 'Admin', 'Quality Assurance'],
            ],
            [
                'superset_id' => '',
                'slug' => 'kpi-utilisation',
                'title_en' => 'KPI - Agent Utilisation',
                'title_ms' => 'KPI - Penggunaan Ejen',
                'description_en' => 'Track agent productivity and time usage. Optimal range: 75-85%. Over 85% indicates burnout risk.',
                'description_ms' => 'Jejaki produktiviti ejen dan penggunaan masa. Julat optimum: 75-85%. Melebihi 85% menunjukkan risiko keletihan.',
                'category' => 'Performance',
                'icon' => 'Activity',
                'order' => 7,
                'is_active' => true,
                'is_public' => false,
                'ui_config' => [
                    'hideTitle' => false,
                    'hideTab' => false,
                    'hideChartControls' => false,
                    'filtersExpanded' => true,
                    'filtersVisible' => true,
                ],
                'tags' => 'KPI, Utilisation, Productivity, Performance, Workforce',
                'sql_source' => 'sql/kpi_utilisation.sql',
                'roles' => ['Manager', 'Supervisor', 'Admin', 'HR'],
            ],

            // ===============================================
            // PERFORMANCE REPORTS
            // ===============================================
            [
                'superset_id' => '',
                'slug' => 'agent-performance-comprehensive',
                'title_en' => 'Agent Performance Dashboard',
                'title_ms' => 'Papan Pemuka Prestasi Ejen',
                'description_en' => 'Comprehensive agent metrics combining all KPIs: tickets, calls, quality, and productivity. Complete performance overview.',
                'description_ms' => 'Metrik ejen komprehensif menggabungkan semua KPI: tiket, panggilan, kualiti, dan produktiviti. Gambaran prestasi lengkap.',
                'category' => 'Performance',
                'icon' => 'Users',
                'order' => 10,
                'is_active' => true,
                'is_public' => false,
                'ui_config' => [
                    'hideTitle' => false,
                    'hideTab' => false,
                    'hideChartControls' => false,
                    'filtersExpanded' => true,
                    'filtersVisible' => true,
                ],
                'tags' => 'Performance, Agents, Comprehensive, Management, KPI',
                'sql_source' => 'sql/agent_performance.sql',
                'roles' => ['Manager', 'Supervisor', 'Admin', 'HR'],
            ],
            [
                'superset_id' => '',
                'slug' => 'queue-statistics',
                'title_en' => 'Queue Statistics & Performance',
                'title_ms' => 'Statistik & Prestasi Baris Gilir',
                'description_en' => 'Real-time and historical queue performance metrics including volume, ASA, service level, and abandons. Essential for operations.',
                'description_ms' => 'Metrik prestasi baris gilir masa nyata dan sejarah termasuk volum, ASA, tahap perkhidmatan, dan panggilan terbiar.',
                'category' => 'Operations',
                'icon' => 'List',
                'order' => 11,
                'is_active' => true,
                'is_public' => false,
                'ui_config' => [
                    'hideTitle' => false,
                    'hideTab' => false,
                    'hideChartControls' => false,
                    'filtersExpanded' => true,
                    'filtersVisible' => true,
                ],
                'tags' => 'Queue, Operations, Real-time, Service Level, Call Center',
                'sql_source' => 'sql/queue_stats.sql',
                'roles' => ['Manager', 'Supervisor', 'Admin'],
            ],
            [
                'superset_id' => '',
                'slug' => 'ticket-sla-performance',
                'title_en' => 'Ticket & SLA Performance',
                'title_ms' => 'Prestasi Tiket & SLA',
                'description_en' => 'Ticket lifecycle and SLA compliance tracking. Monitor resolution times, breaches, and customer satisfaction by priority.',
                'description_ms' => 'Penjejakan kitaran hayat tiket dan pematuhan SLA. Pantau masa penyelesaian, pelanggaran, dan kepuasan pelanggan.',
                'category' => 'Quality',
                'icon' => 'AlertCircle',
                'order' => 12,
                'is_active' => true,
                'is_public' => false,
                'ui_config' => [
                    'hideTitle' => false,
                    'hideTab' => false,
                    'hideChartControls' => false,
                    'filtersExpanded' => true,
                    'filtersVisible' => true,
                ],
                'tags' => 'SLA, Tickets, Quality, Compliance, Resolution',
                'sql_source' => 'sql/ticket_sla.sql',
                'roles' => ['Manager', 'Supervisor', 'Admin', 'Quality Assurance'],
            ],
            [
                'superset_id' => '',
                'slug' => 'customer-satisfaction-csat',
                'title_en' => 'Customer Satisfaction (CSAT)',
                'title_ms' => 'Kepuasan Pelanggan (CSAT)',
                'description_en' => 'Customer satisfaction analysis with NPS, rating distribution, and correlation with SLA/FCR metrics. Track customer sentiment.',
                'description_ms' => 'Analisis kepuasan pelanggan dengan NPS, taburan penilaian, dan korelasi dengan metrik SLA/FCR. Jejak sentimen pelanggan.',
                'category' => 'Quality',
                'icon' => 'Heart',
                'order' => 13,
                'is_active' => true,
                'is_public' => false,
                'ui_config' => [
                    'hideTitle' => false,
                    'hideTab' => false,
                    'hideChartControls' => false,
                    'filtersExpanded' => true,
                    'filtersVisible' => true,
                ],
                'tags' => 'CSAT, NPS, Customer Satisfaction, Quality, Feedback',
                'sql_source' => 'sql/csat_metrics.sql',
                'roles' => ['Manager', 'Supervisor', 'Admin', 'Quality Assurance', 'Executive'],
            ],

            // ===============================================
            // SELF-SERVICE REPORTS
            // ===============================================
            [
                'superset_id' => '',
                'slug' => 'agent-personal-kpi',
                'title_en' => 'My Personal KPIs',
                'title_ms' => 'KPI Peribadi Saya',
                'description_en' => 'Personal performance dashboard with Row Level Security (RLS). View your individual KPIs, targets, quality metrics, and improvement areas.',
                'description_ms' => 'Papan pemuka prestasi peribadi dengan Row Level Security (RLS). Lihat KPI, sasaran, metrik kualiti, dan bidang penambahbaikan individu anda.',
                'category' => 'Self-Service',
                'icon' => 'User',
                'order' => 20,
                'is_active' => true,
                'is_public' => false,
                'ui_config' => [
                    'hideTitle' => false,
                    'hideTab' => false,
                    'hideChartControls' => false,
                    'filtersExpanded' => false,
                    'filtersVisible' => false,
                ],
                'tags' => 'Personal, KPI, Self-Service, Agent, Performance',
                'sql_source' => 'sql/agent_personal_kpi.sql',
                'rls_config' => [
                    [
                        'dataset' => 1, // Will be configured in Superset
                        'clause' => "agent_id = {{ current_user().extra.get('agent_id') }}",
                    ],
                ],
                'roles' => ['Agent', 'Supervisor', 'Manager'], // Agents can see their own data
            ],

            // ===============================================
            // EXECUTIVE DASHBOARDS
            // ===============================================
            [
                'superset_id' => '',
                'slug' => 'executive-summary',
                'title_en' => 'Executive Summary Dashboard',
                'title_ms' => 'Papan Pemuka Ringkasan Eksekutif',
                'description_en' => 'High-level overview of key metrics: SLA compliance, CSAT, FCR, ticket volumes, and agent performance. Strategic decision-making dashboard.',
                'description_ms' => 'Gambaran ringkas metrik utama: pematuhan SLA, CSAT, FCR, volum tiket, dan prestasi ejen. Papan pemuka membuat keputusan strategik.',
                'category' => 'Executive',
                'icon' => 'TrendingUp',
                'order' => 30,
                'is_active' => true,
                'is_public' => false,
                'ui_config' => [
                    'hideTitle' => false,
                    'hideTab' => false,
                    'hideChartControls' => true,
                    'filtersExpanded' => false,
                    'filtersVisible' => true,
                ],
                'tags' => 'Executive, Summary, High-level, KPI, Strategic',
                'sql_source' => 'Combined: agent_performance, ticket_sla, csat_metrics, queue_stats',
                'roles' => ['Executive', 'Admin', 'Manager'],
            ],
        ];

        // Create dashboards and assign roles
        foreach ($dashboards as $dashboardData) {
            $roles = $dashboardData['roles'] ?? [];
            $sqlSource = $dashboardData['sql_source'] ?? null;
            unset($dashboardData['roles'], $dashboardData['sql_source']);

            // Create dashboard
            $dashboard = SupersetDashboard::create($dashboardData);

            // Assign roles with permissions based on role type
            foreach ($roles as $roleName) {
                $role = Role::where('name', $roleName)->first();

                if ($role) {
                    // Define permissions based on role
                    $permissions = [
                        'can_view' => true,
                        'can_export' => in_array($roleName, ['Manager', 'Admin', 'Executive', 'HR']),
                        'can_share' => in_array($roleName, ['Admin', 'Executive']),
                        'custom_rls' => null,
                    ];

                    // Special RLS for agent personal dashboard
                    if ($dashboard->slug === 'agent-personal-kpi' && $roleName === 'Agent') {
                        $permissions['custom_rls'] = json_encode($dashboard->rls_config);
                    }

                    $dashboard->roles()->attach($role->id, $permissions);
                }
            }

            $this->command->info("✓ Created: {$dashboard->title_en} (SQL: {$sqlSource})");
        }

        $this->command->info('');
        $this->command->info('════════════════════════════════════════════════════');
        $this->command->info('  Superset Dashboard Seeding Completed Successfully!');
        $this->command->info('════════════════════════════════════════════════════');
        $this->command->warn('');
        $this->command->warn('⚠ IMPORTANT: Dashboard IDs are empty!');
        $this->command->warn('');
        $this->command->warn('Next Steps:');
        $this->command->warn('1. Create dashboards in Superset using SQL files from:');
        $this->command->warn('   /Users/mohdmarzuqikrammohdhelmi/Workspace/jkdm-crm/crm-reporting/sql/');
        $this->command->warn('');
        $this->command->warn('2. For each dashboard, copy its UUID from Superset');
        $this->command->warn('   (found in dashboard URL or API response)');
        $this->command->warn('');
        $this->command->warn('3. Update superset_id in database:');
        $this->command->warn('   UPDATE superset_dashboards');
        $this->command->warn('   SET superset_id = \'your-uuid-here\'');
        $this->command->warn('   WHERE slug = \'dashboard-slug\';');
        $this->command->warn('');
        $this->command->info('════════════════════════════════════════════════════');
    }
}
