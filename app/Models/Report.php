<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'report_type',
        'generated_by',
        'parameters',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'status',
        'generated_at',
        'expires_at',
        'download_count',
    ];

    protected $casts = [
        'parameters' => 'array',
        'file_size' => 'integer',
        'download_count' => 'integer',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user who generated this report.
     */
    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Scope for completed reports.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for pending reports.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for failed reports.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Increment download count.
     */
    public function incrementDownloadCount()
    {
        $this->increment('download_count');
    }

    /**
     * Check if report has expired.
     */
    public function isExpired()
    {
        return $this->expires_at && now()->greaterThan($this->expires_at);
    }
}
