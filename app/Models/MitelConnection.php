<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class MitelConnection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'host',
        'port',
        'database',
        'username',
        'password',
        'encrypt',
        'trust_server_certificate',
        'is_active',
        'last_sync_at',
        'sync_status',
        'last_sync_message',
    ];

    protected $casts = [
        'encrypt' => 'boolean',
        'trust_server_certificate' => 'boolean',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Get the decrypted password.
     */
    public function getPasswordAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Encrypt the password before saving.
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Get the sync logs for this connection.
     */
    public function syncLogs()
    {
        return $this->hasMany(MitelSyncLog::class);
    }

    /**
     * Get the latest sync log.
     */
    public function latestSyncLog()
    {
        return $this->hasOne(MitelSyncLog::class)->latestOfMany();
    }

    /**
     * Get the connection configuration array.
     */
    public function getConnectionConfig(): array
    {
        return [
            'driver' => 'sqlsrv',
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password, // Will use the decrypted value
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => $this->encrypt ? 'yes' : 'no',
            'trust_server_certificate' => $this->trust_server_certificate,
        ];
    }

    /**
     * Update the sync status.
     */
    public function updateSyncStatus(string $status, ?string $message = null): void
    {
        $this->update([
            'sync_status' => $status,
            'last_sync_at' => now(),
            'last_sync_message' => $message,
        ]);
    }

    /**
     * Scope to get only active connections.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
