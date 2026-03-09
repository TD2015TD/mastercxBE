# React Frontend Integration Guide - Superset Dashboard Management

This guide shows how to integrate the Superset Dashboard Management API into your React frontend (`crm-frontend` project).

## Overview

The backend API provides complete CRUD operations for managing Superset dashboards with:
- Bilingual support (English & Malay)
- Role-based access control
- Dashboard metadata management
- Role assignment with granular permissions

## API Endpoints Summary

Base URL: `http://localhost:8000/api/v1/superset/`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/dashboard-configs` | List all dashboards |
| GET | `/my-dashboards` | Get user's accessible dashboards |
| GET | `/dashboard-configs/{id}` | Get single dashboard |
| POST | `/dashboard-configs` | Create dashboard |
| PUT | `/dashboard-configs/{id}` | Update dashboard |
| DELETE | `/dashboard-configs/{id}` | Delete dashboard |
| POST | `/dashboard-configs/{id}/assign-roles` | Assign roles to dashboard |
| GET | `/categories` | Get all categories |
| POST | `/guest-token` | Generate guest token for embedding |

## TypeScript Interfaces

Create these types in your React project:

```typescript
// types/superset.ts

export interface SupersetDashboard {
  id: number;
  superset_id: string;
  slug: string;
  title: string;
  description: string | null;
  title_en: string;
  title_ms: string;
  description_en: string | null;
  description_ms: string | null;
  category: string | null;
  icon: string | null;
  order: number;
  is_active: boolean;
  is_public: boolean;
  ui_config: UIConfig;
  default_filters: Record<string, any>;
  rls_config: RLSRule[];
  tags: string[];
  thumbnail_url: string | null;
  roles: DashboardRole[];
  created_at: string;
  updated_at: string;
  deleted_at?: string | null;
}

export interface UIConfig {
  hideTitle?: boolean;
  hideTab?: boolean;
  hideChartControls?: boolean;
  filtersExpanded?: boolean;
  filtersVisible?: boolean;
}

export interface RLSRule {
  dataset?: number;
  clause?: string;
}

export interface DashboardRole {
  id: number;
  name: string;
  slug: string;
  permissions: RolePermissions;
}

export interface RolePermissions {
  can_view: boolean;
  can_export: boolean;
  can_share: boolean;
  custom_rls: RLSRule[] | null;
}

export interface CreateDashboardRequest {
  superset_id: string;
  slug: string;
  title_en: string;
  title_ms: string;
  description_en?: string;
  description_ms?: string;
  category?: string;
  icon?: string;
  order?: number;
  is_active?: boolean;
  is_public?: boolean;
  ui_config?: UIConfig;
  default_filters?: Record<string, any>;
  rls_config?: RLSRule[];
  tags?: string;
  thumbnail_url?: string;
  roles?: RoleAssignment[];
}

export interface RoleAssignment {
  role_id: number;
  can_view?: boolean;
  can_export?: boolean;
  can_share?: boolean;
  custom_rls?: RLSRule[] | null;
}
```

## API Service

Create an API service for dashboard management:

```typescript
// services/supersetDashboardService.ts

import axios from 'axios';
import { SupersetDashboard, CreateDashboardRequest, RoleAssignment } from '../types/superset';

const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8000/api/v1';

