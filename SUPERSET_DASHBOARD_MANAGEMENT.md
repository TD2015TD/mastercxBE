# Superset Dashboard Management System

This document describes the dashboard management system for storing, organizing, and controlling access to Superset dashboards with bilingual support (English and Malay) and role-based access control.

## Overview

The system allows you to:
- Store dashboard metadata with bilingual titles and descriptions
- Link dashboards to user roles with granular permissions
- Apply Row Level Security (RLS) rules at dashboard and role levels
- Organize dashboards by categories
- Control dashboard visibility and access

## Database Schema

### `superset_dashboards` Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `superset_id` | string | Superset dashboard UUID (unique) |
| `slug` | string | URL-friendly identifier (unique) |
| `title_en` | string | Dashboard title in English |
| `description_en` | text | Dashboard description in English |
| `title_ms` | string | Dashboard title in Malay |
| `description_ms` | text | Dashboard description in Malay |
| `category` | string | Dashboard category (e.g., Sales, Operations) |
| `icon` | string | Icon class for UI display |
| `order` | integer | Display order (default: 0) |
| `is_active` | boolean | Whether dashboard is active (default: true) |
| `is_public` | boolean | Public access without authentication (default: false) |
| `ui_config` | json | Dashboard UI configuration |
| `default_filters` | json | Default filter values |
| `rls_config` | json | Row Level Security configuration template |
| `tags` | text | Comma-separated tags |
| `thumbnail_url` | string | URL to thumbnail image |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Last update timestamp |
| `deleted_at` | timestamp | Soft delete timestamp |

### `role_superset_dashboard` Pivot Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `role_id` | bigint | Foreign key to roles table |
| `superset_dashboard_id` | bigint | Foreign key to superset_dashboards table |
| `can_view` | boolean | Permission to view dashboard (default: true) |
| `can_export` | boolean | Permission to export data (default: false) |
| `can_share` | boolean | Permission to share dashboard (default: false) |
| `custom_rls` | json | Custom RLS rules for this role |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Last update timestamp |

## API Endpoints

All endpoints are prefixed with `/api/v1/superset/` and require JWT authentication (except where noted).

### Dashboard Management

#### 1. Get All Dashboards
```
GET /api/v1/superset/dashboard-configs
```

**Query Parameters:**
- `category` (string, optional) - Filter by category
- `is_active` (boolean, optional) - Filter by active status
- `is_public` (boolean, optional) - Filter by public status
- `search` (string, optional) - Search in title or tags

**Headers:**
- `Accept-Language: en|ms` - Set response language

**Response:**
```json
{
  "dashboards": [
    {
      "id": 1,
      "superset_id": "sales-overview-dashboard-uuid",
      "slug": "sales-overview",
      "title": "Sales Overview Dashboard",
      "description": "Comprehensive sales metrics...",
      "title_en": "Sales Overview Dashboard",
      "title_ms": "Papan Pemuka Gambaran Jualan",
      "description_en": "Comprehensive sales metrics...",
      "description_ms": "Metrik jualan menyeluruh...",
      "category": "Sales",
      "icon": "chart-line",
      "order": 1,
      "is_active": true,
      "is_public": false,
      "ui_config": {
        "hideTitle": false,
        "hideTab": false,
        "hideChartControls": false,
        "filtersExpanded": true,
        "filtersVisible": true
      },
      "default_filters": {},
      "rls_config": [],
      "tags": ["sales", "revenue", "performance", "kpi"],
      "thumbnail_url": null,
      "roles": [
        {
          "id": 1,
          "name": "Admin",
          "slug": "admin"
        }
      ],
      "created_at": "2025-12-21T00:00:00.000000Z",
      "updated_at": "2025-12-21T00:00:00.000000Z"
    }
  ]
}
```

#### 2. Get My Dashboards
```
GET /api/v1/superset/my-dashboards
```

Returns dashboards accessible to the authenticated user based on their role.

**Headers:**
- `Accept-Language: en|ms` - Set response language
- `Authorization: Bearer {token}` - JWT token

**Response:**
```json
{
  "dashboards": [
    {
      "id": 1,
      "superset_id": "sales-overview-dashboard-uuid",
      "slug": "sales-overview",
      "title": "Sales Overview Dashboard",
      "...": "...",
      "permissions": {
        "can_view": true,
        "can_export": true,
        "can_share": false
      },
      "custom_rls": null
    }
  ]
}
```

#### 3. Get Single Dashboard
```
GET /api/v1/superset/dashboard-configs/{id|slug}
```

**Parameters:**
- `id|slug` - Dashboard ID or slug

