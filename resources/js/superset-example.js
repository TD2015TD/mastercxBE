/**
 * Example: How to use Superset embedding in your application
 *
 * This file shows different ways to embed Superset dashboards
 */

import {
    embedSupersetDashboard,
    embedSupersetDashboardWithRefresh
} from './superset.js';

/**
 * Example 1: Basic dashboard embedding
 */
export function embedBasicDashboard() {
    const dashboardId = 'your-dashboard-uuid-here';

    embedSupersetDashboard('superset-container', dashboardId, {
        hideTitle: false,
        hideTab: false,
        hideChartControls: false,
    }).catch(error => {
        console.error('Failed to embed dashboard:', error);
    });
}

/**
 * Example 2: Dashboard with custom UI config
 */
export function embedCustomDashboard() {
    const dashboardId = 'your-dashboard-uuid-here';

    embedSupersetDashboardWithRefresh('superset-container', dashboardId, {
        hideTitle: true,           // Hide the dashboard title
        hideTab: true,             // Hide the tab bar
        hideChartControls: true,   // Hide chart controls
        filtersExpanded: false,    // Collapse filters by default
        filtersVisible: true,      // But keep them visible
    }).catch(error => {
        console.error('Failed to embed dashboard:', error);
    });
}

/**
 * Example 3: Dashboard with Row Level Security
 */
export function embedDashboardWithRLS() {
    const dashboardId = 'your-dashboard-uuid-here';

    embedSupersetDashboardWithRefresh('superset-container', dashboardId, {
        rls: {
            // Apply filters at the dataset level
            1: [{ clause: "customer_id = 123" }],  // dataset_id: filters
            2: [{ clause: "region = 'North'" }],
        }
    }).catch(error => {
        console.error('Failed to embed dashboard:', error);
    });
}

/**
 * Example 4: Dynamic dashboard loading based on user selection
 */
export function setupDynamicDashboard() {
    const selectElement = document.getElementById('dashboard-select');
    const containerElement = document.getElementById('superset-container');

    selectElement.addEventListener('change', async (event) => {
        const dashboardId = event.target.value;

        // Clear the container
        containerElement.innerHTML = '<div class="loading">Loading dashboard...</div>';

        try {
            await embedSupersetDashboardWithRefresh('superset-container', dashboardId);
        } catch (error) {
            containerElement.innerHTML = `<div class="error">Failed to load dashboard: ${error.message}</div>`;
        }
    });
}

/**
 * Example 5: Multiple dashboards on the same page
 */
export function embedMultipleDashboards() {
    const dashboards = [
        { containerId: 'dashboard-1', dashboardId: 'uuid-1' },
        { containerId: 'dashboard-2', dashboardId: 'uuid-2' },
        { containerId: 'dashboard-3', dashboardId: 'uuid-3' },
    ];

    dashboards.forEach(({ containerId, dashboardId }) => {
        embedSupersetDashboardWithRefresh(containerId, dashboardId, {
            hideTitle: true,
            hideTab: true,
        }).catch(error => {
            console.error(`Failed to embed dashboard ${dashboardId}:`, error);
            document.getElementById(containerId).innerHTML =
                `<div class="error">Failed to load dashboard</div>`;
        });
    });
}

// Export all examples
export default {
    embedBasicDashboard,
    embedCustomDashboard,
    embedDashboardWithRLS,
    setupDynamicDashboard,
    embedMultipleDashboards,
};
