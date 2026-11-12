import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Configura la compilación de recursos locales y la recarga durante el desarrollo.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
