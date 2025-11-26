<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'name_ms',
        'parent_id',
        'department',
        'level',
        'code',
        'description',
        'description_ms',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'level' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Get the parent category.
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Get all descendants recursively.
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get all ancestors up to the root.
     */
    public function ancestors()
    {
        $ancestors = collect([]);
        $category = $this->parent;

        while ($category) {
            $ancestors->push($category);
            $category = $category->parent;
        }

        return $ancestors;
    }

    /**
     * Scope to get only active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by department.
     */
    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Scope to filter by level.
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope to get root categories (level 1).
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')->orderBy('sort_order');
    }

    /**
     * Get the full category path.
     */
    public function getFullPathAttribute()
    {
        $path = collect([$this->name]);
        $ancestors = $this->ancestors();

        foreach ($ancestors->reverse() as $ancestor) {
            $path->prepend($ancestor->name);
        }

        return $path->implode(' > ');
    }

    /**
     * Get category tree for a specific department.
     */
    public static function getTree($department = null)
    {
        $query = static::roots()->active()->with('descendants');

        if ($department) {
            $query->where('department', $department);
        }

        return $query->get();
    }
}
