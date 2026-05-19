<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotSession extends Model
{
    protected $primaryKey = 'interaction_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'interaction_id',
        'customer_name',
        'customer_ic',
        'customer_phone',
        'customer_email',
        'topic',
        'faq_path',
        'status',
        'agent_id',
        'agent_name',
        'queue_name',
        'crm_agent_id',
        'issue',
    ];
}
