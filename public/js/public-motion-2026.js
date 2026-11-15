/* MARKAZ public website — Motion 2026 (scroll reveals + image focus-in).
   Additive: it only adds attributes/classes that public-motion-2026.css reacts
   to. If the browser lacks a feature, the visitor asks for reduced motion, or
   Data Saver is on, this script does nothing and the site looks as it did before. */
(() => {
    'use strict';

    const root = document.documentElement;
    const main = document.querySelector('main');
    if (!main) return;

    const reduceMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection || {};
    const supported = 'IntersectionObserver' in window &&
        window.CSS && typeof CSS.supports === 'function' &&
        CSS.supports('translate', '0 1px') && CSS.supports('scale', '1');

    if (reduceMotion || connection.saveData || !supported) return;

    /* Things that should enter one by one. Anything else directly inside a
       section's container is picked up as a "loose block" below. */
    const ITEM_SELECTOR = [
        '.section-heading', '.public-section-heading', '.markaz-facility-heading',
        '.memorisation-heading', '.academic-milestones-heading', '.intro-heading',
        '.highlight-item', '.programme-card', '.public-value-card',
        '.markaz-photo-card', '.markaz-facility-card', '.stage-card', '.pathway-card',
        '.quran-card', '.conventional-card', '.quran-feature', '.memorisation-note',
        '.public-copy', '.public-photo', '.intro-content', '.intro-visual',
        '.public-quote', '.public-cta', '.markaz-facility-intro', '.markaz-facility-note',
        '.markaz-faq-group > h3', '.public-faq-item', '.markaz-faq-aside',
        '.admission-banner-inner', '.academics-cta-content',
        '.admission-priority-card', '.admission-contact-card', '.admission-guidance-card'
    ].join(',');

    const ZOOM_SELECTOR = [
        '.public-photo', '.intro-visual', '.markaz-photo-card', '.markaz-facility-card',
        '.markaz-facility-intro', '.markaz-facility-note'
    ].join(',');

    const targets = [];
    const seen = new Set();

    function tag(element) {
        if (!element || seen.has(element)) return;
        /* Never animate something inside something that is already animated. */
        if (element.parentElement && element.parentElement.closest('[data-mk-r]')) return;
        seen.add(element);
        element.setAttribute('data-mk-r', element.matches(ZOOM_SELECTOR) ? 'zoom' : '');
        targets.push(element);
    }

    /* The first section is the page hero; it has its own load animation. */
    Array.from(main.querySelectorAll(':scope > section')).slice(1).forEach(section => {
        const container = section.querySelector(':scope > .container') || section;

        Array.from(section.querySelectorAll(ITEM_SELECTOR)).forEach(tag);

        Array.from(container.children).forEach(child => {
            if (seen.has(child) || child.matches('script, style')) return;
            const containsItem = Array.from(seen).some(item => child.contains(item));
            if (!containsItem) tag(child);
        });
    });

    /* Footer columns. */
    document.querySelectorAll('.footer .container > *, .footer > .footer-bottom').forEach(tag);

    /* ---- Images: soft focus-in as they finish loading ---------------------- */
    const imagesInProgress = [];
    Array.from(document.querySelectorAll('main img')).forEach(img => {
        if (img.closest('.building-frame, .header, .footer, .brand')) return;
        /* Already finished (loaded OR failed): nothing left to wait for. */
        if (img.complete) return;
        if (window.getComputedStyle(img).filter !== 'none') return; // keep images that already use a filter untouched

        img.classList.add('mk26-img');
        imagesInProgress.push(img);

        const finish = () => {
            img.classList.add('mk26-img-in');
            window.setTimeout(() => img.classList.remove('mk26-img', 'mk26-img-in'), 1700);
        };
        img.addEventListener('load', finish, { once: true });
        img.addEventListener('error', finish, { once: true });
        /* Safety net: never leave an image blurred if the browser never reports back. */
        window.setTimeout(() => img.classList.remove('mk26-img', 'mk26-img-in'), 12000);
    });

    if (!targets.length && !imagesInProgress.length) return;

    root.classList.add('mk26');

    function cleanUp(element) {
        element.removeAttribute('data-mk-r');
        element.classList.remove('mk-in');
        element.style.removeProperty('--mk-d');
    }

    let observerFired = false;
    let observer = null;
    let pending = new Set(targets);

    function reveal(entries) {
        observerFired = true;
        const ready = entries
            .filter(entry => entry.isIntersecting || entry.boundingClientRect.top < 0)
            .sort((a, b) =>
                (a.boundingClientRect.top - b.boundingClientRect.top) ||
                (a.boundingClientRect.left - b.boundingClientRect.left));

        let order = 0;
        ready.forEach(entry => {
            const element = entry.target;
            observer.unobserve(element);
            pending.delete(element);

            /* Elements already scrolled past (page opened part-way down, or an
               anchor jump) appear straight away; the rest cascade in. */
            const passed = !entry.isIntersecting && entry.boundingClientRect.top < 0;
            const delay = passed ? 0 : Math.min(order++, 7) * 85;
            element.style.setProperty('--mk-d', delay + 'ms');
            element.classList.add('mk-in');
            window.setTimeout(() => cleanUp(element), delay + 1400);
        });
    }

    function revealEverythingLeft() {
        Array.from(pending).forEach((element, index) => {
            pending.delete(element);
            if (observer) observer.unobserve(element);
            element.style.setProperty('--mk-d', Math.min(index, 7) * 85 + 'ms');
            element.classList.add('mk-in');
            window.setTimeout(() => cleanUp(element), Math.min(index, 7) * 85 + 1400);
        });
    }

    /* Switch transitions on one frame after tagging, then start watching, so
       that tagging an element never makes it animate out before animating in. */
    window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
        root.classList.add('mk26-live');

        observer = new IntersectionObserver(reveal, {
            threshold: 0,
            rootMargin: '0px 0px -6% 0px'
        });
        targets.forEach(element => observer.observe(element));

        /* Safety net 1: if the observer never reports anything, show the page. */
        window.setTimeout(() => { if (!observerFired) revealEverythingLeft(); }, 2500);
    }));

    /* Safety net 2: the last items on a page sit inside the bottom margin, so
       when the visitor reaches the very bottom, show whatever is left. */
    let queued = false;
    window.addEventListener('scroll', () => {
        if (queued || !pending.size) return;
        queued = true;
        window.requestAnimationFrame(() => {
            queued = false;
            const atBottom = window.innerHeight + (window.scrollY || root.scrollTop || 0) >=
                root.scrollHeight - 90;
            if (atBottom) revealEverythingLeft();
        });
    }, { passive: true });

    /* Safety net 3: bring a restored (back/forward) page back to a clean state. */
    window.addEventListener('pageshow', event => {
        if (event.persisted) revealEverythingLeft();
    });
})();
