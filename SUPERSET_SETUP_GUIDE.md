# Superset Dashboard Management - Quick Setup Guide

## Overview

This system provides complete dashboard management with:
- ✅ Bilingual support (English & Malay)
- ✅ Role-based access control (RBAC)
- ✅ Row Level Security (RLS)
- ✅ Guest token authentication
- ✅ Dashboard metadata storage
- ✅ Granular permissions (view, export, share)

## Quick Start

### 1. Environment Setup

Add to your `.env` file:

```env
# Backend Superset credentials
SUPERSET_URL=http://localhost:8088
SUPERSET_USERNAME=admin
SUPERSET_PASSWORD=admin

# Frontend Superset URL
VITE_SUPERSET_URL=http://localhost:8088
```

### 2. Run Migrations

```bash
php artisan migrate
```

Creates:
- `superset_dashboards` - Dashboard metadata table
- `role_superset_dashboard` - Role-dashboard pivot table

### 3. Seed Sample Data (Optional)

```bash
php artisan db:seed --class=SupersetDashboardSeeder
```

**Important:** Update the `superset_id` values in the seeder with your actual dashboard UUIDs.

### 4. Build Frontend Assets

```bash
npm run dev
# or for production
npm run build
```

## API Endpoints

All endpoints are under `/api/v1/superset/` and require JWT authentication:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/dashboard-configs` | Get all dashboards |
| GET | `/my-dashboards` | Get user's accessible dashboards |
| GET | `/dashboard-configs/{id}` | Get single dashboard |
| POST | `/dashboard-configs` | Create dashboard |
| PUT | `/dashboard-configs/{id}` | Update dashboard |
| DELETE | `/dashboard-configs/{id}` | Delete dashboard |
| POST | `/dashboard-configs/{id}/assign-roles` | Assign roles |
| GET | `/categories` | Get all categories |
| POST | `/guest-token` | Generate guest token (with RLS) |

## Example: Creating a Dashboard

```bash
curl -X POST http://localhost:8000/api/v1/superset/dashboard-configs \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "superset_id": "your-superset-dashboard-uuid",
    "slug": "sales-dashboard",
    "title_en": "Sales Dashboard",
    "title_ms": "Papan Pemuka Jualan",
    "description_en": "Sales metrics and analytics",
    "description_ms": "Metrik dan analitik jualan",
    "category": "Sales",
    "icon": "chart-line",
    "order": 1,
    "is_active": true,
    "is_public": false,
    "ui_config": {
      "hideTitle": false,
      "hideTab": false,
      "hideChartControls": false,
      "filtersExpanded": true
    },
    "roles": [
      {
        "role_id": 1,
        "can_view": true,
        "can_export": true,
        "can_share": false
      }
    ]
  }'
```

## Example: Frontend Integration

```javascript
import { embedSupersetDashboardWithRefresh } from './resources/js/superset.js';

// 1. Get user's dashboards
const response = await fetch('/api/v1/superset/my-dashboards', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept-Language': 'ms' // or 'en'
  }
});

const { dashboards } = await response.json();

// 2. Embed a dashboard
const dashboard = dashboards[0];
await embedSupersetDashboardWithRefresh(
  'dashboard-container',
  dashboard.superset_id,
  dashboard.ui_config
);
```

## File Structure

```
app/
├── Models/
│   ├── SupersetDashboard.php      # Dashboard model
│   └── Role.php                   # Updated with dashboard relationship
├── Http/Controllers/Api/
│   ├── SupersetController.php     # Guest token generation (enhanced)
│   └── SupersetDashboardController.php  # Dashboard CRUD operations

database/
├── migrations/
│   └── *_create_superset_dashboards_table.php
└── seeders/
    └── SupersetDashboardSeeder.php

resources/
├── js/
│   ├── superset.js               # Superset SDK utilities
│   └── superset-example.js       # Usage examples
└── views/
    └── superset-example.blade.php # HTML example

routes/
└── api.php                       # API routes added