export const supersetDashboardService = {
  /**
   * Get all dashboards with optional filters
   */
  async getDashboards(params?: {
    search?: string;
    category?: string;
    is_active?: boolean;
    is_public?: boolean;
  }): Promise<SupersetDashboard[]> {
    const response = await axios.get(`${API_BASE_URL}/superset/dashboard-configs`, {
      params,
      headers: {
        'Accept-Language': localStorage.getItem('language') || 'en',
      },
    });
    return response.data.dashboards;
  },

  /**
   * Get user's accessible dashboards
   */
  async getMyDashboards(): Promise<SupersetDashboard[]> {
    const response = await axios.get(`${API_BASE_URL}/superset/my-dashboards`, {
      headers: {
        'Accept-Language': localStorage.getItem('language') || 'en',
      },
    });
    return response.data.dashboards;
  },

  /**
   * Get single dashboard by ID or slug
   */
  async getDashboard(id: number | string): Promise<SupersetDashboard> {
    const response = await axios.get(`${API_BASE_URL}/superset/dashboard-configs/${id}`, {
      headers: {
        'Accept-Language': localStorage.getItem('language') || 'en',
      },
    });
    return response.data;
  },

  /**
   * Create new dashboard
   */
  async createDashboard(data: CreateDashboardRequest): Promise<SupersetDashboard> {
    const response = await axios.post(`${API_BASE_URL}/superset/dashboard-configs`, data);
    return response.data.dashboard;
  },

  /**
   * Update dashboard
   */
  async updateDashboard(id: number, data: Partial<CreateDashboardRequest>): Promise<SupersetDashboard> {
    const response = await axios.put(`${API_BASE_URL}/superset/dashboard-configs/${id}`, data);
    return response.data.dashboard;
  },

  /**
   * Delete dashboard
   */
  async deleteDashboard(id: number): Promise<void> {
    await axios.delete(`${API_BASE_URL}/superset/dashboard-configs/${id}`);
  },

  /**
   * Assign roles to dashboard
   */
  async assignRoles(id: number, roles: RoleAssignment[]): Promise<SupersetDashboard> {
    const response = await axios.post(
      `${API_BASE_URL}/superset/dashboard-configs/${id}/assign-roles`,
      { roles }
    );
    return response.data.dashboard;
  },

  /**
   * Get all categories
   */
  async getCategories(): Promise<string[]> {
    const response = await axios.get(`${API_BASE_URL}/superset/categories`);
    return response.data.categories;
  },

  /**
   * Generate guest token for dashboard embedding
   */
  async getGuestToken(dashboardId: string, rls?: RLSRule[]): Promise<string> {
    const response = await axios.post(`${API_BASE_URL}/superset/guest-token`, {
      dashboard_id: dashboardId,
      rls,
    });
    return response.data.token;
  },
};
```

## React Hooks

Create custom hooks for easier state management:

```typescript
// hooks/useSupersetDashboards.ts

import { useState, useEffect } from 'react';
import { SupersetDashboard } from '../types/superset';
import { supersetDashboardService } from '../services/supersetDashboardService';

export const useSupersetDashboards = (filters?: {
  search?: string;
  category?: string;
  is_active?: boolean;
}) => {
  const [dashboards, setDashboards] = useState<SupersetDashboard[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);

  useEffect(() => {
    loadDashboards();
  }, [filters]);

  const loadDashboards = async () => {
    try {
      setLoading(true);
      const data = await supersetDashboardService.getDashboards(filters);
      setDashboards(data);
      setError(null);
    } catch (err) {
      setError(err as Error);
    } finally {
      setLoading(false);
    }
  };

  const createDashboard = async (data: any) => {
    const newDashboard = await supersetDashboardService.createDashboard(data);
    setDashboards([...dashboards, newDashboard]);
    return newDashboard;
  };

  const updateDashboard = async (id: number, data: any) => {
    const updated = await supersetDashboardService.updateDashboard(id, data);
    setDashboards(dashboards.map(d => d.id === id ? updated : d));
    return updated;
  };

  const deleteDashboard = async (id: number) => {
    await supersetDashboardService.deleteDashboard(id);
    setDashboards(dashboards.filter(d => d.id !== id));
  };

  return {
    dashboards,
    loading,
    error,
    loadDashboards,
    createDashboard,
    updateDashboard,
    deleteDashboard,
  };
};
```

## Example React Components

### 1. Dashboard List Page

```typescript
// pages/SupersetDashboardManagement.tsx

import React, { useState } from 'react';
import { useSupersetDashboards } from '../hooks/useSupersetDashboards';
import DashboardTable from '../components/DashboardTable';
import DashboardForm from '../components/DashboardForm';
import { SupersetDashboard } from '../types/superset';

