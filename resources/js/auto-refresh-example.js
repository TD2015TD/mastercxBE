/**
 * Auto Token Refresh - Frontend Integration Example
 *
 * This file demonstrates how to integrate auto-refresh token functionality
 * in your frontend application (React, Vue, or vanilla JavaScript).
 */

// ============================================================================
// Example 1: Using Axios (Recommended)
// ============================================================================

import axios from 'axios';

// Create axios instance
const api = axios.create({
  baseURL: 'http://localhost:8000/api/v1',
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor - Add token to all requests
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('access_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor - Handle auto-refreshed tokens
api.interceptors.response.use(
  (response) => {
    // Check if the token was auto-refreshed by the server
    if (response.headers['x-token-refreshed'] === 'true') {
      const authHeader = response.headers['authorization'];
      if (authHeader && authHeader.startsWith('Bearer ')) {
        const newToken = authHeader.substring(7); // Remove 'Bearer ' prefix

        // Store the new token
        localStorage.setItem('access_token', newToken);

        console.log('Token auto-refreshed successfully');

        // Optional: Dispatch event for other parts of your app
        window.dispatchEvent(new CustomEvent('token-refreshed', {
          detail: { token: newToken }
        }));
      }
    }
    return response;
  },
  (error) => {
    // Handle authentication errors
    if (error.response && error.response.status === 401) {
      const message = error.response.data.message;

      // Token expired and cannot be refreshed
      if (message.includes('expired and cannot be refreshed')) {
        console.log('Session expired. Redirecting to login...');

        // Clear stored token
        localStorage.removeItem('access_token');

        // Redirect to login page
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  }
);

// Export the configured axios instance
export default api;

// ============================================================================
// Example 2: Using Fetch API
// ============================================================================

class ApiClient {
  constructor(baseURL) {
    this.baseURL = baseURL;
  }

  getToken() {
    return localStorage.getItem('access_token');
  }

  setToken(token) {
    localStorage.setItem('access_token', token);
  }

  removeToken() {
    localStorage.removeItem('access_token');
  }

  async request(endpoint, options = {}) {
    const token = this.getToken();

    const config = {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        ...options.headers,
        ...(token && { Authorization: `Bearer ${token}` }),
      },
    };

    try {
      const response = await fetch(`${this.baseURL}${endpoint}`, config);

      // Check if token was refreshed
      if (response.headers.get('x-token-refreshed') === 'true') {
        const authHeader = response.headers.get('authorization');
        if (authHeader) {
          const newToken = authHeader.replace('Bearer ', '');
          this.setToken(newToken);
          console.log('Token auto-refreshed successfully');
        }
      }

      // Handle authentication errors
      if (response.status === 401) {
        const data = await response.json();
        if (data.message && data.message.includes('expired and cannot be refreshed')) {
          this.removeToken();
          window.location.href = '/login';
        }
      }

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error('API request failed:', error);
      throw error;
    }
  }

  get(endpoint) {
    return this.request(endpoint, { method: 'GET' });
  }

  post(endpoint, data) {
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  put(endpoint, data) {
    return this.request(endpoint, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  delete(endpoint) {
    return this.request(endpoint, { method: 'DELETE' });
  }
}

// Usage
const apiClient = new ApiClient('http://localhost:8000/api/v1');

// ============================================================================
// Example 3: React Hook for Auto-Refresh Token
// ============================================================================

import { useEffect } from 'react';

export function useAutoRefreshToken() {
  useEffect(() => {
    const handleTokenRefresh = (event) => {
      console.log('Token refreshed:', event.detail.token);
      // You can update your global state here if needed
      // For example, if using Redux or Context API
    };

    window.addEventListener('token-refreshed', handleTokenRefresh);

    return () => {
      window.removeEventListener('token-refreshed', handleTokenRefresh);
    };
  }, []);
}

// Usage in your main App component
function App() {
  useAutoRefreshToken();

  return (
    <div>
      {/* Your app content */}
    </div>
  );
}

// ============================================================================
// Example 4: Vue.js Plugin
// ============================================================================

// Create a Vue plugin for auto-refresh
const AutoRefreshPlugin = {
  install(app, options) {
    // Create axios instance
    const api = axios.create({
      baseURL: options.baseURL || 'http://localhost:8000/api/v1',
    });

    // Add request interceptor
    api.interceptors.request.use((config) => {
      const token = localStorage.getItem('access_token');
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
      return config;
    });

    // Add response interceptor
    api.interceptors.response.use(
      (response) => {
        if (response.headers['x-token-refreshed'] === 'true') {
          const authHeader = response.headers['authorization'];
          if (authHeader && authHeader.startsWith('Bearer ')) {
            const newToken = authHeader.substring(7);
            localStorage.setItem('access_token', newToken);

            // Emit event for Vue components
            app.config.globalProperties.$emitter.emit('token-refreshed', newToken);
          }
        }
        return response;
      },
      (error) => {
        if (error.response?.status === 401) {
          localStorage.removeItem('access_token');
          window.location.href = '/login';
        }
        return Promise.reject(error);
      }
    );

    // Make available globally
    app.config.globalProperties.$api = api;
  },
};

// Usage in Vue 3
// import { createApp } from 'vue';
// import App from './App.vue';
// import AutoRefreshPlugin from './plugins/auto-refresh';
//
// const app = createApp(App);
// app.use(AutoRefreshPlugin, { baseURL: 'http://localhost:8000/api/v1' });
// app.mount('#app');

// ============================================================================
// Example 5: Monitoring Token Expiration
// ============================================================================

/**
 * Decode JWT token to get expiration time
 */
function decodeToken(token) {
  try {
    const base64Url = token.split('.')[1];
    const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
    const jsonPayload = decodeURIComponent(
      atob(base64)
        .split('')
        .map((c) => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2))
        .join('')
    );
    return JSON.parse(jsonPayload);
  } catch (error) {
    console.error('Failed to decode token:', error);
    return null;
  }
}

/**
 * Get time until token expiration in seconds
 */
function getTimeUntilExpiration(token) {
  const decoded = decodeToken(token);
  if (!decoded || !decoded.exp) return 0;

  const now = Math.floor(Date.now() / 1000);
  return decoded.exp - now;
}

/**
 * Monitor token expiration and show warnings
 */
function startTokenExpirationMonitor() {
  setInterval(() => {
    const token = localStorage.getItem('access_token');
    if (!token) return;

    const timeRemaining = getTimeUntilExpiration(token);

    // Token will expire soon (less than 5 minutes)
    if (timeRemaining > 0 && timeRemaining < 300) {
      console.warn(`Token expires in ${Math.floor(timeRemaining / 60)} minutes`);
      // You could show a warning to the user here
    }

    // Token has expired
    if (timeRemaining <= 0) {
      console.log('Token has expired');
      // The auto-refresh middleware will handle this on the next request
    }
  }, 60000); // Check every minute
}

// Start monitoring when app loads
// startTokenExpirationMonitor();

// ============================================================================
// Example 6: Testing Auto-Refresh
// ============================================================================

/**
 * Test function to verify auto-refresh is working
 */
async function testAutoRefresh() {
  try {
    console.log('Testing auto-refresh...');

    // Get current token
    const oldToken = localStorage.getItem('access_token');
    console.log('Current token:', oldToken?.substring(0, 20) + '...');

    // Make a request (use any authenticated endpoint)
    const response = await api.get('/auth/me');

    // Get token after request
    const newToken = localStorage.getItem('access_token');

    if (oldToken !== newToken) {
      console.log('✓ Token was refreshed!');
      console.log('New token:', newToken?.substring(0, 20) + '...');
    } else {
      console.log('Token was not refreshed (still valid)');
    }

    return response.data;
  } catch (error) {
    console.error('Test failed:', error);
  }
}

// Run test
// testAutoRefresh();

// ============================================================================
// Example 7: Complete Authentication Service
// ============================================================================

class AuthService {
  constructor(apiClient) {
    this.api = apiClient;
  }

  async login(email, password) {
    try {
      const response = await this.api.post('/auth/login', { email, password });
      const { access_token } = response.data.data;

      localStorage.setItem('access_token', access_token);

      return response.data;
    } catch (error) {
      console.error('Login failed:', error);
      throw error;
    }
  }

  async logout() {
    try {
      await this.api.post('/auth/logout');
    } catch (error) {
      console.error('Logout failed:', error);
    } finally {
      localStorage.removeItem('access_token');
      window.location.href = '/login';
    }
  }

  async getCurrentUser() {
    try {
      const response = await this.api.get('/auth/me');
      return response.data;
    } catch (error) {
      console.error('Failed to get current user:', error);
      throw error;
    }
  }

  isAuthenticated() {
    const token = localStorage.getItem('access_token');
    if (!token) return false;

    // Check if token is expired
    const timeRemaining = getTimeUntilExpiration(token);
    return timeRemaining > 0;
  }

  getToken() {
    return localStorage.getItem('access_token');
  }
}

// Usage
// const authService = new AuthService(api);
// await authService.login('agent@example.com', 'password');
// const user = await authService.getCurrentUser();
// await authService.logout();

// ============================================================================
// Notes and Best Practices
// ============================================================================

/**
 * BEST PRACTICES:
 *
 * 1. Always use HTTPS in production to prevent token interception
 * 2. Store tokens in localStorage (or sessionStorage for more security)
 * 3. Never store tokens in cookies without proper security flags
 * 4. Implement proper error handling for 401 responses
 * 5. Clear tokens on logout
 * 6. Monitor token expiration and warn users if needed
 * 7. Test auto-refresh in different scenarios (concurrent requests, etc.)
 *
 * SECURITY CONSIDERATIONS:
 *
 * 1. The auto-refresh middleware handles most of the complexity server-side
 * 2. Frontend only needs to capture and store the new token from headers
 * 3. Old tokens are automatically blacklisted after refresh
 * 4. Tokens cannot be refreshed after the refresh window expires (2 weeks default)
 * 5. Explicit logout immediately invalidates the token
 */
