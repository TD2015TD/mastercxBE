<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupersetDashboard extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'superset_id',
        'slug',
        'title_en',
        'description_en',
        'title_ms',
        'description_ms',
        'category',
        'icon',
        'order',
        'is_active',
        'is_public',
        'ui_config',
        'default_filters',
        'rls_config',
        'tags',
        'thumbnail_url',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'ui_config' => 'array',
        'default_filters' => 'array',
        'rls_config' => 'array',
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the roles that have access to this dashboard.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_superset_dashboard')
            ->withPivot(['can_view', 'can_export', 'can_share', 'custom_rls'])
            ->withTimestamps();
    }

    /**
     * Get localized title based on app locale.
     *
     * @param string|null $locale
     * @return string
     */
    public function getTitle(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        return $this->{"title_{$locale}"} ?? $this->title_en;
    }

    /**
     * Get localized description based on app locale.
     *
     * @param string|null $locale
     * @return string|null
     */
    public function getDescription(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        return $this->{"description_{$locale}"} ?? $this->description_en;
    }

    /**
     * Get tags as array.
     *
     * @return array
     */
    public function getTagsArray(): array
    {
        if (empty($this->tags)) {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $this->tags)));
    }

    /**
     * Scope: Active dashboards only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Public dashboards.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope: By category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: Ordered.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('title_en');
    }

    /**
     * Check if a role has access to this dashboard.
     *
     * @param Role|int $role
     * @return bool
     */
    public function hasRoleAccess($role): bool
    {
        $roleId = $role instanceof Role ? $role->id : $role;
        return $this->roles()->where('role_id', $roleId)->exists();
    }

    /**
     * Get permissions for a specific role.
     *
     * @param Role|int $role
     * @return array|null
     */
    public function getRolePermissions($role): ?array
    {
        $roleId = $role instanceof Role ? $role->id : $role;
        $pivot = $this->roles()->where('role_id', $roleId)->first()?->pivot;

        if (!$pivot) {
            return null;
        }

        return [
            'can_view' => $pivot->can_view,
            'can_export' => $pivot->can_export,
            'can_share' => $pivot->can_share,
            'custom_rls' => $pivot->custom_rls,
        ];
    }
}