**Response:**
```json
{
  "id": 1,
  "superset_id": "sales-overview-dashboard-uuid",
  "slug": "sales-overview",
  "...": "...",
  "permissions": {
    "can_view": true,
    "can_export": true,
    "can_share": false
  }
}
```

#### 4. Create Dashboard
```
POST /api/v1/superset/dashboard-configs
```

**Request Body:**
```json
{
  "superset_id": "my-dashboard-uuid",
  "slug": "my-dashboard",
  "title_en": "My Dashboard",
  "description_en": "Dashboard description",
  "title_ms": "Papan Pemuka Saya",
  "description_ms": "Keterangan papan pemuka",
  "category": "Analytics",
  "icon": "chart-bar",
  "order": 10,
  "is_active": true,
  "is_public": false,
  "ui_config": {
    "hideTitle": false,
    "hideTab": false,
    "hideChartControls": false
  },
  "default_filters": {},
  "rls_config": [],
  "tags": "analytics, custom",
  "roles": [
    {
      "role_id": 1,
      "can_view": true,
      "can_export": true,
      "can_share": false,
      "custom_rls": null
    }
  ]
}
```

**Response:**
```json
{
  "message": "Dashboard created successfully",
  "dashboard": { "..." }
}
```

#### 5. Update Dashboard
```
PUT /api/v1/superset/dashboard-configs/{id}
```

**Request Body:** (all fields optional)
```json
{
  "title_en": "Updated Title",
  "title_ms": "Tajuk Dikemaskini",
  "is_active": true,
  "order": 5
}
```

**Response:**
```json
{
  "message": "Dashboard updated successfully",
  "dashboard": { "..." }
}
```

#### 6. Delete Dashboard
```
DELETE /api/v1/superset/dashboard-configs/{id}
```

**Response:**
```json
{
  "message": "Dashboard deleted successfully"
}
```

#### 7. Assign Roles to Dashboard
```
POST /api/v1/superset/dashboard-configs/{id}/assign-roles
```

**Request Body:**
```json
{
  "roles": [
    {
      "role_id": 1,
      "can_view": true,
      "can_export": true,
      "can_share": true,
      "custom_rls": null
    },
    {
      "role_id": 2,
      "can_view": true,
      "can_export": false,
      "can_share": false,
      "custom_rls": [
        {
          "dataset": 1,
          "clause": "department_id = 5"
        }
      ]
    }
  ]
}
```

**Response:**
```json
{
  "message": "Roles assigned successfully",
  "dashboard": { "..." }
}
```

#### 8. Get Categories
```
GET /api/v1/superset/categories
```

**Response:**
```json
{
  "categories": ["Sales", "Operations", "Analytics", "Executive"]
}
```

### Guest Token Generation (Enhanced)

```
POST /api/v1/superset/guest-token
```

**Request Body:**
```json
{
  "dashboard_id": "my-dashboard-uuid",
  "rls": []
}
```

The endpoint now:
1. Checks if user has role-based access to the dashboard
2. Applies dashboard-level RLS configuration
3. Applies role-specific custom RLS rules
4. Merges all RLS rules before generating token

**Response:**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

## Usage Examples

### 1. Creating a Dashboard

```bash
curl -X POST http://localhost:8000/api/v1/superset/dashboard-configs \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "superset_id": "abc-123-uuid",
    "slug": "team-performance",
    "title_en": "Team Performance",
    "title_ms": "Prestasi Pasukan",
    "description_en": "Track team metrics",
    "description_ms": "Jejak metrik pasukan",
    "category": "Operations",
    "icon": "users",
    "order": 1,
    "is_active": true,
    "is_public": false,
    "roles": [
      {
        "role_id": 1,
        "can_view": true,
        "can_export": true,
        "can_share": true
      }
    ]
  }'
```

### 2. Getting User's Dashboards

```javascript
// Frontend example
const response = await fetch('/api/v1/superset/my-dashboards', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept-Language': 'ms' // or 'en'
  }
});

const { dashboards } = await response.json();

dashboards.forEach(dashboard => {
  console.log(dashboard.title); // Returns Malay title
  console.log(dashboard.permissions); // User's permissions
});
```

### 3. Embedding Dashboard with RLS

```javascript
import { embedSupersetDashboardWithRefresh } from './resources/js/superset.js';

// Get dashboard metadata
const dashboard = await fetch('/api/v1/superset/dashboard-configs/sales-overview')
  .then(r => r.json());

// Embed with configuration from database
await embedSupersetDashboardWithRefresh(
  'container-id',
  dashboard.superset_id,
  {
    ...dashboard.ui_config,
    // RLS will be automatically applied by backend when fetching token
  }
);
```

