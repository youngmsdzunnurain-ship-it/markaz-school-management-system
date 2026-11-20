/* MARKAZ: the same "fit to screen" scaling the Dashboard uses, for the MARKAZ Online Class workspace.
   Desktop screens narrower than the 1600px design show the SAME layout scaled to fit (so the sidebar and
   every card keep the same proportions as the Dashboard). Phones (850px and below) keep the mobile layout. */
(function () {
    var DESIGN = 1600, MIN = 851, root = document.documentElement;
    function fit() {
        var w = window.innerWidth, z = (w >= MIN && w < DESIGN) ? w / DESIGN : 1;
        root.style.zoom = z === 1 ? "" : String(z);
        root.style.setProperty("--page-zoom", String(z));
    }
    fit();
    window.addEventListener("resize", fit);
    window.addEventListener("orientationchange", fit);
})();
