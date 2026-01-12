<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MitelSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'mitel_connection_id',
        'sync_type',
        'table_name',
        'status',
        'started_at',
        'completed_at',
        'records_fetched',
        'records_inserted',
        'records_updated',
        'records_failed',
        'error_message',
        'sync_details',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'sync_details' => 'array',
    ];

    /**
     * Get the connection for this sync log.
     */
    public function connection()
    {
        return $this->belongsTo(MitelConnection::class, 'mitel_connection_id');
    }

    /**
     * Get the duration of the sync in seconds.
     */
    public function getDurationAttribute()
    {
        if ($this->started_at && $this->completed_at) {
            return $this->completed_at->diffInSeconds($this->started_at);
        }
        return null;
    }

    /**
     * Scope to get only successful syncs.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope to get only failed syncs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get syncs for a specific table.
     */
    public function scopeForTable($query, string $tableName)
    {
        return $query->where('table_name', $tableName);
    }
}
