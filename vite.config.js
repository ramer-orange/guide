import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from 'tailwindcss';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/hamburger.js',
                'resources/js/share-button.js',
                'resources/js/validation-scroll.js',
                'resources/js/alert-modal.js',
            ],
            refresh: true,
        }),
    ],
});
