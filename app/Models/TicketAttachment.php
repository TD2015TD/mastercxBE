<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TicketAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'uploaded_by',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'mime_type',
        'description',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    protected $appends = [
        'file_size_formatted',
        'download_url',
    ];

    /**
     * Get the ticket that owns the attachment.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who uploaded the attachment.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get formatted file size.
     */
    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get download URL for the attachment.
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('api.tickets.attachments.download', [
            'ticketId' => $this->ticket_id,
            'attachmentId' => $this->id,
        ]);
    }

    /**
     * Delete the file from storage.
     */
    public function deleteFile(): bool
    {
        if (Storage::disk(config('filesystems.default'))->exists($this->file_path)) {
            return Storage::disk(config('filesystems.default'))->delete($this->file_path);
        }
        return true;
    }

    /**
     * Get the file type category based on mime type.
     */
    public static function getFileTypeFromMime(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } elseif (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ])) {
            return 'document';
        } elseif (in_array($mimeType, ['application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed'])) {
            return 'archive';
        } else {
            return 'other';
        }
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Delete file from storage when attachment is deleted
        static::deleted(function ($attachment) {
            $attachment->deleteFile();
        });
    }
}
