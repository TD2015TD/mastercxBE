<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SystemConfiguration extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category',
        'key',
        'value',
        'type',
        'description',
        'is_encrypted',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Cache key prefix for configurations
     */
    const CACHE_PREFIX = 'system_config_';
    const CACHE_TTL = 3600; // 1 hour

    /**
     * Boot method to clear cache on save/delete
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($config) {
            $config->clearCache();
        });

        static::deleted(function ($config) {
            $config->clearCache();
        });
    }

    /**
     * Get attribute accessor - decrypt if needed
     */
    public function getValueAttribute($value)
    {
        if ($this->is_encrypted && $value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return $value;
            }
        }

        // Cast based on type
        return $this->castValue($value);
    }

    /**
     * Set attribute mutator - encrypt if needed
     */
    public function setValueAttribute($value)
    {
        if ($this->is_encrypted && $value) {
            $this->attributes['value'] = Crypt::encryptString($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }

    /**
     * Cast value based on type
     */
    protected function castValue($value)
    {
        if (is_null($value)) {
            return null;
        }

        return match ($this->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'json', 'array' => is_string($value) ? json_decode($value, true) : $value,
            default => $value,
        };
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get active configurations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get a configuration value by key
     */
    public static function get($key, $default = null)
    {
        try {
            $cacheKey = self::CACHE_PREFIX . $key;

            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
                $config = static::where('key', $key)
                    ->where('is_active', true)
                    ->first();

                return $config ? $config->value : $default;
            });
        } catch (\Exception $e) {
            // If table doesn't exist or query fails, return default value
            \Log::debug("SystemConfiguration::get() failed for key '{$key}': " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Set a configuration value
     */
    public static function set($key, $value, $category = 'general', $type = 'string', $isEncrypted = false)
    {
        $config = static::updateOrCreate(
            ['key' => $key],
            [
                'category' => $category,
                'value' => $value,
                'type' => $type,
                'is_encrypted' => $isEncrypted,
                'is_active' => true,
            ]
        );

        return $config;
    }

    /**
     * Get all configurations by category
     */
    public static function getByCategory($category)
    {
        $cacheKey = self::CACHE_PREFIX . 'category_' . $category;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($category) {
            return static::byCategory($category)
                ->active()
                ->get()
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    /**
     * Get all mail configurations
     */
    public static function getMailConfig()
    {
        return static::getByCategory('mail');
    }

    /**
     * Get authentication configuration
     */
    public static function getAuthConfig()
    {
        return static::getByCategory('auth');
    }

    /**
     * Clear cache for this configuration
     */
    public function clearCache()
    {
        Cache::forget(self::CACHE_PREFIX . $this->key);
        Cache::forget(self::CACHE_PREFIX . 'category_' . $this->category);
        Cache::forget(self::CACHE_PREFIX . 'all');
    }

    /**
     * Clear all configuration cache
     */
    public static function clearAllCache()
    {
        Cache::flush();
    }

    /**
     * Get grouped configurations by category
     */
    public static function getAllGrouped()
    {
        $cacheKey = self::CACHE_PREFIX . 'all';

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return static::active()
                ->get()
                ->groupBy('category')
                ->map(function ($configs) {
                    return $configs->pluck('value', 'key')->toArray();
                })
                ->toArray();
        });
    }
}
