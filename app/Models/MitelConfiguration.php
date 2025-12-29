<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MitelConfiguration extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get configuration value by key
     */
    public static function getValue(string $key, $default = null)
    {
        $config = self::where('key', $key)
            ->where('is_active', true)
            ->first();

        return $config ? $config->value : $default;
    }

    /**
     * Get all active configurations as key-value pairs
     */
    public static function getAllActive()
    {
        return self::where('is_active', true)
            ->get()
            ->pluck('value', 'key')
            ->toArray();
    }
}
