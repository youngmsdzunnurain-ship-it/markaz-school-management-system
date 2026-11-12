/* MARKAZ shared public-page motion. Loaded with defer on public information pages. */
(() => {
    'use strict';

    const root = document.documentElement;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const main = document.querySelector('body.public-home-page main, body.public-page main');
    const header = document.querySelector('.header');

    if (!main) return;

    /* Reveal whole existing sections, not their interactive cards. This keeps each
       card's established hover transform independent from the entrance animation. */
    // The first section is the page hero. Keep it visible immediately; hiding it
    // behind the scroll-reveal observer can leave a blank band below the header.
    const revealTargets = Array.from(main.querySelectorAll(':scope > section')).slice(1);
    revealTargets.forEach((section, index) => {
        section.setAttribute('data-mk-reveal', '');
        section.style.setProperty('--mk-motion-delay', (index === 0 ? 0 : Math.min(index % 3, 2) * 70) + 'ms');
    });

    const heroCopy = document.querySelector('.public-home-page .hero-content');
    if (heroCopy && !reduceMotion) heroCopy.classList.add('mk-hero-enter');

    const progress = document.createElement('div');
    progress.className = 'mk-scroll-progress';
    progress.setAttribute('aria-hidden', 'true');
    document.body.appendChild(progress);

    const updateScrollState = () => {
        const scrollTop = window.scrollY || root.scrollTop || 0;
        const scrollable = root.scrollHeight - window.innerHeight;
        if (header) header.classList.toggle('mk-is-scrolled', scrollTop > 20);
        if (!reduceMotion && scrollable > 0) {
            progress.style.transform = 'scaleX(' + Math.min(1, Math.max(0, scrollTop / scrollable)) + ')';
        }
    };

    let scrollQueued = false;
    window.addEventListener('scroll', () => {
        if (scrollQueued) return;
        scrollQueued = true;
        window.requestAnimationFrame(() => {
            updateScrollState();
            scrollQueued = false;
        });
    }, { passive: true });

    updateScrollState();

    if (reduceMotion || !('IntersectionObserver' in window)) {
        revealTargets.forEach(section => section.classList.add('mk-is-visible'));
        return;
    }

    root.classList.add('mk-motion-ready');
    const observer = new IntersectionObserver((entries, activeObserver) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('mk-is-visible');
            activeObserver.unobserve(entry.target);
        });
    }, {
        threshold: 0.04,
        rootMargin: '0px 0px -5% 0px'
    });

    revealTargets.forEach(section => observer.observe(section));
})();