## Migration and Seeding

### Run Migrations

```bash
php artisan migrate
```

This creates:
- `superset_dashboards` table
- `role_superset_dashboard` pivot table

### Seed Sample Dashboards

```bash
php artisan db:seed --class=SupersetDashboardSeeder
```

This creates 6 sample dashboards:
1. Sales Overview Dashboard
2. Agent Performance Dashboard
3. Customer Satisfaction Dashboard
4. SLA Compliance Dashboard
5. Ticket Analytics Dashboard
6. Executive Summary Dashboard

**Note:** Update the `superset_id` values in the seeder with your actual Superset dashboard UUIDs.

## Model Usage

### SupersetDashboard Model

```php
use App\Models\SupersetDashboard;

// Get all active dashboards
$dashboards = SupersetDashboard::active()->ordered()->get();

// Get dashboards by category
$salesDashboards = SupersetDashboard::active()
    ->byCategory('Sales')
    ->get();

// Get localized title
$dashboard = SupersetDashboard::find(1);
$title = $dashboard->getTitle('ms'); // Returns Malay title
$description = $dashboard->getDescription('en'); // Returns English description

// Get tags as array
$tags = $dashboard->getTagsArray();

// Check role access
$hasAccess = $dashboard->hasRoleAccess($roleId);

// Get role permissions
$permissions = $dashboard->getRolePermissions($roleId);
```

### Role Model

```php
use App\Models\Role;

$role = Role::find(1);

// Get all dashboards for this role
$dashboards = $role->supersetDashboards;

// Get only active dashboards
$activeDashboards = $role->activeSupersetDashboards()->get();

// Check permissions
foreach ($dashboards as $dashboard) {
    echo $dashboard->pivot->can_view; // true/false
    echo $dashboard->pivot->can_export; // true/false
    echo $dashboard->pivot->custom_rls; // array or null
}
```

## Row Level Security (RLS)

RLS rules are applied in this order:
1. Request-provided RLS (from frontend)
2. Dashboard-level RLS (`rls_config`)
3. Role-specific custom RLS (`custom_rls` in pivot table)

### Example RLS Configuration

**Dashboard Level:**
```php
$dashboard->rls_config = [
    [
        'dataset' => 1,
        'clause' => "created_at >= '2025-01-01'"
    ]
];
```

**Role Level:**
```php
// Agents only see their own data
$dashboard->roles()->attach($agentRole->id, [
    'can_view' => true,
    'can_export' => false,
    'can_share' => false,
    'custom_rls' => [
        [
            'dataset' => 1,
            'clause' => 'agent_id = ${user.id}'
        ]
    ]
]);
```

## Bilingual Support

The system supports English (`en`) and Malay (`ms`) languages:

### Backend (Laravel)

Set locale:
```php
app()->setLocale('ms');
```

Get localized content:
```php
$dashboard->getTitle(); // Uses app locale
$dashboard->getTitle('ms'); // Force Malay
```

### Frontend (API)

Set language via header:
```javascript
fetch('/api/v1/superset/my-dashboards', {
  headers: {
    'Accept-Language': 'ms'
  }
});
```

## Security Considerations

1. **Authentication**: All dashboard management endpoints require JWT authentication
2. **Authorization**: Guest token endpoint validates role-based access
3. **RLS**: Multiple layers of RLS ensure data isolation
4. **Public Dashboards**: Use `is_public` flag carefully
5. **Permissions**: Granular permissions (view, export, share)

## Best Practices

1. **Always use backend token generation** in production
2. **Set meaningful slugs** for user-friendly URLs
3. **Use categories** to organize dashboards
4. **Apply RLS rules** at dashboard level when possible
5. **Test permissions** before deploying
6. **Update superset_id** in seeder with real UUIDs
7. **Use bilingual content** for all user-facing text
8. **Set appropriate order** for dashboard display

## Troubleshooting

### Dashboard not accessible

1. Check if dashboard is active: `is_active = true`
2. Verify role assignment exists
3. Check `can_view` permission is true
4. Ensure user has correct role assigned

### RLS not working

1. Verify `rls_config` format is correct
2. Check dataset IDs match Superset configuration
3. Test RLS rules in Superset first
4. Check token generation logs

### Localization issues

1. Ensure both `title_en` and `title_ms` are set
2. Check `Accept-Language` header
3. Verify app locale configuration

## Additional Resources

- [Superset Integration Guide](SUPERSET_INTEGRATION_GUIDE.md)
- [Superset RLS Documentation](https://superset.apache.org/docs/security#row-level-security)
- [Laravel Eloquent Relationships](https://laravel.com/docs/eloquent-relationships)
