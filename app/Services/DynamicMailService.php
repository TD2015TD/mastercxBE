<?php

namespace App\Services;

use App\Models\EmailConfiguration;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Mail\Mailable;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class DynamicMailService
{
    /**
     * Send email using database SMTP configuration.
     *
     * @param string|array $to
     * @param Mailable $mailable
     * @param EmailConfiguration|null $config
     * @return bool
     */
    public static function send($to, Mailable $mailable, ?EmailConfiguration $config = null): bool
    {
        try {
            // Get SMTP configuration from database or use default
            if (!$config) {
                $config = EmailConfiguration::getDefaultSmtp();
            }

            if ($config && in_array($config->type, ['smtp', 'both'])) {
                // Use database configuration
                return self::sendWithConfig($to, $mailable, $config);
            }

            // Fallback to default Laravel mail configuration
            Mail::to($to)->send($mailable);
            return true;

        } catch (\Exception $e) {
            \Log::error('Failed to send email: ' . $e->getMessage(), [
                'to' => is_array($to) ? implode(', ', $to) : $to,
                'config_id' => $config?->id,
            ]);
            return false;
        }
    }

    /**
     * Send email with specific database configuration.
     *
     * @param string|array $to
     * @param Mailable $mailable
     * @param EmailConfiguration $config
     * @return bool
     */
    protected static function sendWithConfig($to, Mailable $mailable, EmailConfiguration $config): bool
    {
        // Create a custom mailer on the fly
        $transport = new EsmtpTransport(
            $config->smtp_host,
            $config->smtp_port,
            $config->smtp_encryption === 'tls'
        );

        if ($config->smtp_encryption === 'ssl') {
            $transport->setStreamOptions([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
        }

        $transport->setUsername($config->smtp_username);
        $transport->setPassword($config->smtp_password);

        // Configure from address
        if ($config->smtp_from_address) {
            $mailable->from($config->smtp_from_address, $config->smtp_from_name ?? '');
        }

        // Create a temporary mailer configuration
        Config::set('mail.mailers.dynamic_smtp', [
            'transport' => 'smtp',
            'host' => $config->smtp_host,
            'port' => $config->smtp_port,
            'encryption' => $config->smtp_encryption,
            'username' => $config->smtp_username,
            'password' => $config->smtp_password,
            'timeout' => null,
        ]);

        // Send using the dynamic mailer
        Mail::mailer('dynamic_smtp')->to($to)->send($mailable);

        return true;
    }

    /**
     * Send email using a specific configuration by ID.
     *
     * @param string|array $to
     * @param Mailable $mailable
     * @param int $configId
     * @return bool
     */
    public static function sendWithConfigId($to, Mailable $mailable, int $configId): bool
    {
        $config = EmailConfiguration::find($configId);

        if (!$config) {
            \Log::warning("Email configuration {$configId} not found, using default");
            return self::send($to, $mailable);
        }

        return self::send($to, $mailable, $config);
    }

    /**
     * Test SMTP connection.
     *
     * @param EmailConfiguration $config
     * @return array
     */
    public static function testSmtpConnection(EmailConfiguration $config): array
    {
        try {
            $transport = new EsmtpTransport(
                $config->smtp_host,
                $config->smtp_port,
                $config->smtp_encryption === 'tls'
            );

            if ($config->smtp_encryption === 'ssl') {
                $transport->setStreamOptions([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ]
                ]);
            }

            $transport->setUsername($config->smtp_username);
            $transport->setPassword($config->smtp_password);

            // Test connection by starting the transport
            $transport->start();
            $transport->stop();

            return [
                'success' => true,
                'message' => 'SMTP connection successful',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'SMTP connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get available SMTP configurations.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAvailableConfigs()
    {
        return EmailConfiguration::smtp()->active()->get();
    }
}
