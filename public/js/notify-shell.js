/*
 * Dot.Notify shell: the light or dark scheme. A plain external file, loaded synchronously in <head> so the scheme is set before first
 * paint (no flash), and so a nonce CSP needs no inline script. The shared header's account menu and the phone menu hold the
 * Light / Dark / System buttons (dot-ui js/dot-nav.js stores the choice under `dot-theme` and fires `dot:theme` on the document).
 * This file turns that choice into the pad's own `html.dark` class: `dark` is dark, `light` is light, anything else follows the system.
 */
(function () {
    'use strict';
    var root = document.documentElement;
    var query = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function stored() {
        try { return window.localStorage.getItem('dot-theme'); } catch (e) { return null; }
    }

    function apply() {
        var mode = stored();
        var dark = mode === 'dark' || (mode !== 'light' && !!query && query.matches);
        root.classList.toggle('dark', dark);
        if (mode === 'dark' || mode === 'light') { root.setAttribute('data-theme', mode); } else { root.removeAttribute('data-theme'); }
    }

    apply();
    document.addEventListener('dot:theme', apply);
    if (query && query.addEventListener) { query.addEventListener('change', apply); }
}());
