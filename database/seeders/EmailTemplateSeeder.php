<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'code' => 'CITIZEN_OTP',
                'name' => 'Citizen Portal OTP Email',
                'subject' => 'Kod OTP untuk Portal Khidmat Rakyat',
                'body_html' => $this->getCitizenOtpHtml(),
                'body_text' => $this->getCitizenOtpText(),
                'available_variables' => json_encode([
                    'otp_code' => 'The 6-digit OTP code',
                    'phone_number' => 'User phone number',
                ]),
                'category' => 'otp',
                'is_active' => true,
            ],
            [
                'code' => 'TICKET_CREATED',
                'name' => 'Ticket Created Confirmation',
                'subject' => 'Aduan Anda Telah Diterima - {{ ticket_number }}',
                'body_html' => $this->getTicketCreatedHtml(),
                'body_text' => $this->getTicketCreatedText(),
                'available_variables' => json_encode([
                    'customer_name' => 'Customer name',
                    'ticket_number' => 'Ticket number',
                    'subject' => 'Ticket subject',
                    'department' => 'Department name',
                    'priority' => 'Priority level',
                    'created_at' => 'Creation date',
                ]),
                'category' => 'notification',
                'is_active' => true,
            ],
            [
                'code' => 'TICKET_STATUS_UPDATE',
                'name' => 'Ticket Status Update',
                'subject' => 'Status Aduan Anda Telah Dikemaskini - {{ ticket_number }}',
                'body_html' => $this->getTicketStatusUpdateHtml(),
                'body_text' => $this->getTicketStatusUpdateText(),
                'available_variables' => json_encode([
                    'customer_name' => 'Customer name',
                    'ticket_number' => 'Ticket number',
                    'subject' => 'Ticket subject',
                    'old_status' => 'Previous status',
                    'new_status' => 'New status',
                    'updated_at' => 'Update date',
                    'notes' => 'Status update notes',
                ]),
                'category' => 'notification',
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            DB::table('email_templates')->updateOrInsert(
                ['code' => $template['code']],
                array_merge($template, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('Email templates seeded successfully!');
    }

    private function getCitizenOtpHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #2563eb;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .otp-code {
            background-color: #dbeafe;
            color: #1e40af;
            font-size: 32px;
            font-weight: bold;
            padding: 20px;
            text-align: center;
            letter-spacing: 8px;
            border-radius: 8px;
            margin: 20px 0;
            border: 2px dashed #3b82f6;
        }
        .footer {
            background-color: #f3f4f6;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-radius: 0 0 8px 8px;
        }
        .warning {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔐 Portal Khidmat Rakyat</h1>
    </div>
    
    <div class="content">
        <p>Assalamualaikum / Salam Sejahtera,</p>
        
        <p>Anda telah meminta kod OTP untuk log masuk ke <strong>Portal Khidmat Rakyat</strong> menggunakan nombor telefon <strong>{{ phone_number }}</strong>.</p>
        
        <p style="text-align: center; margin: 25px 0 10px;">Kod OTP anda ialah:</p>
        
        <div class="otp-code">{{ otp_code }}</div>
        
        <div class="warning">
            <p style="margin: 0;"><strong>⚠️ Penting:</strong></p>
            <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                <li>Kod ini sah untuk <strong>10 minit sahaja</strong></li>
                <li>Jangan kongsikan kod ini dengan sesiapa</li>
                <li>Jika anda tidak membuat permintaan ini, sila abaikan email ini</li>
            </ul>
        </div>
        
        <p>Selepas memasukkan kod OTP, anda akan dapat melihat semua aduan yang berkaitan dengan nombor telefon anda.</p>
        
        <p style="margin-top: 30px;">Terima kasih,<br>
        <strong>Jabatan Khidmat Digital Malaysia</strong></p>
    </div>
    
    <div class="footer">
        <p>Email automatik ini dihantar dari sistem Portal Khidmat Rakyat.</p>
        <p>Sila jangan membalas email ini.</p>
    </div>
</body>
</html>
HTML;
    }

    private function getCitizenOtpText(): string
    {
        return <<<'TEXT'
Assalamualaikum / Salam Sejahtera,

Anda telah meminta kod OTP untuk log masuk ke Portal Khidmat Rakyat menggunakan nombor telefon {{ phone_number }}.

KOD OTP ANDA: {{ otp_code }}

PENTING:
- Kod ini sah untuk 10 minit sahaja
- Jangan kongsikan kod ini dengan sesiapa
- Jika anda tidak membuat permintaan ini, sila abaikan email ini

Selepas memasukkan kod OTP, anda akan dapat melihat semua aduan yang berkaitan dengan nombor telefon anda.

Terima kasih,
Jabatan Khidmat Digital Malaysia

---
Email automatik ini dihantar dari sistem Portal Khidmat Rakyat.
Sila jangan membalas email ini.
TEXT;
    }

    private function getTicketCreatedHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #10b981;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .ticket-info {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #d1d5db;
        }
        .ticket-number {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            text-align: center;
            padding: 10px;
            background-color: #dbeafe;
            border-radius: 6px;
            margin: 15px 0;
        }
        .info-row {
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .info-label {
            font-weight: bold;
            color: #6b7280;
        }
        .footer {
            background-color: #f3f4f6;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-radius: 0 0 8px 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>✅ Aduan Anda Telah Diterima</h1>
    </div>
    
    <div class="content">
        <p>Assalamualaikum / Salam Sejahtera <strong>{{ customer_name }}</strong>,</p>
        
        <p>Terima kasih kerana menghubungi kami. Aduan anda telah berjaya diterima dan sedang diproses.</p>
        
        <div class="ticket-number">{{ ticket_number }}</div>
        
        <div class="ticket-info">
            <div class="info-row">
                <span class="info-label">Tajuk:</span> {{ subject }}
            </div>
            <div class="info-row">
                <span class="info-label">Jabatan:</span> {{ department }}
            </div>
            <div class="info-row">
                <span class="info-label">Keutamaan:</span> {{ priority }}
            </div>
            <div class="info-row">
                <span class="info-label">Tarikh:</span> {{ created_at }}
            </div>
        </div>
        
        <p>Sila simpan nombor aduan di atas untuk rujukan anda. Anda boleh menggunakan nombor ini untuk menjejak status aduan anda di Portal Khidmat Rakyat.</p>
        
        <p>Pasukan kami akan menghubungi anda secepat mungkin.</p>
        
        <p style="margin-top: 30px;">Terima kasih,<br>
        <strong>Jabatan Khidmat Digital Malaysia</strong></p>
    </div>
    
    <div class="footer">
        <p>Email automatik ini dihantar dari sistem Portal Khidmat Rakyat.</p>
        <p>Untuk pertanyaan, sila log masuk ke portal atau hubungi kami.</p>
    </div>
</body>
</html>
HTML;
    }

    private function getTicketCreatedText(): string
    {
        return <<<'TEXT'
Assalamualaikum / Salam Sejahtera {{ customer_name }},

Terima kasih kerana menghubungi kami. Aduan anda telah berjaya diterima dan sedang diproses.

NOMBOR ADUAN: {{ ticket_number }}

MAKLUMAT ADUAN:
- Tajuk: {{ subject }}
- Jabatan: {{ department }}
- Keutamaan: {{ priority }}
- Tarikh: {{ created_at }}

Sila simpan nombor aduan di atas untuk rujukan anda. Anda boleh menggunakan nombor ini untuk menjejak status aduan anda di Portal Khidmat Rakyat.

Pasukan kami akan menghubungi anda secepat mungkin.

Terima kasih,
Jabatan Khidmat Digital Malaysia

---
Email automatik ini dihantar dari sistem Portal Khidmat Rakyat.
Untuk pertanyaan, sila log masuk ke portal atau hubungi kami.
TEXT;
    }

    private function getTicketStatusUpdateHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f59e0b;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .ticket-number {
            font-size: 20px;
            font-weight: bold;
            color: #2563eb;
            text-align: center;
            padding: 10px;
            background-color: #dbeafe;
            border-radius: 6px;
            margin: 15px 0;
        }
        .status-change {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
            border: 1px solid #d1d5db;
        }
        .status-box {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: bold;
            margin: 0 10px;
        }
        .arrow {
            font-size: 24px;
            color: #6b7280;
            margin: 0 10px;
        }
        .notes-box {
            background-color: #fef3c7;
            padding: 15px;
            border-left: 4px solid #f59e0b;
            margin: 20px 0;
        }
        .footer {
            background-color: #f3f4f6;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-radius: 0 0 8px 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📢 Status Aduan Dikemaskini</h1>
    </div>
    
    <div class="content">
        <p>Assalamualaikum / Salam Sejahtera <strong>{{ customer_name }}</strong>,</p>
        
        <p>Status aduan anda telah dikemaskini.</p>
        
        <div class="ticket-number">{{ ticket_number }}</div>
        
        <p><strong>Tajuk:</strong> {{ subject }}</p>
        
        <div class="status-change">
            <p style="margin-bottom: 15px;">Perubahan Status:</p>
            <div>
                <span class="status-box" style="background-color: #e5e7eb;">{{ old_status }}</span>
                <span class="arrow">→</span>
                <span class="status-box" style="background-color: #d1fae5; color: #065f46;">{{ new_status }}</span>
            </div>
        </div>
        
        <div class="notes-box">
            <p style="margin: 0;"><strong>Catatan:</strong></p>
            <p style="margin: 10px 0 0;">{{ notes }}</p>
        </div>
        
        <p><strong>Tarikh Kemaskini:</strong> {{ updated_at }}</p>
        
        <p>Anda boleh log masuk ke Portal Khidmat Rakyat untuk melihat maklumat penuh aduan anda.</p>
        
        <p style="margin-top: 30px;">Terima kasih,<br>
        <strong>Jabatan Khidmat Digital Malaysia</strong></p>
    </div>
    
    <div class="footer">
        <p>Email automatik ini dihantar dari sistem Portal Khidmat Rakyat.</p>
        <p>Untuk pertanyaan, sila log masuk ke portal atau hubungi kami.</p>
    </div>
</body>
</html>
HTML;
    }

    private function getTicketStatusUpdateText(): string
    {
        return <<<'TEXT'
Assalamualaikum / Salam Sejahtera {{ customer_name }},

Status aduan anda telah dikemaskini.

NOMBOR ADUAN: {{ ticket_number }}
TAJUK: {{ subject }}

PERUBAHAN STATUS:
{{ old_status }} → {{ new_status }}

CATATAN:
{{ notes }}

TARIKH KEMASKINI: {{ updated_at }}

Anda boleh log masuk ke Portal Khidmat Rakyat untuk melihat maklumat penuh aduan anda.

Terima kasih,
Jabatan Khidmat Digital Malaysia

---
Email automatik ini dihantar dari sistem Portal Khidmat Rakyat.
Untuk pertanyaan, sila log masuk ke portal atau hubungi kami.
TEXT;
    }
}
