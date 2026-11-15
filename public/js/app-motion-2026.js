/* MARKAZ dashboards — Motion 2026: numbers count up when they arrive.
   Additive. It only changes the text of the dashboard counters, always ends on
   exactly the value the dashboard itself wrote, and does nothing for visitors
   who ask for reduced motion. */
(() => {
    'use strict';

    if (!window.requestAnimationFrame || !window.MutationObserver) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const SELECTOR = [
        '.stat-card .stat-main > strong',
        '#completeStudents', '#incompleteStudents', '#noResultStudents',
        '#resultCompletionPercentage'
    ].join(',');

    const NUMBER = /^\s*(\d{1,7})\s*(%?)\s*$/;
    const DURATION = 1100;
    const ease = t => 1 - Math.pow(1 - t, 4);

    const states = new WeakMap();

    function watch(element) {
        if (states.has(element)) return;
        const state = { target: null, written: null, frame: 0 };
        states.set(element, state);

        const read = () => {
            const text = element.textContent;
            if (text === state.written) return;          // our own write: ignore

            const animating = state.frame !== 0;
            if (animating && NUMBER.exec(text) && state.target === text) { element.textContent = state.written; return; }
            if (state.frame) { cancelAnimationFrame(state.frame); state.frame = 0; }

            const match = NUMBER.exec(text);
            if (!match) { state.target = null; return; }

            const end = parseInt(match[1], 10);
            const suffix = match[2];
            if (state.target === text) {                  // same value written again
                /* (finished or repeated write of the same value) */
                return;
            }
            state.target = text;
            if (end < 2) return;                          // nothing worth counting

            const start = performance.now();
            const step = now => {
                const progress = Math.min(1, (now - start) / DURATION);
                const value = progress >= 1 ? end : Math.round(end * ease(progress));
                const out = value + suffix;
                state.written = out;
                element.textContent = out;
                if (progress < 1) state.frame = requestAnimationFrame(step);
                else { state.frame = 0; state.written = text.trim() === out ? text : out; }
            };
            state.written = '0' + suffix;
            element.textContent = state.written;
            state.frame = requestAnimationFrame(step);
        };

        new MutationObserver(read).observe(element, { childList: true, characterData: true, subtree: true });
        read();
    }

    const scan = () => document.querySelectorAll(SELECTOR).forEach(watch);
    scan();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan);
})();
