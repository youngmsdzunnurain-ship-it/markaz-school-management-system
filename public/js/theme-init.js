/* MARKAZ staff pages: applies the saved colour theme BEFORE the page paints,
   so there is no flash of the wrong theme. Loaded only by the staff pages
   (Admin, Teacher, Exam Officer, Management), never by public pages or the
   student portal. The choice is saved in this browser under "markaz-theme". */
(function () {
    try {
        var dark = window.localStorage.getItem('markaz-theme') === 'dark';
        var root = document.documentElement;
        root.setAttribute('data-theme', dark ? 'dark' : 'light');
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) { meta.setAttribute('data-light', meta.getAttribute('content') || ''); }
        if (dark && meta) { meta.setAttribute('content', '#101612'); }
        /* The dark stylesheet is only downloaded for people who use dark mode.
           document.write keeps it render-blocking, so the first paint is already dark. */
        if (dark && document.currentScript && document.currentScript.src) {
            var href = document.currentScript.src.replace(/js\/theme-init\.js.*$/, 'css/dark-mode.css?v=20261115-dark1');
            document.write('<link rel="stylesheet" id="mk-dark-css" href="' + href + '">');
        }
    } catch (error) { /* storage blocked: stay on the light theme */ }
})();
