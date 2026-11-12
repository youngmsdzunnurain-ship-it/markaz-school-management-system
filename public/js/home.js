document.addEventListener('DOMContentLoaded', () => {
    const menuToggle = document.getElementById('menuToggle');
    const mainNav = document.getElementById('mainNav');
    const portalMenu = document.getElementById('portalMenu');
    const portalToggle = document.getElementById('portalToggle');
    const exploreMenus = document.querySelectorAll('.nav-group');

    function closePortal() {
        if (portalMenu) portalMenu.classList.remove('open');
        if (portalToggle) portalToggle.setAttribute('aria-expanded', 'false');
    }

    function closeExplore(exceptMenu = null) {
        exploreMenus.forEach(menu => {
            if (menu !== exceptMenu) menu.removeAttribute('open');
        });
    }

    function closeDropdowns() {
        closePortal();
        closeExplore();
    }

    function closeMobileNav() {
        if (mainNav) mainNav.classList.remove('open');

        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.setAttribute('aria-label', 'Open navigation menu');
            menuToggle.textContent = '☰';
        }
    }

    // Mobile navigation button.
    if (menuToggle && mainNav) {
        menuToggle.addEventListener('click', event => {
            event.stopPropagation();
            const isOpen = mainNav.classList.toggle('open');

            menuToggle.setAttribute('aria-expanded', String(isOpen));
            menuToggle.setAttribute(
                'aria-label',
                isOpen ? 'Close navigation menu' : 'Open navigation menu'
            );
            menuToggle.textContent = isOpen ? '✕' : '☰';

            if (!isOpen) closeDropdowns();
        });
    }

    // Explore: opening it closes Portal.
    exploreMenus.forEach(menu => {
        menu.addEventListener('toggle', () => {
            if (menu.open) closePortal();
        });
    });

    // Portal: opening it closes Explore.
    if (portalMenu && portalToggle) {
        portalToggle.addEventListener('click', event => {
            event.stopPropagation();

            const shouldOpen = !portalMenu.classList.contains('open');

            closeExplore();
            closePortal();

            if (shouldOpen) {
                portalMenu.classList.add('open');
                portalToggle.setAttribute('aria-expanded', 'true');
            }
        });
    }

    // Clicking outside either dropdown closes both.
    document.addEventListener('click', event => {
        const insideExplore = event.target.closest('.nav-group');
        const insidePortal = event.target.closest('.portal-menu');

        if (!insideExplore && !insidePortal) {
            closeDropdowns();
        }
    });

    // Escape closes dropdowns and the mobile menu.
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeDropdowns();
            closeMobileNav();
        }
    });

    // Keep the selected destination highlighted without waiting for the
    // next document to finish loading (including links inside Explore).
    function setActiveDestination(targetUrl) {
        if (!mainNav || targetUrl.origin !== location.origin) return;
        const destination = targetUrl.pathname.split('/').filter(Boolean).pop() || 'home.html';
        const links = Array.from(mainNav.querySelectorAll('a[href]'));
        links.forEach(link => {
            const linkUrl = new URL(link.href, location.href);
            const active = linkUrl.origin === location.origin &&
                (linkUrl.pathname.split('/').filter(Boolean).pop() || 'home.html') === destination;
            link.classList.toggle('active', active);
            if (active) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });

        const explore = mainNav.querySelector('.nav-group');
        const exploreSummary = explore?.querySelector(':scope > summary.nav-link');
        const isExploreDestination = Boolean(explore?.querySelector('a[href]') &&
            Array.from(explore.querySelectorAll('a[href]')).some(link => {
                const linkUrl = new URL(link.href, location.href);
                return linkUrl.origin === location.origin &&
                    (linkUrl.pathname.split('/').filter(Boolean).pop() || 'home.html') === destination;
            }));
        exploreSummary?.classList.toggle('active', isExploreDestination);
        if (isExploreDestination) exploreSummary?.setAttribute('aria-current', 'page');
        else exploreSummary?.removeAttribute('aria-current');
    }

    if (mainNav) {
        setActiveDestination(new URL(location.href));
        mainNav.querySelectorAll('a[href]').forEach(link => {
            link.addEventListener('click', () => {
                setActiveDestination(new URL(link.href, location.href));
                closeDropdowns();
                closeMobileNav();
            });
        });
    }

    // Keep the footer year current.
    const currentYear = document.getElementById('currentYear');
    if (currentYear) {
        currentYear.textContent = new Date().getFullYear();
    }
});
