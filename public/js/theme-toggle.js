/* MARKAZ staff pages: the Light / Dark switch.
   Adds one item to the avatar drop-down (Dashboard and every other staff
   page). The choice is saved in this browser and shared across tabs.
   Loaded only by Admin, Teacher, Exam Officer and Management pages. */
(() => {
    'use strict';

    const KEY = 'markaz-theme';
    const root = document.documentElement;

    const ICONS = {
        moon: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.6 6.6 0 0 0 9.8 9.8z"/></svg>',
        sun: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.2M12 19.3v2.2M4.6 4.6l1.6 1.6M17.8 17.8l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.6 19.4l1.6-1.6M17.8 6.2l1.6-1.6"/></svg>'
    };

    const isDark = () => root.getAttribute('data-theme') === 'dark';

    function read() {
        try { return window.localStorage.getItem(KEY) === 'dark' ? 'dark' : 'light'; } catch (error) { return 'light'; }
    }

    function paintMeta(dark) {
        const meta = document.querySelector('meta[name="theme-color"]');
        if (!meta) return;
        if (!meta.hasAttribute('data-light')) meta.setAttribute('data-light', meta.getAttribute('content') || '');
        if (dark) meta.setAttribute('content', '#101612');
        else if (meta.hasAttribute('data-light')) meta.setAttribute('content', meta.getAttribute('data-light'));
    }

    function refresh() {
        const dark = isDark();
        document.querySelectorAll('.mk-theme-toggle').forEach(button => {
            /* only touch the page when something actually changed (avoids endless observer loops) */
            const state = dark ? 'dark' : 'light';
            if (button.getAttribute('data-state') === state) return;
            button.setAttribute('data-state', state);
            button.setAttribute('aria-checked', String(dark));
            button.setAttribute('title', dark ? 'Switch to light mode' : 'Switch to dark mode');
            const icon = button.querySelector('.mk-theme-icon');
            const label = button.querySelector('.mk-theme-label');
            if (icon) icon.innerHTML = dark ? ICONS.sun : ICONS.moon;
            if (label) label.textContent = dark ? 'Light mode' : 'Dark mode';
        });
    }

    /* Download the dark stylesheet the first time it is needed, and switch only once it is ready. */
    function withDarkCss(done) {
        if (document.getElementById('mk-dark-css')) { done(); return; }
        const script = document.querySelector('script[src*="theme-toggle.js"]');
        const path = window.location.pathname.replace(/\.html$/, '').replace(/\/+$/, '').split('/');
        let key = path.pop() || 'index';
        if (key === 'index' && path.length > 1) key = path.pop();
        const href = (script ? script.src : '').replace(/js\/theme-toggle\.js.*$/, 'css/theme/' + key + '.dark.css?v=20261116-dark2');
        if (!href) { done(); return; }
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.id = 'mk-dark-css';
        link.href = href;
        link.onload = done;
        link.onerror = () => { link.remove(); };
        document.head.appendChild(link);
    }

    function apply(mode, save) {
        if (mode === 'dark' && !document.getElementById('mk-dark-css')) {
            withDarkCss(() => apply('dark', save));
            return;
        }
        const dark = mode === 'dark';
        if (save) { try { window.localStorage.setItem(KEY, dark ? 'dark' : 'light'); } catch (error) { /* ignore */ } }

        /* a short, smooth colour fade only while switching (never on page load) */
        root.classList.add('mk-theme-switching');
        root.setAttribute('data-theme', dark ? 'dark' : 'light');
        paintMeta(dark);
        refresh();
        window.setTimeout(() => root.classList.remove('mk-theme-switching'), 450);
        document.dispatchEvent(new CustomEvent('markaz:themechange', { detail: { theme: dark ? 'dark' : 'light' } }));
    }

    function build(compact) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'mk-theme-toggle' + (compact ? ' mk-theme-toggle-compact' : '');
        button.setAttribute('role', 'switch');
        button.innerHTML = '<span class="mk-theme-icon"></span><span class="mk-theme-label"></span>' +
            '<span class="mk-theme-switch" aria-hidden="true"><i></i></span>';
        button.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();
            apply(isDark() ? 'light' : 'dark', true);
        });
        return button;
    }

    /* The two avatar menus are built by other scripts, so look for them now
       and again whenever the page changes, until both are handled. */
    function place() {
        const spots = [
            ['.profile-dropdown', '#topProfileLogoutButton'],
            ['.markaz-shell-profile-menu', '#markazShellLogout']
        ];
        let found = 0;
        spots.forEach(([menuSelector, logoutSelector]) => {
            const menu = document.querySelector(menuSelector);
            if (!menu) return;
            found++;
            if (menu.querySelector('.mk-theme-toggle')) return;
            const toggle = build(false);
            if (menuSelector === '.profile-dropdown') toggle.classList.add('profile-dropdown-item');
            const logout = menu.querySelector(logoutSelector);
            if (logout) menu.insertBefore(toggle, logout); else menu.appendChild(toggle);
        });
        refresh();
        return found > 0;
    }

    /* Pages without an avatar menu (MARKAZ Online) get a small floating button.
       It is fixed to the corner of the screen, so it can never move anything on the page. */
    function fallback() {
        if (document.querySelector('.mk-theme-toggle')) return;
        const toggle = build(true);
        toggle.classList.add('mk-theme-toggle-floating');
        document.body.appendChild(toggle);
        refresh();
    }

    function start() {
        if (isDark()) paintMeta(true);
        place();
        /* watch for the menus being built; stop as soon as every menu on this page has its switch */
        const observer = new MutationObserver(() => { if (place()) observer.disconnect(); });
        observer.observe(document.body, { childList: true, subtree: true });
        window.setTimeout(() => observer.disconnect(), 15000);
        window.setTimeout(fallback, 1500);
        refresh();
    }

    window.addEventListener('storage', event => {
        if (event.key === KEY) apply(read(), false);
    });

    window.MarkazTheme = { get: () => (isDark() ? 'dark' : 'light'), set: mode => apply(mode, true) };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
