import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/notify.css', 'resources/css/jetstream.css', 'resources/css/guest.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
