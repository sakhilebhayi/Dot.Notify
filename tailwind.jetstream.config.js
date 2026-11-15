import dotPreset from './dot-ui/tailwind/tailwind-preset.cjs';

/**
 * Tailwind for the signed-in shell's Jetstream pages (profile, team, API tokens): the same content, the dot-ui preset (Roboto) and dark mode
 * as tailwind.config.js, with preflight OFF and NO plugins. The notify stylesheet owns the pad's base styles (headings, lists, links, form
 * controls), so Tailwind's reset and the forms plugin must not run over them. This is exactly what the Play CDN build did
 * (corePlugins.preflight false, no plugins) before it was replaced by this file. It does not extend tailwind.config.js as a preset, because a
 * preset's plugins (forms, typography) would come along.
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

    plugins: [],

    corePlugins: { preflight: false },
};