Documentation:
├── SUPERSET_INTEGRATION_GUIDE.md      # SDK integration guide
└── SUPERSET_DASHBOARD_MANAGEMENT.md   # Dashboard management guide
```

## Key Features

### 1. Bilingual Support

All dashboards have English and Malay versions:

```php
// Backend
$dashboard->getTitle('ms'); // Returns Malay title
$dashboard->getDescription('en'); // Returns English description

// Frontend API - set language header
headers: { 'Accept-Language': 'ms' }
```

### 2. Role-Based Access Control

```php
// Check if role has access
$dashboard->hasRoleAccess($roleId);

// Get role permissions
$permissions = $dashboard->getRolePermissions($roleId);
// Returns: ['can_view' => true, 'can_export' => false, ...]
```

### 3. Row Level Security

RLS rules cascade in this order:
1. Frontend request RLS
2. Dashboard-level RLS (`rls_config`)
3. Role-specific RLS (`custom_rls`)

```php
// Dashboard-level RLS
'rls_config' => [
    ['dataset' => 1, 'clause' => 'status = "active"']
]

// Role-specific RLS (in pivot table)
'custom_rls' => [
    ['dataset' => 1, 'clause' => 'agent_id = ${user.id}']
]
```

### 4. Permissions

Three levels of granular permissions:
- `can_view` - View the dashboard
- `can_export` - Export data from dashboard
- `can_share` - Share dashboard with others

## Common Tasks

### Get Dashboards for Current User

```php
$user = auth()->user();
$role = Role::with('activeSupersetDashboards')->find($user->role_id);
$dashboards = $role->activeSupersetDashboards()->get();
```

### Create Dashboard with Role Assignment

```php
$dashboard = SupersetDashboard::create([
    'superset_id' => 'uuid',
    'slug' => 'my-dashboard',
    'title_en' => 'My Dashboard',
    'title_ms' => 'Papan Pemuka Saya',
    // ... other fields
]);

// Assign to admin role with full permissions
$dashboard->roles()->attach($adminRole->id, [
    'can_view' => true,
    'can_export' => true,
    'can_share' => true,
    'custom_rls' => null,
]);
```

### Update Dashboard Metadata

```php
$dashboard->update([
    'is_active' => true,
    'order' => 5,
    'category' => 'Analytics',
]);
```

## Testing

### Test Guest Token with Role Access

```bash
# Should succeed if user has access
curl -X POST http://localhost:8000/api/v1/superset/guest-token \
  -H "Authorization: Bearer USER_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"dashboard_id": "dashboard-uuid"}'

# Should return 403 if no access
```

### Test Bilingual Response

```bash
# English
curl -X GET http://localhost:8000/api/v1/superset/my-dashboards \
  -H "Authorization: Bearer TOKEN" \
  -H "Accept-Language: en"

# Malay
curl -X GET http://localhost:8000/api/v1/superset/my-dashboards \
  -H "Authorization: Bearer TOKEN" \
  -H "Accept-Language: ms"
```

## Next Steps

1. ✅ Configure Superset instance (see [SUPERSET_INTEGRATION_GUIDE.md](SUPERSET_INTEGRATION_GUIDE.md))
2. ✅ Run migrations
3. ✅ Update seeder with real dashboard UUIDs
4. ✅ Run seeder
5. ✅ Test API endpoints
6. ✅ Integrate into your frontend
7. ✅ Configure RLS rules as needed

## Documentation

- **[SUPERSET_INTEGRATION_GUIDE.md](SUPERSET_INTEGRATION_GUIDE.md)** - Superset SDK setup and embedding
- **[SUPERSET_DASHBOARD_MANAGEMENT.md](SUPERSET_DASHBOARD_MANAGEMENT.md)** - Complete API reference and examples

## Support

For issues:
1. Check Superset logs: `docker logs superset_app`
2. Check Laravel logs: `storage/logs/laravel.log`
3. Verify role assignments in database
4. Test RLS rules in Superset directly first
