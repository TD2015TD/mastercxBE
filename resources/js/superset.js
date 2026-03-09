/**
 * Superset Embedded SDK Integration
 *
 * This module provides utilities for embedding Superset dashboards
 * using guest token authentication.
 */

import { embedDashboard } from '@superset-ui/embedded-sdk';

// Superset configuration - read from environment variable
const SUPERSET_DOMAIN = import.meta.env.VITE_SUPERSET_URL || 'http://localhost:8088';

/**
 * Fetches a guest token from the Superset backend
 *
 * @param {string} dashboardId - The UUID of the dashboard to embed
 * @param {Object} rls - Row Level Security rules (optional)
 * @returns {Promise<string>} The guest token
 */
export async function fetchGuestToken(dashboardId, rls = {}) {
    const response = await fetch(`${SUPERSET_DOMAIN}/api/v1/security/guest_token/`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            resources: [{
                type: 'dashboard',
                id: dashboardId
            }],
            rls: rls,
            user: {
                username: 'guest',
                first_name: 'Guest',
                last_name: 'User'
            }
        })
    });

    if (!response.ok) {
        throw new Error(`Failed to fetch guest token: ${response.statusText}`);
    }

    const data = await response.json();
    return data.token;
}

/**
 * Alternative: Fetch guest token from your Laravel backend
 * This is more secure as it keeps Superset credentials server-side
 *
 * @param {string} dashboardId - The UUID of the dashboard to embed
 * @returns {Promise<string>} The guest token
 */
export async function fetchGuestTokenFromBackend(dashboardId) {
    const response = await fetch('/api/superset/guest-token', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ dashboard_id: dashboardId })
    });

    if (!response.ok) {
        throw new Error(`Failed to fetch guest token: ${response.statusText}`);
    }

    const data = await response.json();
    return data.token;
}

/**
 * Embeds a Superset dashboard into a DOM element
 *
 * @param {string} containerId - The ID of the container element
 * @param {string} dashboardId - The UUID of the dashboard to embed
 * @param {Object} options - Additional embedding options
 * @returns {Promise<void>}
 */
export async function embedSupersetDashboard(containerId, dashboardId, options = {}) {
    try {
        // Fetch guest token (choose one of the methods)
        const guestToken = await fetchGuestToken(dashboardId, options.rls);
        // OR use backend:
        // const guestToken = await fetchGuestTokenFromBackend(dashboardId);

        // Embed the dashboard
        await embedDashboard({
            id: dashboardId,
            supersetDomain: SUPERSET_DOMAIN,
            mountPoint: document.getElementById(containerId),
            fetchGuestToken: () => guestToken,
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

        console.log('Dashboard embedded successfully');
    } catch (error) {
        console.error('Error embedding dashboard:', error);
        throw error;
    }
}

/**
 * More flexible embedding with dynamic token refresh
 *
 * @param {string} containerId - The ID of the container element
 * @param {string} dashboardId - The UUID of the dashboard to embed
 * @param {Object} options - Additional embedding options
 * @returns {Promise<void>}
 */
export async function embedSupersetDashboardWithRefresh(containerId, dashboardId, options = {}) {
    try {
        await embedDashboard({
            id: dashboardId,
            supersetDomain: SUPERSET_DOMAIN,
            mountPoint: document.getElementById(containerId),
            fetchGuestToken: async () => {
                // This function will be called whenever a new token is needed
                return await fetchGuestToken(dashboardId, options.rls);
                // OR use backend:
                // return await fetchGuestTokenFromBackend(dashboardId);
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

        console.log('Dashboard embedded successfully with token refresh');
    } catch (error) {
        console.error('Error embedding dashboard:', error);
        throw error;
    }
}

export default {
    embedSupersetDashboard,
    embedSupersetDashboardWithRefresh,
    fetchGuestToken,
    fetchGuestTokenFromBackend,
};