const SupersetDashboardManagement: React.FC = () => {
  const [filters, setFilters] = useState({
    search: '',
    category: '',
    is_active: undefined as boolean | undefined,
  });

  const [editingDashboard, setEditingDashboard] = useState<SupersetDashboard | null>(null);
  const [showForm, setShowForm] = useState(false);

  const {
    dashboards,
    loading,
    error,
    createDashboard,
    updateDashboard,
    deleteDashboard,
  } = useSupersetDashboards(filters);

  const handleCreate = () => {
    setEditingDashboard(null);
    setShowForm(true);
  };

  const handleEdit = (dashboard: SupersetDashboard) => {
    setEditingDashboard(dashboard);
    setShowForm(true);
  };

  const handleSave = async (data: any) => {
    if (editingDashboard) {
      await updateDashboard(editingDashboard.id, data);
    } else {
      await createDashboard(data);
    }
    setShowForm(false);
  };

  const handleDelete = async (dashboard: SupersetDashboard) => {
    if (window.confirm(`Delete "${dashboard.title}"?`)) {
      await deleteDashboard(dashboard.id);
    }
  };

  if (error) {
    return <div className="alert alert-danger">Error: {error.message}</div>;
  }

  return (
    <div className="container-fluid">
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h1>Superset Dashboard Management</h1>
        <button className="btn btn-primary" onClick={handleCreate}>
          <i className="bi bi-plus-circle"></i> Create Dashboard
        </button>
      </div>

      {/* Filters */}
      <div className="card mb-4">
        <div className="card-body">
          <div className="row g-3">
            <div className="col-md-4">
              <input
                type="text"
                className="form-control"
                placeholder="Search dashboards..."
                value={filters.search}
                onChange={(e) => setFilters({ ...filters, search: e.target.value })}
              />
            </div>
            <div className="col-md-3">
              <select
                className="form-select"
                value={filters.category}
                onChange={(e) => setFilters({ ...filters, category: e.target.value })}
              >
                <option value="">All Categories</option>
                <option value="Sales">Sales</option>
                <option value="Operations">Operations</option>
                <option value="Analytics">Analytics</option>
                <option value="Executive">Executive</option>
              </select>
            </div>
            <div className="col-md-3">
              <select
                className="form-select"
                value={filters.is_active === undefined ? '' : filters.is_active.toString()}
                onChange={(e) => setFilters({
                  ...filters,
                  is_active: e.target.value === '' ? undefined : e.target.value === 'true'
                })}
              >
                <option value="">All Status</option>
                <option value="true">Active</option>
                <option value="false">Inactive</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      {/* Dashboard Table */}
      <DashboardTable
        dashboards={dashboards}
        loading={loading}
        onEdit={handleEdit}
        onDelete={handleDelete}
      />

      {/* Form Modal */}
      {showForm && (
        <DashboardForm
          dashboard={editingDashboard}
          onSave={handleSave}
          onCancel={() => setShowForm(false)}
        />
      )}
    </div>
  );
};

export default SupersetDashboardManagement;
```

### 2. Dashboard Table Component

```typescript
// components/DashboardTable.tsx

import React from 'react';
import { SupersetDashboard } from '../types/superset';

interface Props {
  dashboards: SupersetDashboard[];
  loading: boolean;
  onEdit: (dashboard: SupersetDashboard) => void;
  onDelete: (dashboard: SupersetDashboard) => void;
}

