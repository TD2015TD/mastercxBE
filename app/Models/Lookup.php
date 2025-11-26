<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lookup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'code',
        'name_en',
        'name_ms',
        'description_en',
        'description_ms',
        'sort_order',
        'is_active',
        'color',
        'icon',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'sort_order' => 'integer',
    ];

    /**
     * Scope: Filter by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Only active lookups
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get localized name based on locale
     */
    public function getLocalizedNameAttribute()
    {
        $locale = app()->getLocale();
        return $locale === 'ms' ? $this->name_ms : $this->name_en;
    }

    /**
     * Get localized description based on locale
     */
    public function getLocalizedDescriptionAttribute()
    {
        $locale = app()->getLocale();
        return $locale === 'ms' ? $this->description_ms : $this->description_en;
    }

    /**
     * Static method to get lookups by type as key-value pairs
     */
    public static function getByType($type, $locale = 'en')
    {
        return self::ofType($type)
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(function ($item) use ($locale) {
                $name = $locale === 'ms' ? $item->name_ms : $item->name_en;
                return [$item->code => $name];
            });
    }

    /**
     * Static method to get all lookups grouped by type
     */
    public static function getAllGrouped($locale = 'en')
    {
        return self::active()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('type')
            ->map(function ($items) use ($locale) {
                return $items->map(function ($item) use ($locale) {
                    return [
                        'code' => $item->code,
                        'name' => $locale === 'ms' ? $item->name_ms : $item->name_en,
                        'description' => $locale === 'ms' ? $item->description_ms : $item->description_en,
                        'color' => $item->color,
                        'icon' => $item->icon,
                        'metadata' => $item->metadata,
                    ];
                });
            });
    }
}
