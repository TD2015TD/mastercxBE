<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupersetConfiguration extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
        'is_secret',
        'is_active',
    ];

    protected $casts = [
        'is_secret' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function getValue(string $key, $default = null)
    {
        $config = self::where('key', $key)
            ->where('is_active', true)
            ->first();

        return $config ? $config->value : $default;
    }

    public static function getAllActive(): array
    {
        return self::where('is_active', true)
            ->get()
            ->pluck('value', 'key')
            ->toArray();
    }
}
