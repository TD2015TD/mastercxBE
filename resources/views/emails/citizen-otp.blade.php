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
        
        <p>Anda telah meminta kod OTP untuk log masuk ke <strong>Portal Khidmat Rakyat</strong> menggunakan nombor telefon <strong>{{ $phoneNumber }}</strong>.</p>
        
        <p style="text-align: center; margin: 25px 0 10px;">Kod OTP anda ialah:</p>
        
        <div class="otp-code">{{ $otpCode }}</div>
        
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
