<?php

namespace App\Services;

use App\Models\SystemConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthenticationService
{
    /**
     * Authenticate user based on system configuration
     */
    public function authenticate($credentials)
    {
        Log::info('AuthenticationService: Starting authentication', [
            'username' => $credentials['username'] ?? 'not_provided'
        ]);

        try {
            // Try to get auth method from system configuration
            // Default to 'database' if table doesn't exist or config not found
            $authMethod = SystemConfiguration::get('auth.method', 'database');
            Log::info('AuthenticationService: Auth method determined', ['method' => $authMethod]);
        } catch (\Exception $e) {
            // If system_configurations table doesn't exist yet, default to database auth
            Log::info('SystemConfiguration table not available, defaulting to database authentication', [
                'error' => $e->getMessage()
            ]);
            $authMethod = 'database';
        }

        $result = match ($authMethod) {
            'ldap' => $this->authenticateLdap($credentials),
            'database' => $this->authenticateDatabase($credentials),
            default => $this->authenticateDatabase($credentials),
        };

        Log::info('AuthenticationService: Authentication result', [
            'username' => $credentials['username'] ?? 'not_provided',
            'success' => $result['success'],
            'message' => $result['message'] ?? 'N/A'
        ]);

        return $result;
    }

    /**
     * Authenticate using database
     */
    protected function authenticateDatabase($credentials)
    {
        Log::debug('AuthenticationService: Database authentication started', [
            'username' => $credentials['username']
        ]);

        // Find user by username
        $user = User::where('username', $credentials['username'])->first();

        if (!$user) {
            Log::warning('AuthenticationService: User not found in database', [
                'username' => $credentials['username']
            ]);
            return [
                'success' => false,
                'message' => 'User not found',
            ];
        }

        Log::debug('AuthenticationService: User found, checking password', [
            'user_id' => $user->id,
            'username' => $user->username,
            'has_password_hash' => !empty($user->password)
        ]);

        if (!Hash::check($credentials['password'], $user->password)) {
            Log::warning('AuthenticationService: Invalid password', [
                'user_id' => $user->id,
                'username' => $user->username
            ]);
            return [
                'success' => false,
                'message' => 'Invalid credentials',
            ];
        }

        if ($user->deleted_at) {
            Log::warning('AuthenticationService: User account is deactivated', [
                'user_id' => $user->id,
                'username' => $user->username,
                'deleted_at' => $user->deleted_at
            ]);
            return [
                'success' => false,
                'message' => 'User account is deactivated',
            ];
        }

        Log::info('AuthenticationService: Database authentication successful', [
            'user_id' => $user->id,
            'username' => $user->username,
            'email' => $user->email
        ]);

        return [
            'success' => true,
            'user' => $user,
        ];
    }

    /**
     * Authenticate using LDAP
     */
    protected function authenticateLdap($credentials)
    {
        try {
            // Get LDAP configuration from database
            $ldapConfig = [
                'host' => SystemConfiguration::get('ldap.host'),
                'port' => SystemConfiguration::get('ldap.port', 389),
                'base_dn' => SystemConfiguration::get('ldap.base_dn'),
                'username' => SystemConfiguration::get('ldap.username'),
                'password' => SystemConfiguration::get('ldap.password'),
                'use_ssl' => SystemConfiguration::get('ldap.use_ssl', false),
                'user_search_attribute' => SystemConfiguration::get('ldap.user_search_attribute', 'uid'),
            ];

            // Validate LDAP configuration
            if (empty($ldapConfig['host']) || empty($ldapConfig['base_dn'])) {
                Log::error('LDAP configuration is incomplete');
                return [
                    'success' => false,
                    'message' => 'LDAP authentication is not properly configured',
                ];
            }

            // Connect to LDAP server
            $ldapConn = $this->connectToLdap($ldapConfig);

            if (!$ldapConn) {
                return [
                    'success' => false,
                    'message' => 'Failed to connect to LDAP server',
                ];
            }

            // Search for user in LDAP
            $userDn = $this->findLdapUser($ldapConn, $ldapConfig, $credentials['email']);

            if (!$userDn) {
                ldap_close($ldapConn);
                return [
                    'success' => false,
                    'message' => 'User not found in LDAP',
                ];
            }

            // Attempt to bind with user credentials
            $bindResult = @ldap_bind($ldapConn, $userDn, $credentials['password']);

            if (!$bindResult) {
                ldap_close($ldapConn);
                return [
                    'success' => false,
                    'message' => 'Invalid LDAP credentials',
                ];
            }

            ldap_close($ldapConn);

            // Find or create user in local database
            $user = $this->syncLdapUser($credentials['username']);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Failed to sync LDAP user',
                ];
            }

            return [
                'success' => true,
                'user' => $user,
            ];
        } catch (\Exception $e) {
            Log::error('LDAP authentication error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'LDAP authentication failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Connect to LDAP server
     */
    protected function connectToLdap($config)
    {
        $protocol = $config['use_ssl'] ? 'ldaps' : 'ldap';
        $ldapUri = "{$protocol}://{$config['host']}:{$config['port']}";

        $ldapConn = ldap_connect($ldapUri);

        if (!$ldapConn) {
            return false;
        }

        ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);

        // Bind with service account if configured
        if (!empty($config['username']) && !empty($config['password'])) {
            $bind = @ldap_bind($ldapConn, $config['username'], $config['password']);
            if (!$bind) {
                ldap_close($ldapConn);
                return false;
            }
        }

        return $ldapConn;
    }

    /**
     * Find user in LDAP
     */
    protected function findLdapUser($ldapConn, $config, $username)
    {
        $searchAttribute = $config['user_search_attribute'];
        $filter = "({$searchAttribute}={$username})";

        $search = @ldap_search($ldapConn, $config['base_dn'], $filter);

        if (!$search) {
            return null;
        }

        $entries = ldap_get_entries($ldapConn, $search);

        if ($entries['count'] === 0) {
            return null;
        }

        return $entries[0]['dn'];
    }

    /**
     * Sync LDAP user to local database
     */
    protected function syncLdapUser($username)
    {
        // Find user by username
        $user = User::where('username', $username)->first();

        // If user doesn't exist, create a new one (if registration is allowed)
        if (!$user) {
            try {
                $allowRegistration = SystemConfiguration::get('auth.allow_registration', false);
            } catch (\Exception $e) {
                $allowRegistration = false;
            }

            if (!$allowRegistration) {
                return null;
            }

            $user = User::create([
                'username' => $username,
                'email' => $username, // Default email to username
                'name' => $username,
                'password' => Hash::make(bin2hex(random_bytes(16))), // Random password (won't be used)
                'role_id' => 2, // Default to Agent role
            ]);
        }

        return $user;
    }

    /**
     * Test LDAP connection
     */
    public static function testLdapConnection()
    {
        try {
            $ldapConfig = [
                'host' => SystemConfiguration::get('ldap.host'),
                'port' => SystemConfiguration::get('ldap.port', 389),
                'base_dn' => SystemConfiguration::get('ldap.base_dn'),
                'username' => SystemConfiguration::get('ldap.username'),
                'password' => SystemConfiguration::get('ldap.password'),
                'use_ssl' => SystemConfiguration::get('ldap.use_ssl', false),
            ];

            if (empty($ldapConfig['host'])) {
                return [
                    'success' => false,
                    'message' => 'LDAP host is not configured',
                ];
            }

            $protocol = $ldapConfig['use_ssl'] ? 'ldaps' : 'ldap';
            $ldapUri = "{$protocol}://{$ldapConfig['host']}:{$ldapConfig['port']}";

            $ldapConn = ldap_connect($ldapUri);

            if (!$ldapConn) {
                return [
                    'success' => false,
                    'message' => 'Failed to connect to LDAP server',
                ];
            }

            ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);

            if (!empty($ldapConfig['username']) && !empty($ldapConfig['password'])) {
                $bind = @ldap_bind($ldapConn, $ldapConfig['username'], $ldapConfig['password']);
                if (!$bind) {
                    ldap_close($ldapConn);
                    return [
                        'success' => false,
                        'message' => 'Failed to bind with LDAP credentials',
                    ];
                }
            }

            ldap_close($ldapConn);

            return [
                'success' => true,
                'message' => 'LDAP connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'LDAP connection error: ' . $e->getMessage(),
            ];
        }
    }
}
