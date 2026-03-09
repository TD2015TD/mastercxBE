# Superset Embedded Dashboard Integration Guide

This guide explains how to integrate Apache Superset dashboards into your Laravel application using the `@superset-ui/embedded-sdk` with guest token authentication.

## Overview

The integration consists of:
- **Frontend SDK**: `@superset-ui/embedded-sdk` for embedding dashboards
- **Backend API**: Laravel endpoints for secure guest token generation
- **Authentication**: Guest token approach for secure, row-level security (RLS)

## Setup

### 1. Environment Configuration

Add the following variables to your `.env` file:

```env
# Backend configuration (for API token generation)
SUPERSET_URL=http://localhost:8088
SUPERSET_USERNAME=admin
SUPERSET_PASSWORD=admin

# Frontend configuration (for embedded SDK)
VITE_SUPERSET_URL=http://localhost:8088
```

For development, use `http://localhost:8088`. For production, use your Superset server URL.

**Important:** After adding or changing `VITE_*` variables, you must rebuild your assets:
```bash
npm run dev
# or for production
npm run build
```

### 2. Superset Configuration

Enable guest token authentication in your Superset instance:

1. Edit your `superset_config.py`:

```python
# Enable embedded dashboard features
FEATURE_FLAGS = {
    "EMBEDDED_SUPERSET": True,
}

# Configure guest token settings
GUEST_TOKEN_JWT_SECRET = "your-secret-key-here"  # Use a strong secret
GUEST_TOKEN_JWT_ALGO = "HS256"
GUEST_TOKEN_JWT_EXP_SECONDS = 300  # Token expires in 5 minutes

# CORS settings for embedding
ENABLE_CORS = True
CORS_OPTIONS = {
    'supports_credentials': True,
    'allow_headers': ['*'],
    'resources': ['*'],
    'origins': ['http://localhost:8000', 'http://localhost:3000']  # Your app URLs
}

# Embedding settings
GUEST_ROLE_NAME = "Public"  # Create this role in Superset
```

2. Create a "Public" role in Superset with appropriate permissions:
   - Navigate to **Settings > List Roles**
   - Create role named "Public"
   - Grant permissions to view specific dashboards

3. Restart Superset after configuration changes

### 3. Install Dependencies

The package is already installed via npm:

```bash
npm install @superset-ui/embedded-sdk
```

## Usage

### Method 1: Frontend-Only (Direct Token Fetch)

This method fetches guest tokens directly from Superset. Less secure but simpler for development.

```javascript
import { embedSupersetDashboardWithRefresh } from './resources/js/superset.js';

const DASHBOARD_ID = 'your-dashboard-uuid';

embedSupersetDashboardWithRefresh('superset-container', DASHBOARD_ID, {
    hideTitle: false,
    hideTab: false,
    hideChartControls: false,
    filtersExpanded: true,
    filtersVisible: true,
}).catch(error => {
    console.error('Failed to embed dashboard:', error);
});
```

### Method 2: Backend Token Generation (Recommended)

This method uses your Laravel backend to generate tokens. More secure as credentials stay server-side.

**Update [resources/js/superset.js](resources/js/superset.js:84)** to use `fetchGuestTokenFromBackend`:

```javascript
export async function embedSupersetDashboardWithRefresh(containerId, dashboardId, options = {}) {
    try {
        await embedDashboard({
            id: dashboardId,
            supersetDomain: SUPERSET_DOMAIN,
            mountPoint: document.getElementById(containerId),
            fetchGuestToken: async () => {
                // Use backend token generation
                return await fetchGuestTokenFromBackend(dashboardId);
            },
            dashboardUiConfig: {
                hideTitle: options.hideTitle || false,
                hideTab: options.hideTab || false,
                hideChartControls: options.hideChartControls || false,
                filters: {
                    expanded: options.filtersExpanded !== undefined ? options.filtersExpanded : true,
                    visible: options.filtersVisible !== undefined ? options.filtersVisible : true,
                },
            },
        });
    } catch (error) {
        console.error('Error embedding dashboard:', error);
        throw error;
    }
}
```

### Method 3: With Row Level Security (RLS)

Apply data filters based on the authenticated user:

```javascript
embedSupersetDashboardWithRefresh('superset-container', DASHBOARD_ID, {
    rls: {
        // Filter by dataset ID
        1: [{ clause: "customer_id = 123" }],
        2: [{ clause: "region = 'North'" }],
    }
}).catch(error => {
    console.error('Failed to embed dashboard:', error);
});
```

**Backend RLS (in SupersetController):**

```php
// Example: Filter by user's organization
if ($user && $user->organization_id) {
    $rls[] = [
        'dataset' => 1,  // Your dataset ID
        'clause' => "organization_id = {$user->organization_id}"
    ];
}
```

## API Endpoints

### POST `/api/v1/superset/guest-token`

Generate a guest token for embedding a dashboard.

**Request:**
```json
{
    "dashboard_id": "uuid-of-dashboard",
    "rls": {
        "1": [{"clause": "customer_id = 123"}]
    }
}
```

