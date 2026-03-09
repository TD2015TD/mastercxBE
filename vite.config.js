import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    define: {
        'import.meta.env.VITE_SUPERSET_URL': JSON.stringify(process.env.VITE_SUPERSET_URL || 'http://localhost:8088'),
    },
});
