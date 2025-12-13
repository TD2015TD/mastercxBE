<?php

namespace App\Providers;

use App\Models\SystemConfiguration;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class DynamicConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only load configurations if database tables exist
        if ($this->canLoadConfig()) {
            $this->loadMailConfiguration();
            $this->loadAuthConfiguration();
        }
    }

    /**
     * Check if we can load configuration from database
     */
    private function canLoadConfig(): bool
    {
        try {
            return Schema::hasTable('system_configurations');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Load mail configuration from database
     */
    private function loadMailConfiguration(): void
    {
        try {
            $mailConfig = SystemConfiguration::getMailConfig();

            if (!empty($mailConfig)) {
                // Set mail driver
                if (isset($mailConfig['mail.driver'])) {
                    config(['mail.default' => $mailConfig['mail.driver']]);
                }

                // Set SMTP configuration
                config([
                    'mail.mailers.smtp.transport' => 'smtp',
                    'mail.mailers.smtp.host' => $mailConfig['mail.host'] ?? config('mail.mailers.smtp.host'),
                    'mail.mailers.smtp.port' => $mailConfig['mail.port'] ?? config('mail.mailers.smtp.port'),
                    'mail.mailers.smtp.encryption' => $mailConfig['mail.encryption'] ?? config('mail.mailers.smtp.encryption'),
                    'mail.mailers.smtp.username' => $mailConfig['mail.username'] ?? config('mail.mailers.smtp.username'),
                    'mail.mailers.smtp.password' => SystemConfiguration::get('mail.password', config('mail.mailers.smtp.password')),
                ]);

                // Set from address
                config([
                    'mail.from.address' => $mailConfig['mail.from.address'] ?? config('mail.from.address'),
                    'mail.from.name' => $mailConfig['mail.from.name'] ?? config('mail.from.name'),
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail if configuration cannot be loaded
            \Log::warning('Could not load mail configuration from database: ' . $e->getMessage());
        }
    }

    /**
     * Load authentication configuration from database
     */
    private function loadAuthConfiguration(): void
    {
        try {
            $authConfig = SystemConfiguration::getAuthConfig();

            if (!empty($authConfig)) {
                // You can extend this to configure authentication behavior
                config([
                    'auth.method' => $authConfig['auth.method'] ?? 'database',
                    'auth.allow_registration' => $authConfig['auth.allow_registration'] ?? false,
                    'auth.require_email_verification' => $authConfig['auth.require_email_verification'] ?? false,
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail if configuration cannot be loaded
            \Log::warning('Could not load auth configuration from database: ' . $e->getMessage());
        }
    }
}
