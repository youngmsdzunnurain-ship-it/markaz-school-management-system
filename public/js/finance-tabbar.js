/* MARKAZ School Fees: the finance tabs live in the sidebar on desktop and move into the page on phones.
   The same buttons are moved (not copied), so their listeners and active states are kept. */
(() => {
    "use strict";
    const nav = document.querySelector(".finance-sidebar-nav");
    const sidebar = document.querySelector("aside.sidebar");
    const content = document.getElementById("mainContent") || document.querySelector("main");
    if (!nav || !sidebar || !content) return;
    nav.classList.add("finance-tabbar");
    const home = nav.parentElement;
    const homeNext = nav.nextSibling;
    const phone = window.matchMedia("(max-width: 760px)");
    const place = () => {
        if (phone.matches) {
            if (nav.parentElement !== content) {
                const header = content.querySelector(":scope > .topbar, :scope > header, :scope > .page-header");
                content.insertBefore(nav, header ? header.nextSibling : content.firstChild);
            }
        } else if (nav.parentElement !== home) {
            home.insertBefore(nav, homeNext);
        }
    };
    if (phone.addEventListener) phone.addEventListener("change", place);
    else phone.addListener(place);
    place();
})();
