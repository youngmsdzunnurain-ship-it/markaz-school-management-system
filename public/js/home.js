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

    // Navigation links close menus before the browser navigates.
    if (mainNav) {
        mainNav.querySelectorAll('a[href]').forEach(link => {
            link.addEventListener('click', () => {
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
