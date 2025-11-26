<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketHistory extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'action',
        'field_name',
        'old_value',
        'new_value',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the ticket that this history belongs to
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who made this change
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a history entry
     */
    public static function log(
        int $ticketId,
        string $action,
        ?int $userId = null,
        ?string $fieldName = null,
        $oldValue = null,
        $newValue = null,
        ?string $description = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'ticket_id' => $ticketId,
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
