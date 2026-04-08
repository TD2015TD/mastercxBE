# Permission System Implementation Guide

## Overview
A complete permission-based access control system has been implemented for the CRM. This allows fine-grained control over what users can do based on their assigned permissions.

## Backend Implementation

### 1. User Permission Methods (app/Models/User.php)

The User model now includes these permission methods:

```php
// Check single permission
$user->hasPermission('ticket.edit');

// Check if user has ANY of the permissions
$user->hasAnyPermission(['ticket.edit', 'ticket.delete']);

// Check if user has ALL permissions
$user->hasAllPermissions(['ticket.edit', 'ticket.delete']);

// Get all user permissions
$permissions = $user->getPermissions(); // Returns collection of permission slugs

// Check if super admin (bypasses all permission checks)
$user->isSuperAdmin();

// Laravel's built-in can() method now checks permissions
$user->can('ticket.edit');
```

### 2. Permission Middleware

Two middleware classes have been created:

- **CheckPermission** - Requires a single permission
- **CheckAnyPermission** - Requires at least one of multiple permissions

#### Usage in Routes (routes/api.php)

```php
// Single permission required
Route::get('/users', [UserController::class, 'index'])
    ->middleware('permission:user.manage');

// Any of multiple permissions
Route::get('/tickets', [TicketController::class, 'index'])
    ->middleware('permission.any:ticket.view,ticket.edit');

// In route groups
Route::middleware(['permission:user.manage'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
});
```

#### Usage in Controllers

```php
public function someMethod(Request $request)
{
    $user = $request->user();
    
    // Check permission manually
    if (!$user->hasPermission('ticket.delete')) {
        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to delete tickets'
        ], 403);
    }
    
    // Continue with action...
}
```

### 3. Available Permissions

The system includes 36 permissions across 6 modules:

**Ticket Management:**
- ticket.view
- ticket.create
- ticket.edit
- ticket.delete
- ticket.assign
- ticket.escalate
- ticket.close

**Customer Management:**
- customer.view
- customer.create
- customer.edit
- customer.delete
- customer.export

**Reporting & Analytics:**
- report.view
- report.create
- report.export
- analytics.view
- kpi.view

**System Administration:**
- user.manage
- role.manage
- sla.manage
- category.manage
- system.config
- audit.view

**Knowledge Base:**
- kb.view
- kb.create
- kb.edit
- kb.delete
- kb.approve

**Telephony (Mitel):**
- mitel.view
- mitel.call
- mitel.transfer
- mitel.recording
- mitel.queue

### 4. Database Seeding

```bash
# Seed all permissions
php artisan db:seed --class=PermissionSeeder

# Assign default permissions to roles
php artisan db:seed --class=RolePermissionSeeder
```

## Frontend Implementation

### 1. Permission Utilities (src/utils/permissions.js)

```javascript
import { hasPermission, hasAnyPermission, hasAllPermissions, canPerform } from '../utils/permissions';

// Check single permission
const canEdit = hasPermission(permissions, 'ticket.edit');

// Check any permission
const canManage = hasAnyPermission(permissions, ['ticket.edit', 'ticket.delete']);

// Check all permissions
const hasFullAccess = hasAllPermissions(permissions, ['ticket.edit', 'ticket.delete']);

// Check with super admin bypass
const allowed = canPerform(user, permissions, 'ticket.edit');
```

### 2. Can Component (src/components/Can.jsx)

Use the `<Can>` component to conditionally render UI elements:

```jsx
import Can from '../components/Can';

// Single permission
<Can permission="ticket.edit">
  <button>Edit Ticket</button>
</Can>

// Any of multiple permissions
<Can anyOf={['ticket.edit', 'ticket.delete']}>
  <button>Manage Ticket</button>
</Can>

// All permissions required
<Can allOf={['ticket.edit', 'ticket.assign']}>
  <button>Edit & Assign</button>
</Can>

// With fallback content
<Can 
  permission="user.manage"
  fallback={<p>You don't have access to this feature</p>}
>
  <UserManagementPanel />
</Can>
```

### 3. usePermission Hook

Use the hook for permission checks in functional components:

```jsx
import { usePermission } from '../components/Can';

function MyComponent() {
  const { can, canAny, canAll, isSuperAdmin, permissions } = usePermission();

  const handleEdit = () => {
    if (!can('ticket.edit')) {
      toast.error('You do not have permission to edit tickets');
      return;
    }
    // Proceed with edit...
  };

  return (
    <div>
      {can('ticket.create') && <CreateButton />}
      {canAny(['ticket.edit', 'ticket.delete']) && <ManageButton />}
      {isSuperAdmin() && <AdminPanel />}
    </div>
  );
}
```

### 4. AuthContext Integration

The AuthContext provides user and permissions throughout the app:

```jsx
import { useAuth } from '../contexts/AuthContext';

function MyComponent() {
  const { user, permissions, isAuthenticated } = useAuth();

  return (
    <div>
      <p>User: {user?.name}</p>
      <p>Permissions: {permissions.length}</p>
    </div>
  );
}
```

#### Wrap your app with AuthProvider:

```jsx
// In main.jsx or App.jsx
import { AuthProvider } from './contexts/AuthContext';

<AuthProvider>
  <App />
</AuthProvider>
```

## Managing Permissions

### Via UI (System Administration > RBAC > Permission Matrix)

1. Navigate to System Administration
2. Click on RBAC Management
3. Select "Permission Matrix" tab
4. Choose a role
5. Toggle permissions on/off
6. Click "Save Changes"

### Via API

```javascript
// Update role permissions
await api.post(`/roles/${roleId}/permissions`, {
  permissions: [1, 2, 3, 5, 8] // Array of permission IDs
});
```

## Example: Protecting a Route

### Backend (Laravel)

```php
// routes/api.php
Route::middleware(['jwt.verify', 'permission:user.manage'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
});
```

### Frontend (React)

```jsx
import Can from '../components/Can';
import { usePermission } from '../components/Can';

function UserManagement() {
  const { can } = usePermission();

  const handleDeleteUser = (userId) => {
    if (!can('user.manage')) {
      toast.error('You do not have permission to delete users');
      return;
    }
    
    // Proceed with delete...
  };

  return (
    <div>
      <Can permission="user.manage">
        <button onClick={() => navigate('/users/create')}>
          Create User
        </button>
      </Can>

      <Can permission="user.manage" fallback={<p>No access</p>}>
        <UserTable />
      </Can>
    </div>
  );
}
```

## Super Admin Bypass

Users with the `super_admin` role automatically bypass all permission checks and have access to everything.

## Notes

- Permissions are loaded on login and stored in localStorage
- Frontend permissions are checked client-side (UI only)
- Backend permissions are enforced server-side (security)
- Always use both frontend and backend checks for security
- Permission cache is cleared on logout
