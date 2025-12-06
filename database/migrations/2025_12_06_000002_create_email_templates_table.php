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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 255);
            $table->string('subject', 255);
            $table->text('body_html');
            $table->text('body_text');
            $table->json('available_variables')->nullable();
            $table->enum('category', ['system', 'notification', 'otp', 'marketing'])->default('system');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('code');
            $table->index('category');
            $table->index('is_active');
        });

        // Insert default OTP template
        DB::table('email_templates')->insert([
            'code' => 'CITIZEN_OTP',
            'name' => 'Citizen Portal OTP Email',
            'subject' => 'Kod OTP untuk Portal Khidmat Rakyat',
            'body_html' => $this->getDefaultHtmlTemplate(),
            'body_text' => $this->getDefaultTextTemplate(),
            'available_variables' => json_encode([
                'otp_code' => 'The 6-digit OTP code',
                'phone_number' => 'User phone number',
            ]),
            'category' => 'otp',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }

    private function getDefaultHtmlTemplate(): string
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

    private function getDefaultTextTemplate(): string
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
};
