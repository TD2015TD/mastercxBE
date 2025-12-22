<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the users for this role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the menu items assigned to this role.
     */
    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'role_menu_items')
            ->withPivot(['can_view', 'can_create', 'can_edit', 'can_delete'])
            ->withTimestamps()
            ->orderBy('order');
    }

    /**
     * Get active menu items for this role.
     */
    public function activeMenuItems(): BelongsToMany
    {
        return $this->menuItems()->where('menu_items.is_active', true);
    }

    /**
     * Get the Superset dashboards assigned to this role.
     */
    public function supersetDashboards(): BelongsToMany
    {
        return $this->belongsToMany(SupersetDashboard::class, 'role_superset_dashboard')
            ->withPivot(['can_view', 'can_export', 'can_share', 'custom_rls'])
            ->withTimestamps();
    }

    /**
     * Get active Superset dashboards for this role.
     */
    public function activeSupersetDashboards(): BelongsToMany
    {
        return $this->supersetDashboards()
            ->where('superset_dashboards.is_active', true)
            ->orderBy('superset_dashboards.order')
            ->orderBy('superset_dashboards.title_en');
    }
}
