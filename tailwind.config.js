import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import dotPreset from './dot-ui/tailwind/tailwind-preset.cjs';

/**
 * Tailwind for the guest pages (resources/css/app.css, preflight on). Roboto comes from the shared dot-ui preset
 * (font-sans, font-mono, text-dot-*); there is no font override of the host's own. The signed-in shell uses tailwind.jetstream.config.js.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    presets: [dotPreset],

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    darkMode: 'class',

    plugins: [forms, typography],
};
