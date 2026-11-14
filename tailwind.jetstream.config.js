import baseConfig from './tailwind.config.js';

/**
 * Tailwind for the signed-in shell's Jetstream pages (profile, team, API tokens): the same content, preset and dark mode as
 * tailwind.config.js, with preflight OFF. The notify stylesheet owns the pad's base styles (headings, lists, links), so Tailwind's
 * reset must not run over them; this is what the Play CDN build did (corePlugins.preflight false) before it was replaced by this file.
 * The forms and typography plugins are not part of the CDN build either, and are left out here to keep the shell identical.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    presets: [baseConfig],
    content: baseConfig.content,
    plugins: [],
    corePlugins: { preflight: false },
};
