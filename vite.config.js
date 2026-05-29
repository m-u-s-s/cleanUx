import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Heavy libs loaded only on pages that need them (see @vite in those blades).
                'resources/js/apexcharts.js',
                'resources/js/fullcalendar.js',
            ],
            refresh: true,
        }),
    ],
});
