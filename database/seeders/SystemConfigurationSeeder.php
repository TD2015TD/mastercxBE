<?php

namespace Database\Seeders;

use App\Models\SystemConfiguration;
use Illuminate\Database\Seeder;

class SystemConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configurations = [
            // Mail Configuration
            [
                'category' => 'mail',
                'key' => 'mail.driver',
                'value' => 'smtp',
                'type' => 'string',
                'description' => 'Mail driver (smtp, sendmail, mailgun, etc.)',
                'is_encrypted' => false,
                'is_active' => true,
            ],
            [
                'category' => 'mail',
                'key' => 'mail.host',
                'value' => 'smtp.gmail.com',
                'type' => 'string',
                'description' => 'SMTP server host',
                'is_encrypted' => false,
                'is_active' => true,
            ],
            [
                'category' => 'mail',
                'key' => 'mail.port',
                'value' => '465',
                'type' => 'integer',
                'description' => 'SMTP server port',
                'is_encrypted' => false,
                'is_active' => true,
            ],
            [
                'category' => 'mail',
                'key' => 'mail.encryption',
                'value' => 'ssl',
                'type' => 'string',
                'description' => 'Mail encryption (ssl, tls, null)',
                'is_encrypted' => false,
                'is_active' => true,
            ],
            [
                'category' => 'mail',
                'key' => 'mail.username',
                'value' => 'mail@apextreasuresoftware.com',
                'type' => 'string',
                'description' => 'SMTP username',
                'is_encrypted' => false,
                'is_active' => true,
            ],
            [
                'category' => 'mail',
                'key' => 'mail.password',
                'value' => 'elkb nnor xrlp shos',
                'type' => 'string',
                'description' => 'SMTP password',
                'is_encrypted' => true,
                'is_active' => true,
            ],
            [
                'category' => 'mail',
                'key' => 'mail.from.address',
                'value' => 'mail@apextreasuresoftware.com',
                'type' => 'string',
                'description' => 'Default from email address',
                'is_encrypted' => false,
                'is_active' => true,
            ],
            [
                'category' => 'mail',
                'key' => 'mail.from.name',
                'value' => 'JKM CRM',
                'type' => 'string',
                'description' => 'Default from name',
                'is_encrypted' => false,
                'is_active' => true,
            ],

            // Authentication Configuration
            [
                'category' => 'auth',
                'key' => 'auth.method',
                'value' => 'database',
                'type' => 'string',
                'description' => 'Authentication method (database, ldap)',
                'is_encrypted' => false,
                'is_active' => true,
                'metadata' => [
                    'options' => ['database', 'ldap'],
                ],
            ],
            [
                'category' => 'auth',
                'key' => 'auth.allow_registration',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Allow user registration',
                'is_encrypted' => false,
                'is_active' => true,
            ],
            [
                'category' => 'auth',
                'key' => 'auth.require_email_verification',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Require email verification',
                'is_encrypted' => false,
                'is_active' => true,
            ],

            // LDAP Configuration (if LDAP auth is enabled)
            [
                'category' => 'ldap',
                'key' => 'ldap.host',
                'value' => '',
                'type' => 'string',
                'description' => 'LDAP server host',
                'is_encrypted' => false,
                'is_active' => false,
            ],
            [
                'category' => 'ldap',
                'key' => 'ldap.port',
                'value' => '389',
                'type' => 'integer',
                'description' => 'LDAP server port',
                'is_encrypted' => false,
                'is_active' => false,
            ],
            [
                'category' => 'ldap',
                'key' => 'ldap.base_dn',
                'value' => '',
                'type' => 'string',
                'description' => 'LDAP base DN',
                'is_encrypted' => false,
                'is_active' => false,
            ],
            [
                'category' => 'ldap',
                'key' => 'ldap.username',
                'value' => '',
                'type' => 'string',
                'description' => 'LDAP bind username',
                'is_encrypted' => false,
                'is_active' => false,
            ],
            [
                'category' => 'ldap',
                'key' => 'ldap.password',
                'value' => '',
                'type' => 'string',
                'description' => 'LDAP bind password',
                'is_encrypted' => true,
                'is_active' => false,
            ],
            [
                'category' => 'ldap',
                'key' => 'ldap.use_ssl',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Use SSL for LDAP connection',
                'is_encrypted' => false,
                'is_active' => false,
            ],
            [
                'category' => 'ldap',
                'key' => 'ldap.user_search_attribute',
                'value' => 'uid',
                'type' => 'string',
                'description' => 'LDAP user search attribute',
                'is_encrypted' => false,
                'is_active' => false,
            ],

            // General System Configuration
            [
                'category' => 'general',
                'key' => 'system.name',
                'value' => 'JKM CRM',
                'type' => 'string',
                'description' => 'System name',
                'is_encrypted' => false,
                'is_active' => true,
            ],
            [
                'category' => 'general',
                'key' => 'system.timezone',
                'value' => 'Asia/Kuala_Lumpur',
                'type' => 'string',
                'description' => 'System timezone',
                'is_encrypted' => false,
                'is_active' => true,
            ],
            [
                'category' => 'general',
                'key' => 'system.date_format',
                'value' => 'Y-m-d',
                'type' => 'string',
                'description' => 'System date format',
                'is_encrypted' => false,
                'is_active' => true,
            ],
            [
                'category' => 'general',
                'key' => 'system.time_format',
                'value' => 'H:i:s',
                'type' => 'string',
                'description' => 'System time format',
                'is_encrypted' => false,
                'is_active' => true,
            ],
        ];

        foreach ($configurations as $config) {
            SystemConfiguration::updateOrCreate(
                ['key' => $config['key']],
                $config
            );
        }

        $this->command->info('System configurations seeded successfully!');
    }
}