const DashboardTable: React.FC<Props> = ({ dashboards, loading, onEdit, onDelete }) => {
  if (loading) {
    return <div className="text-center p-5">Loading...</div>;
  }

  if (dashboards.length === 0) {
    return <div className="text-center p-5 text-muted">No dashboards found</div>;
  }

  return (
    <div className="card">
      <div className="table-responsive">
        <table className="table table-hover mb-0">
          <thead>
            <tr>
              <th>Order</th>
              <th>Title</th>
              <th>Category</th>
              <th>Roles</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {dashboards.map((dashboard) => (
              <tr key={dashboard.id}>
                <td>{dashboard.order}</td>
                <td>
                  <div className="d-flex align-items-center">
                    {dashboard.icon && <i className={`bi bi-${dashboard.icon} me-2`}></i>}
                    <div>
                      <strong>{dashboard.title}</strong>
                      <br />
                      <small className="text-muted">{dashboard.slug}</small>
                    </div>
                  </div>
                </td>
                <td>
                  {dashboard.category && (
                    <span className="badge bg-info">{dashboard.category}</span>
                  )}
                </td>
                <td>
                  <div className="d-flex flex-wrap gap-1">
                    {dashboard.roles.map((role) => (
                      <span key={role.id} className="badge bg-secondary">
                        {role.name}
                      </span>
                    ))}
                  </div>
                </td>
                <td>
                  <span className={`badge ${dashboard.is_active ? 'bg-success' : 'bg-danger'}`}>
                    {dashboard.is_active ? 'Active' : 'Inactive'}
                  </span>
                </td>
                <td>
                  <div className="btn-group btn-group-sm">
                    <button
                      className="btn btn-outline-primary"
                      onClick={() => onEdit(dashboard)}
                      title="Edit"
                    >
                      <i className="bi bi-pencil"></i>
                    </button>
                    <button
                      className="btn btn-outline-danger"
                      onClick={() => onDelete(dashboard)}
                      title="Delete"
                    >
                      <i className="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};

export default DashboardTable;
```

## Integration with Existing Frontend

### 1. Add to your React Router

```typescript
// App.tsx or routes.tsx

import SupersetDashboardManagement from './pages/SupersetDashboardManagement';

// In your routes:
<Route
  path="/admin/superset-dashboards"
  element={<SupersetDashboardManagement />}
/>
```

### 2. Add to System Administration Menu

Update your sidebar/menu configuration to include:

```typescript
{
  title: 'Superset Dashboards',
  path: '/admin/superset-dashboards',
  icon: 'bi-graph-up',
  permission: 'admin', // or whatever permission system you use
  section: 'System Administration'
}
```

### 3. Axios Configuration

Make sure your axios instance includes JWT token:

```typescript
// services/api.ts

import axios from 'axios';

const api = axios.create({
  baseURL: process.env.REACT_APP_API_URL || 'http://localhost:8000/api/v1',
});

// Add JWT token to requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export default api;
```

## Environment Variables

Add to your `.env` file in `crm-frontend`:

```env
REACT_APP_API_URL=http://localhost:8000/api/v1
REACT_APP_SUPERSET_URL=http://localhost:8088
```

## Quick Start Checklist

1. ✅ Copy TypeScript interfaces to your `crm-frontend/src/types/` folder
2. ✅ Create `supersetDashboardService.ts` in your `services/` folder
3. ✅ Create custom hook `useSupersetDashboards.ts` in your `hooks/` folder
4. ✅ Create dashboard management page in `pages/SupersetDashboardManagement.tsx`
5. ✅ Add route to your router
6. ✅ Add menu item to System Administration section
7. ✅ Run migrations on backend: `php artisan migrate`
8. ✅ (Optional) Seed sample data: `php artisan db:seed --class=SupersetDashboardSeeder`

## Next Steps

After setting up the management page:
1. Update the `superset_id` values in the seeded dashboards with real Superset UUIDs
2. Configure roles and permissions for each dashboard
3. Implement dashboard embedding in your frontend using the embedded SDK
4. Set up proper error handling and loading states
5. Add internationalization (i18n) for bilingual support

## Support

Refer to:
- **[SUPERSET_DASHBOARD_MANAGEMENT.md](SUPERSET_DASHBOARD_MANAGEMENT.md)** - Complete API documentation
- **[SUPERSET_INTEGRATION_GUIDE.md](SUPERSET_INTEGRATION_GUIDE.md)** - Superset SDK setup
- **[SUPERSET_SETUP_GUIDE.md](SUPERSET_SETUP_GUIDE.md)** - Quick setup guide
