<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superset Dashboard Example</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .description {
            color: #666;
            margin-bottom: 30px;
        }

        #superset-dashboard {
            width: 100%;
            height: 800px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        .error {
            background-color: #fee;
            border: 1px solid #fcc;
            border-radius: 4px;
            padding: 15px;
            color: #c33;
            margin-bottom: 20px;
        }

        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 800px;
            color: #666;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Superset Dashboard Embedding Example</h1>
        <p class="description">
            This example demonstrates how to embed a Superset dashboard using the embedded SDK with guest token authentication.
        </p>

        <div id="error-message" style="display: none;" class="error"></div>

        <div id="superset-dashboard">
            <div class="loading">Loading dashboard...</div>
        </div>
    </div>

    <script type="module">
        import { embedSupersetDashboardWithRefresh } from './resources/js/superset.js';

        // Replace this with your actual dashboard UUID from Superset
        const DASHBOARD_ID = 'your-dashboard-uuid-here';

        // Embed options
        const options = {
            hideTitle: false,
            hideTab: false,
            hideChartControls: false,
            filtersExpanded: true,
            filtersVisible: true,
            // Optional: Row Level Security rules
            rls: {
                // Example RLS rules
                // dataset_id: [{ clause: "customer_id = 123" }]
            }
        };

        // Embed the dashboard when the page loads
        async function initDashboard() {
            try {
                await embedSupersetDashboardWithRefresh('superset-dashboard', DASHBOARD_ID, options);
            } catch (error) {
                console.error('Failed to embed dashboard:', error);
                const errorDiv = document.getElementById('error-message');
                errorDiv.textContent = `Error: ${error.message}. Please check the console for details.`;
                errorDiv.style.display = 'block';
            }
        }

        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDashboard);
        } else {
            initDashboard();
        }
    </script>
</body>
</html>
