<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupersetDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', app()->getLocale());

        return [
            'id' => $this->id,
            'superset_id' => $this->superset_id,
            'slug' => $this->slug,

            // Localized fields
            'title' => $this->getTitle($locale),
            'description' => $this->getDescription($locale),

            // All language versions
            'title_en' => $this->title_en,
            'title_ms' => $this->title_ms,
            'description_en' => $this->description_en,
            'description_ms' => $this->description_ms,

            // Metadata
            'category' => $this->category,
            'icon' => $this->icon,
            'order' => $this->order,
            'is_active' => $this->is_active,
            'is_public' => $this->is_public,

            // Configuration
            'ui_config' => $this->ui_config,
            'default_filters' => $this->default_filters,
            'rls_config' => $this->rls_config,

            // Tags as array
            'tags' => $this->getTagsArray(),
            'thumbnail_url' => $this->thumbnail_url,

            // Relationships
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                        'permissions' => [
                            'can_view' => $role->pivot->can_view,
                            'can_export' => $role->pivot->can_export,
                            'can_share' => $role->pivot->can_share,
                            'custom_rls' => $role->pivot->custom_rls,
                        ],
                    ];
                });
            }),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at),
        ];
    }
}
