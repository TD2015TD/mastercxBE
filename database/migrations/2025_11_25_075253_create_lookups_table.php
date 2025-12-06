<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create lookups table for all enum values with bilingual support
        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // e.g., 'ticket_status', 'ticket_priority', 'urgency', 'impact', 'channel', etc.
            $table->string('code'); // e.g., 'P1', 'open', 'high', 'critical'
            $table->string('name_en'); // English label
            $table->string('name_ms'); // Malay label
            $table->text('description_en')->nullable(); // English description
            $table->text('description_ms')->nullable(); // Malay description
            $table->integer('sort_order')->default(0); // For ordering in dropdowns
            $table->boolean('is_active')->default(true);
            $table->string('color')->nullable(); // For UI (e.g., 'red', 'green', '#FF0000')
            $table->string('icon')->nullable(); // For UI (e.g., 'warning', 'check')
            $table->json('metadata')->nullable(); // For additional type-specific data
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['type', 'code']);
            $table->index(['type', 'is_active']);
            $table->index('sort_order');
        });

        // Seed initial lookup data
        $this->seedLookupData();
    }

    /**
     * Seed initial lookup data.
     */
    private function seedLookupData(): void
    {
        $lookups = [
            // Ticket Status
            ['type' => 'ticket_status', 'code' => 'Open', 'name_en' => 'Open', 'name_ms' => 'Buka', 'color' => 'blue', 'icon' => 'folder-open', 'sort_order' => 1],
            ['type' => 'ticket_status', 'code' => 'In Progress', 'name_en' => 'In Progress', 'name_ms' => 'Sedang Diproses', 'color' => 'purple', 'icon' => 'clock', 'sort_order' => 2],
            ['type' => 'ticket_status', 'code' => 'Pending', 'name_en' => 'Pending', 'name_ms' => 'Menunggu', 'color' => 'yellow', 'icon' => 'pause', 'sort_order' => 3],
            ['type' => 'ticket_status', 'code' => 'On Hold', 'name_en' => 'On Hold', 'name_ms' => 'Ditahan', 'color' => 'orange', 'icon' => 'hand', 'sort_order' => 4],
            ['type' => 'ticket_status', 'code' => 'Resolved', 'name_en' => 'Resolved', 'name_ms' => 'Diselesaikan', 'color' => 'green', 'icon' => 'check', 'sort_order' => 5],
            ['type' => 'ticket_status', 'code' => 'Closed', 'name_en' => 'Closed', 'name_ms' => 'Ditutup', 'color' => 'gray', 'icon' => 'check-circle', 'sort_order' => 6],
            ['type' => 'ticket_status', 'code' => 'Reopened', 'name_en' => 'Reopened', 'name_ms' => 'Dibuka Semula', 'color' => 'red', 'icon' => 'refresh', 'sort_order' => 7],

            // Ticket Priority
            ['type' => 'ticket_priority', 'code' => 'P1', 'name_en' => 'Critical (P1)', 'name_ms' => 'Kritikal (P1)', 'description_en' => 'Urgent, business-critical issues', 'description_ms' => 'Isu kritikal perniagaan yang mendesak', 'color' => 'red', 'icon' => 'alert-circle', 'metadata' => json_encode(['hours' => 4]), 'sort_order' => 1],
            ['type' => 'ticket_priority', 'code' => 'P2', 'name_en' => 'High (P2)', 'name_ms' => 'Tinggi (P2)', 'description_en' => 'High priority issues', 'description_ms' => 'Isu keutamaan tinggi', 'color' => 'orange', 'icon' => 'alert-triangle', 'metadata' => json_encode(['hours' => 8]), 'sort_order' => 2],
            ['type' => 'ticket_priority', 'code' => 'P3', 'name_en' => 'Medium (P3)', 'name_ms' => 'Sederhana (P3)', 'description_en' => 'Medium priority issues', 'description_ms' => 'Isu keutamaan sederhana', 'color' => 'yellow', 'icon' => 'info', 'metadata' => json_encode(['hours' => 24]), 'sort_order' => 3],
            ['type' => 'ticket_priority', 'code' => 'P4', 'name_en' => 'Low (P4)', 'name_ms' => 'Rendah (P4)', 'description_en' => 'Low priority issues', 'description_ms' => 'Isu keutamaan rendah', 'color' => 'blue', 'icon' => 'message-circle', 'metadata' => json_encode(['hours' => 72]), 'sort_order' => 4],

            // Urgency Levels
            ['type' => 'urgency', 'code' => 'Critical', 'name_en' => 'Critical', 'name_ms' => 'Kritikal', 'description_en' => 'Requires immediate attention', 'description_ms' => 'Memerlukan perhatian segera', 'color' => 'red', 'sort_order' => 1],
            ['type' => 'urgency', 'code' => 'High', 'name_en' => 'High', 'name_ms' => 'Tinggi', 'description_en' => 'Needs quick response', 'description_ms' => 'Memerlukan respons cepat', 'color' => 'orange', 'sort_order' => 2],
            ['type' => 'urgency', 'code' => 'Medium', 'name_en' => 'Medium', 'name_ms' => 'Sederhana', 'description_en' => 'Standard response time', 'description_ms' => 'Masa respons standard', 'color' => 'yellow', 'sort_order' => 3],
            ['type' => 'urgency', 'code' => 'Low', 'name_en' => 'Low', 'name_ms' => 'Rendah', 'description_en' => 'Can be addressed later', 'description_ms' => 'Boleh ditangani kemudian', 'color' => 'blue', 'sort_order' => 4],

            // Impact Levels
            ['type' => 'impact', 'code' => 'Critical', 'name_en' => 'Critical', 'name_ms' => 'Kritikal', 'description_en' => 'Affects multiple users/departments', 'description_ms' => 'Memberi kesan kepada ramai pengguna/jabatan', 'color' => 'red', 'sort_order' => 1],
            ['type' => 'impact', 'code' => 'High', 'name_en' => 'High', 'name_ms' => 'Tinggi', 'description_en' => 'Significant impact on operations', 'description_ms' => 'Kesan besar pada operasi', 'color' => 'orange', 'sort_order' => 2],
            ['type' => 'impact', 'code' => 'Medium', 'name_en' => 'Medium', 'name_ms' => 'Sederhana', 'description_en' => 'Moderate impact', 'description_ms' => 'Kesan sederhana', 'color' => 'yellow', 'sort_order' => 3],
            ['type' => 'impact', 'code' => 'Low', 'name_en' => 'Low', 'name_ms' => 'Rendah', 'description_en' => 'Minimal impact', 'description_ms' => 'Kesan minimum', 'color' => 'blue', 'sort_order' => 4],

            // Communication Channels
            ['type' => 'channel', 'code' => 'phone', 'name_en' => 'Phone', 'name_ms' => 'Telefon', 'icon' => 'phone', 'sort_order' => 1],
            ['type' => 'channel', 'code' => 'email', 'name_en' => 'Email', 'name_ms' => 'E-mel', 'icon' => 'mail', 'sort_order' => 2],
            ['type' => 'channel', 'code' => 'chat', 'name_en' => 'Live Chat', 'name_ms' => 'Sembang Langsung', 'icon' => 'message-square', 'sort_order' => 3],
            ['type' => 'channel', 'code' => 'web', 'name_en' => 'Web Portal', 'name_ms' => 'Portal Web', 'icon' => 'globe', 'sort_order' => 4],
            ['type' => 'channel', 'code' => 'walk-in', 'name_en' => 'Walk-in', 'name_ms' => 'Kunjungan', 'icon' => 'user', 'sort_order' => 5],
            ['type' => 'channel', 'code' => 'social_media', 'name_en' => 'Social Media', 'name_ms' => 'Media Sosial', 'icon' => 'share-2', 'sort_order' => 6],

            // Escalation Levels
            ['type' => 'escalation_level', 'code' => 'L1', 'name_en' => 'Level 1 Support', 'name_ms' => 'Sokongan Tahap 1', 'description_en' => 'First line support', 'description_ms' => 'Sokongan barisan hadapan', 'color' => 'blue', 'sort_order' => 1],
            ['type' => 'escalation_level', 'code' => 'L2', 'name_en' => 'Level 2 Support', 'name_ms' => 'Sokongan Tahap 2', 'description_en' => 'Technical specialists', 'description_ms' => 'Pakar teknikal', 'color' => 'yellow', 'sort_order' => 2],
            ['type' => 'escalation_level', 'code' => 'Supervisor', 'name_en' => 'Supervisor', 'name_ms' => 'Penyelia', 'description_en' => 'Team supervisor', 'description_ms' => 'Penyelia pasukan', 'color' => 'orange', 'sort_order' => 3],
            ['type' => 'escalation_level', 'code' => 'Manager', 'name_en' => 'Manager', 'name_ms' => 'Pengurus', 'description_en' => 'Department manager', 'description_ms' => 'Pengurus jabatan', 'color' => 'red', 'sort_order' => 4],

            // Escalation Type
            ['type' => 'escalation_type', 'code' => 'manual', 'name_en' => 'Manual Escalation', 'name_ms' => 'Peningkatan Manual', 'description_en' => 'Agent manually escalated the ticket', 'description_ms' => 'Ejen meningkatkan tiket secara manual', 'color' => 'blue', 'sort_order' => 1],
            ['type' => 'escalation_type', 'code' => 'auto', 'name_en' => 'Auto Escalation', 'name_ms' => 'Peningkatan Auto', 'description_en' => 'System automatically escalated', 'description_ms' => 'Sistem meningkatkan secara automatik', 'color' => 'orange', 'sort_order' => 2],
            ['type' => 'escalation_type', 'code' => 'sla_risk', 'name_en' => 'SLA At Risk', 'name_ms' => 'SLA Berisiko', 'description_en' => 'Escalated due to SLA breach risk', 'description_ms' => 'Ditingkatkan kerana risiko pelanggaran SLA', 'color' => 'red', 'sort_order' => 3],

            // Escalation Status
            ['type' => 'escalation_status', 'code' => 'pending', 'name_en' => 'Pending', 'name_ms' => 'Menunggu', 'description_en' => 'Awaiting acknowledgment', 'description_ms' => 'Menunggu pengakuan', 'color' => 'yellow', 'sort_order' => 1],
            ['type' => 'escalation_status', 'code' => 'acknowledged', 'name_en' => 'Acknowledged', 'name_ms' => 'Diakui', 'description_en' => 'Escalation acknowledged', 'description_ms' => 'Peningkatan telah diakui', 'color' => 'blue', 'sort_order' => 2],
            ['type' => 'escalation_status', 'code' => 'completed', 'name_en' => 'Completed', 'name_ms' => 'Selesai', 'description_en' => 'Escalation resolved', 'description_ms' => 'Peningkatan diselesaikan', 'color' => 'green', 'sort_order' => 3],

            // Customer Interaction Types
            ['type' => 'interaction_type', 'code' => 'call', 'name_en' => 'Phone Call', 'name_ms' => 'Panggilan Telefon', 'icon' => 'phone', 'sort_order' => 1],
            ['type' => 'interaction_type', 'code' => 'email', 'name_en' => 'Email', 'name_ms' => 'E-mel', 'icon' => 'mail', 'sort_order' => 2],
            ['type' => 'interaction_type', 'code' => 'chat', 'name_en' => 'Chat', 'name_ms' => 'Sembang', 'icon' => 'message-square', 'sort_order' => 3],
            ['type' => 'interaction_type', 'code' => 'sms', 'name_en' => 'SMS', 'name_ms' => 'SMS', 'icon' => 'smartphone', 'sort_order' => 4],
            ['type' => 'interaction_type', 'code' => 'meeting', 'name_en' => 'Meeting', 'name_ms' => 'Mesyuarat', 'icon' => 'users', 'sort_order' => 5],
            ['type' => 'interaction_type', 'code' => 'note', 'name_en' => 'Note', 'name_ms' => 'Nota', 'icon' => 'file-text', 'sort_order' => 6],

            // Sentiment
            ['type' => 'sentiment', 'code' => 'positive', 'name_en' => 'Positive', 'name_ms' => 'Positif', 'color' => 'green', 'icon' => 'smile', 'sort_order' => 1],
            ['type' => 'sentiment', 'code' => 'neutral', 'name_en' => 'Neutral', 'name_ms' => 'Neutral', 'color' => 'gray', 'icon' => 'meh', 'sort_order' => 2],
            ['type' => 'sentiment', 'code' => 'negative', 'name_en' => 'Negative', 'name_ms' => 'Negatif', 'color' => 'red', 'icon' => 'frown', 'sort_order' => 3],

            // Email Integration Status
            ['type' => 'email_status', 'code' => 'pending', 'name_en' => 'Pending', 'name_ms' => 'Menunggu', 'description_en' => 'Email received, awaiting processing', 'description_ms' => 'E-mel diterima, menunggu pemprosesan', 'color' => 'yellow', 'sort_order' => 1],
            ['type' => 'email_status', 'code' => 'processed', 'name_en' => 'Processed', 'name_ms' => 'Diproses', 'description_en' => 'Email successfully processed', 'description_ms' => 'E-mel berjaya diproses', 'color' => 'green', 'sort_order' => 2],
            ['type' => 'email_status', 'code' => 'failed', 'name_en' => 'Failed', 'name_ms' => 'Gagal', 'description_en' => 'Email processing failed', 'description_ms' => 'Pemprosesan e-mel gagal', 'color' => 'red', 'sort_order' => 3],

            // Report Types
            ['type' => 'report_type', 'code' => 'agent_performance', 'name_en' => 'Agent Performance Report', 'name_ms' => 'Laporan Prestasi Ejen', 'description_en' => 'Individual agent KPIs and metrics', 'description_ms' => 'KPI dan metrik ejen individu', 'icon' => 'user-check', 'sort_order' => 1],
            ['type' => 'report_type', 'code' => 'supervisor_team', 'name_en' => 'Team Performance Report', 'name_ms' => 'Laporan Prestasi Pasukan', 'description_en' => 'Team-wide metrics and comparisons', 'description_ms' => 'Metrik dan perbandingan pasukan', 'icon' => 'users', 'sort_order' => 2],
            ['type' => 'report_type', 'code' => 'sla_compliance', 'name_en' => 'SLA Compliance Report', 'name_ms' => 'Laporan Pematuhan SLA', 'description_en' => 'SLA breach analysis', 'description_ms' => 'Analisis pelanggaran SLA', 'icon' => 'clock', 'sort_order' => 3],
            ['type' => 'report_type', 'code' => 'customer_satisfaction', 'name_en' => 'Customer Satisfaction Report', 'name_ms' => 'Laporan Kepuasan Pelanggan', 'description_en' => 'CSAT scores and trends', 'description_ms' => 'Skor dan trend CSAT', 'icon' => 'star', 'sort_order' => 4],
            ['type' => 'report_type', 'code' => 'ticket_volume', 'name_en' => 'Ticket Volume Report', 'name_ms' => 'Laporan Jumlah Tiket', 'description_en' => 'Ticket volume analysis over time', 'description_ms' => 'Analisis jumlah tiket mengikut masa', 'icon' => 'bar-chart', 'sort_order' => 5],

            // Report Status
            ['type' => 'report_status', 'code' => 'pending', 'name_en' => 'Pending', 'name_ms' => 'Menunggu', 'color' => 'yellow', 'sort_order' => 1],
            ['type' => 'report_status', 'code' => 'processing', 'name_en' => 'Processing', 'name_ms' => 'Sedang Diproses', 'color' => 'blue', 'sort_order' => 2],
            ['type' => 'report_status', 'code' => 'completed', 'name_en' => 'Completed', 'name_ms' => 'Selesai', 'color' => 'green', 'sort_order' => 3],
            ['type' => 'report_status', 'code' => 'failed', 'name_en' => 'Failed', 'name_ms' => 'Gagal', 'color' => 'red', 'sort_order' => 4],

            // Agent Status
            ['type' => 'agent_status', 'code' => 'available', 'name_en' => 'Available', 'name_ms' => 'Tersedia', 'color' => 'green', 'icon' => 'check-circle', 'sort_order' => 1],
            ['type' => 'agent_status', 'code' => 'busy', 'name_en' => 'Busy', 'name_ms' => 'Sibuk', 'color' => 'red', 'icon' => 'phone', 'sort_order' => 2],
            ['type' => 'agent_status', 'code' => 'away', 'name_en' => 'Away', 'name_ms' => 'Tiada', 'color' => 'yellow', 'icon' => 'clock', 'sort_order' => 3],
            ['type' => 'agent_status', 'code' => 'offline', 'name_en' => 'Offline', 'name_ms' => 'Luar Talian', 'color' => 'gray', 'icon' => 'x-circle', 'sort_order' => 4],

            // Departments (JKDM specific)
            ['type' => 'department', 'code' => 'SALES TAX', 'name_en' => 'Sales Tax', 'name_ms' => 'Cukai Jualan', 'description_en' => 'Sales tax related inquiries', 'description_ms' => 'Pertanyaan berkaitan cukai jualan', 'sort_order' => 1],
            ['type' => 'department', 'code' => 'SERVICE TAX', 'name_en' => 'Service Tax', 'name_ms' => 'Cukai Perkhidmatan', 'description_en' => 'Service tax related inquiries', 'description_ms' => 'Pertanyaan berkaitan cukai perkhidmatan', 'sort_order' => 2],
            ['type' => 'department', 'code' => 'IMP/EXP & INDUSTRIES', 'name_en' => 'Import/Export & Industries', 'name_ms' => 'Import/Eksport & Industri', 'description_en' => 'Import, export and industrial matters', 'description_ms' => 'Perkara import, eksport dan perindustrian', 'sort_order' => 3],
            ['type' => 'department', 'code' => 'GST', 'name_en' => 'GST', 'name_ms' => 'GST', 'description_en' => 'Goods and Services Tax matters', 'description_ms' => 'Perkara Cukai Barangan dan Perkhidmatan', 'sort_order' => 4],
            ['type' => 'department', 'code' => 'EXCISE', 'name_en' => 'Excise', 'name_ms' => 'Eksais', 'description_en' => 'Excise duty related matters', 'description_ms' => 'Perkara berkaitan duti eksais', 'sort_order' => 5],
            ['type' => 'department', 'code' => 'LEVY', 'name_en' => 'Levy', 'name_ms' => 'Levi', 'description_en' => 'Levy related matters', 'description_ms' => 'Perkara berkaitan levi', 'sort_order' => 6],
            ['type' => 'department', 'code' => 'PASSENGER LEVY', 'name_en' => 'Passenger Levy', 'name_ms' => 'Levi Penumpang', 'description_en' => 'Passenger levy matters', 'description_ms' => 'Perkara levi penumpang', 'sort_order' => 7],
            ['type' => 'department', 'code' => 'TTX', 'name_en' => 'Tourism Tax', 'name_ms' => 'Cukai Pelancongan', 'description_en' => 'Tourism tax related matters', 'description_ms' => 'Perkara berkaitan cukai pelancongan', 'sort_order' => 8],
            ['type' => 'department', 'code' => 'TECHNICAL DIVISION', 'name_en' => 'Technical Division', 'name_ms' => 'Bahagian Teknikal', 'description_en' => 'Technical and classification matters', 'description_ms' => 'Perkara teknikal dan klasifikasi', 'sort_order' => 9],
            ['type' => 'department', 'code' => 'PREVENTIVE', 'name_en' => 'Preventive (Enforcement)', 'name_ms' => 'Pencegahan (Penguatkuasaan)', 'description_en' => 'Enforcement and preventive matters', 'description_ms' => 'Perkara penguatkuasaan dan pencegahan', 'sort_order' => 10],
            ['type' => 'department', 'code' => 'GENERAL', 'name_en' => 'General', 'name_ms' => 'Am', 'description_en' => 'General inquiries and administrative matters', 'description_ms' => 'Pertanyaan am dan perkara pentadbiran', 'sort_order' => 11],
            ['type' => 'department', 'code' => 'OTHERS', 'name_en' => 'Others', 'name_ms' => 'Lain-lain', 'description_en' => 'Other inquiries', 'description_ms' => 'Pertanyaan lain', 'sort_order' => 12],

            // ID Types (Malaysian identification documents)
            ['type' => 'id_type', 'code' => 'MyKad', 'name_en' => 'MyKad', 'name_ms' => 'MyKad', 'description_en' => 'Malaysian Identity Card', 'description_ms' => 'Kad Pengenalan Malaysia', 'sort_order' => 1],
            ['type' => 'id_type', 'code' => 'MyTentera', 'name_en' => 'MyTentera', 'name_ms' => 'MyTentera', 'description_en' => 'Malaysian Armed Forces Identity Card', 'description_ms' => 'Kad Pengenalan Angkatan Tentera Malaysia', 'sort_order' => 2],
            ['type' => 'id_type', 'code' => 'MyPolis', 'name_en' => 'MyPolis', 'name_ms' => 'MyPolis', 'description_en' => 'Malaysian Police Identity Card', 'description_ms' => 'Kad Pengenalan Polis Diraja Malaysia', 'sort_order' => 3],
            ['type' => 'id_type', 'code' => 'MyPR', 'name_en' => 'MyPR', 'name_ms' => 'MyPR', 'description_en' => 'Malaysian Permanent Resident Card', 'description_ms' => 'Kad Penduduk Tetap Malaysia', 'sort_order' => 4],
            ['type' => 'id_type', 'code' => 'Passport', 'name_en' => 'Passport', 'name_ms' => 'Pasport', 'description_en' => 'Passport', 'description_ms' => 'Pasport', 'sort_order' => 5],
        ];

        foreach ($lookups as $lookup) {
            $lookup['created_at'] = now();
            $lookup['updated_at'] = now();
            DB::table('lookups')->insert($lookup);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lookups');
    }
};
