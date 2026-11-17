/* MARKAZ: applies the saved language BEFORE the page paints (no flash).
   English is the default. When Arabic was chosen, this switches the page to
   right-to-left and loads the Arabic font, the right-to-left layout and the
   Arabic dictionary. Nothing extra is downloaded for English. The choice is
   saved in this browser under "markaz-lang". */
(function () {
    try {
        var arabic = window.localStorage.getItem('markaz-lang') === 'ar';
        var root = document.documentElement;
        root.setAttribute('lang', arabic ? 'ar' : 'en');
        root.setAttribute('dir', arabic ? 'rtl' : 'ltr');
        if (arabic && document.currentScript && document.currentScript.src) {
            var base = document.currentScript.src.replace(/js\/lang-init\.js.*$/, '');
            var v = '?v=20261116-lang1';
            var path = window.location.pathname.replace(/\.html$/, '').replace(/\/+$/, '').split('/');
                        var key = path.pop() || 'index';
                        if (key === 'index' && path.length > 1) key = path.pop();
            root.classList.add('mk-i18n-pending');
            document.write(
                '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">' +
                '<link rel="stylesheet" href="' + base + 'css/theme/' + key + '.rtl.css' + v + '">' +
                '<link rel="stylesheet" href="' + base + 'css/rtl-extras.css' + v + '">' +
                '<script src="' + base + 'js/i18n-ar.js' + v + '"><\/script>'
            );
        }
    } catch (error) { /* storage blocked: stay in English */ }
})();
