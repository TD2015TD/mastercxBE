<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeBaseArticle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'content',
        'category_code', // references Lookup.code
        'author',
        'status',
        'uploaded_by',
        'published_at',
        'file_path',
        'file_name',
        'file_type',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(Lookup::class, 'category_code', 'code')
            ->where('type', 'knowledge_base_category');
    }
}
