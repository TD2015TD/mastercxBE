<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailConfiguration extends Model
{
    protected $fillable = [
        'name',
        'type',
        'is_active',
        'is_default',
        // SMTP settings
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'smtp_from_address',
        'smtp_from_name',
        // IMAP settings
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'imap_password',
        'imap_folder',
        // Additional settings
        'department',
        'auto_assign',
        'default_priority',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'auto_assign' => 'boolean',
        'settings' => 'array',
        'smtp_port' => 'integer',
        'imap_port' => 'integer',
    ];

    protected $hidden = [
        'smtp_password',
        'imap_password',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Ensure only one default configuration per type
        static::saving(function ($config) {
            if ($config->is_default) {
                static::where('type', $config->type)
                    ->where('id', '!=', $config->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * Scope to get active configurations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get SMTP configurations.
     */
    public function scopeSmtp($query)
    {
        return $query->whereIn('type', ['smtp', 'both']);
    }

    /**
     * Scope to get IMAP configurations.
     */
    public function scopeImap($query)
    {
        return $query->whereIn('type', ['imap', 'both']);
    }

    /**
     * Get the default SMTP configuration.
     */
    public static function getDefaultSmtp(): ?self
    {
        return static::smtp()
            ->active()
            ->where('is_default', true)
            ->first();
    }

    /**
     * Get the default IMAP configuration.
     */
    public static function getDefaultImap(): ?self
    {
        return static::imap()
            ->active()
            ->where('is_default', true)
            ->first();
    }

    /**
     * Get all active IMAP configurations.
     */
    public static function getActiveImapConfigs()
    {
        return static::imap()
            ->active()
            ->get();
    }

    /**
     * Test SMTP connection.
     */
    public function testSmtpConnection(): array
    {
        return \App\Services\DynamicMailService::testSmtpConnection($this);
    }

    /**
     * Test IMAP connection.
     */
    public function testImapConnection(): array
    {
        try {
            $mailbox = sprintf(
                '{%s:%d/imap/%s}%s',
                $this->imap_host,
                $this->imap_port,
                $this->imap_encryption,
                $this->imap_folder
            );

            $connection = @imap_open(
                $mailbox,
                $this->imap_username,
                $this->imap_password
            );

            if (!$connection) {
                throw new \Exception(imap_last_error() ?: 'Unknown error');
            }

            imap_close($connection);

            return [
                'success' => true,
                'message' => 'IMAP connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'IMAP connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Encrypt sensitive data before saving.
     */
    public function setSmtpPasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['smtp_password'] = encrypt($value);
        }
    }

    /**
     * Decrypt SMTP password when retrieving.
     */
    public function getSmtpPasswordAttribute($value)
    {
        if ($value) {
            try {
                return decrypt($value);
            } catch (\Exception $e) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Encrypt IMAP password before saving.
     */
    public function setImapPasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['imap_password'] = encrypt($value);
        }
    }

    /**
     * Decrypt IMAP password when retrieving.
     */
    public function getImapPasswordAttribute($value)
    {
        if ($value) {
            try {
                return decrypt($value);
            } catch (\Exception $e) {
                return $value;
            }
        }
        return null;
    }
}
