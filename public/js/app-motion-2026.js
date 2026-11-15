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
        '#resultCompletionPercentage',
        '.classes-stat-card strong', '.summary-card-value', '.attendance-summary-card strong',
        '.class-summary-card strong', '.result-summary-card strong'
    ].join(',');

    const NUMBER = /^\s*(\d{1,7})\s*(%?)\s*$/;
    const DURATION = 1100;
    const ease = t => 1 - Math.pow(1 - t, 4);

    const states = new WeakMap();

    function watch(element) {
        if (states.has(element)) return;
        const state = { target: null, written: null, frame: 0, last: null };
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

            const from = state.last === null ? 0 : state.last;
            const duration = state.last === null ? DURATION : 450;
            state.last = end;
            const start = performance.now();
            const step = now => {
                const progress = Math.min(1, (now - start) / duration);
                const value = progress >= 1 ? end : Math.round(from + (end - from) * ease(progress));
                const out = value + suffix;
                state.written = out;
                element.textContent = out;
                if (progress < 1) state.frame = requestAnimationFrame(step);
                else { state.frame = 0; state.written = text.trim() === out ? text : out; }
            };
            state.written = from + suffix;
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


/* Sections further down the dashboard glide up as they scroll into view. */
(() => {
    'use strict';
    const page = document.querySelector('.page-content');
    if (!page || !('IntersectionObserver' in window)) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const seen = new Set();
    let observer = null;

    const showAll = () => {
        seen.forEach(el => el.classList.add('mkapp-in'));
        seen.clear();
    };

    const register = () => {
        Array.from(page.children).forEach((el, index) => {
            if (index < 2 || seen.has(el) || el.dataset.mkappR !== undefined) return;
            el.setAttribute('data-mkapp-r', '');
            seen.add(el);
            if (observer) observer.observe(el);
        });
    };

    observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting && entry.boundingClientRect.top >= 0) return;
            entry.target.classList.add('mkapp-in');
            seen.delete(entry.target);
            observer.unobserve(entry.target);
        });
    }, { threshold: 0, rootMargin: '0px 0px -3% 0px' });

    document.documentElement.classList.add('mkapp');
    register();

    /* Sections that appear later (role filtering, data) are picked up too. */
    new MutationObserver(register).observe(page, { childList: true });

    /* Safety nets: never leave anything offset. */
    window.addEventListener('scroll', () => {
        if (window.innerHeight + (window.scrollY || 0) >= document.documentElement.scrollHeight - 90) showAll();
    }, { passive: true });
    window.setTimeout(() => { if (seen.size && !page.getBoundingClientRect().height) showAll(); }, 4000);
})();
