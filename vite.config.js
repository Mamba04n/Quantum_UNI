import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/storefront-public.css',
                'resources/css/storefront-experience.css',
                'resources/js/app.js',
                'resources/js/storefront-experience.js',
            ],
            refresh: true,
        }),
    ],
});