**Response:**
```json
{
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

### GET `/api/v1/superset/dashboards`

Get list of available dashboards from Superset.

**Response:**
```json
{
    "dashboards": [
        {
            "id": 1,
            "uuid": "dashboard-uuid",
            "dashboard_title": "Sales Dashboard",
            "url": "/superset/dashboard/1/",
            "published": true
        }
    ]
}
```

## File Structure

```
resources/
├── js/
│   ├── superset.js              # Main Superset integration utilities
│   └── superset-example.js      # Usage examples
└── views/
    └── superset-example.blade.php  # Full HTML example

app/Http/Controllers/Api/
└── SupersetController.php       # Backend guest token API
```

## HTML Example

See [resources/views/superset-example.blade.php](resources/views/superset-example.blade.php) for a complete example.

```html
<div id="superset-dashboard" style="width: 100%; height: 800px;"></div>

<script type="module">
    import { embedSupersetDashboardWithRefresh } from './resources/js/superset.js';

    const DASHBOARD_ID = 'your-dashboard-uuid-here';

    embedSupersetDashboardWithRefresh('superset-dashboard', DASHBOARD_ID, {
        hideTitle: false,
        filtersExpanded: true,
    }).catch(error => {
        console.error('Error:', error);
    });
</script>
```

## Dashboard UI Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `hideTitle` | boolean | false | Hide the dashboard title |
| `hideTab` | boolean | false | Hide the tab bar |
| `hideChartControls` | boolean | false | Hide chart control buttons |
| `filtersExpanded` | boolean | true | Expand filters by default |
| `filtersVisible` | boolean | true | Show/hide filters |
| `rls` | object | {} | Row Level Security rules |

## Getting Dashboard UUIDs

### Method 1: From Superset UI
1. Navigate to your dashboard in Superset
2. Look at the URL: `http://localhost:8088/superset/dashboard/123/`
3. The number `123` is the dashboard ID (or use the UUID if available)

### Method 2: Via API
```bash
curl -X GET "http://localhost:8088/api/v1/dashboard/" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

### Method 3: Using the Laravel Endpoint
```bash
curl -X GET "http://localhost:8000/api/v1/superset/dashboards" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## Troubleshooting

### CORS Errors

If you see CORS errors in the browser console:

1. Check your Superset `superset_config.py` CORS settings
2. Ensure your app URL is in the `origins` list
3. Restart Superset after changes

### Token Expiration

Guest tokens expire after the configured time (default 5 minutes). Use `embedSupersetDashboardWithRefresh` to automatically refresh tokens.

### Authentication Errors

Check:
1. Superset credentials in `.env` are correct
2. Superset is running and accessible
3. Guest token feature is enabled in Superset config
4. "Public" role exists with proper permissions

### Dashboard Not Loading

1. Verify dashboard UUID is correct
2. Check browser console for errors
3. Ensure the "Public" role has permission to view the dashboard
4. Check network tab for failed requests

## Security Best Practices

1. **Use Backend Token Generation**: Always use the Laravel backend endpoint in production
2. **Implement RLS**: Apply row-level security to restrict data access
3. **Validate Permissions**: Check user permissions before generating tokens
4. **Secure Credentials**: Never expose Superset credentials in frontend code
5. **HTTPS in Production**: Use HTTPS for both Laravel and Superset in production
6. **Token Expiration**: Keep token expiration short (5-10 minutes)
7. **Audit Logging**: Log token generation requests for security audits

## Advanced Examples

### Multiple Dashboards on One Page

```javascript
const dashboards = [
    { containerId: 'dashboard-1', dashboardId: 'uuid-1' },
    { containerId: 'dashboard-2', dashboardId: 'uuid-2' },
];

dashboards.forEach(({ containerId, dashboardId }) => {
    embedSupersetDashboardWithRefresh(containerId, dashboardId, {
        hideTitle: true,
    });
});
```

### Dynamic Dashboard Selection

```javascript
document.getElementById('dashboard-select').addEventListener('change', (event) => {
    const dashboardId = event.target.value;
    embedSupersetDashboardWithRefresh('superset-container', dashboardId);
});
```

### User-Specific RLS

In your Laravel controller:

```php
public function getGuestToken(Request $request)
{
    $user = $request->user();

    $rls = [];

    // Filter by user's department
    if ($user->department_id) {
        $rls[] = [
            'dataset' => 1,
            'clause' => "department_id = {$user->department_id}"
        ];
    }

    // Filter by date range
    $rls[] = [
        'dataset' => 1,
        'clause' => "created_at >= '{$user->created_at}'"
    ];

    // Continue with token generation...
}
```

## Resources

- [Superset Embedded SDK Documentation](https://superset.apache.org/docs/installation/running-on-docker)
- [Guest Token Authentication](https://superset.apache.org/docs/security#guest-token)
- [Superset API Documentation](https://superset.apache.org/docs/api)

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review Superset logs: `docker logs superset_app`
3. Check Laravel logs: `storage/logs/laravel.log`
4. Enable debug mode in `.env`: `APP_DEBUG=true`
