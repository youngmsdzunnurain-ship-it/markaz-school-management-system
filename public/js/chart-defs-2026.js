/* MARKAZ dashboard charts: adds the colour gradients that dashboard-polish-2026.css uses to give the
   attendance bars and rings their depth. Purely visual; the charts and their numbers are untouched. */
(function () {
    'use strict';
    if (document.getElementById('mk-chart-defs')) return;
    function grad(id, x2, y2, a, b) {
        return '<linearGradient id="' + id + '" x1="0" y1="0" x2="' + x2 + '" y2="' + y2 + '">' +
            '<stop offset="0" stop-color="' + a + '"/><stop offset="1" stop-color="' + b + '"/></linearGradient>';
    }
    var defs = '<defs>' +
        grad('mkg-present', 0, 1, '#5fd493', '#1b7646') + grad('mkg-present-d', 1, 1, '#5fd493', '#1b7646') +
        grad('mkg-absent', 0, 1, '#ff8f8f', '#c23b3b') + grad('mkg-absent-d', 1, 1, '#ff8f8f', '#c23b3b') +
        grad('mkg-late', 0, 1, '#ffd978', '#c58a0e') + grad('mkg-late-d', 1, 1, '#ffd978', '#c58a0e') +
        grad('mkg-excused', 0, 1, '#c3cbdb', '#6c778e') + grad('mkg-excused-d', 1, 1, '#c3cbdb', '#6c778e') +
        '</defs>';
    function add() {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.id = 'mk-chart-defs';
        svg.setAttribute('width', '0'); svg.setAttribute('height', '0'); svg.setAttribute('aria-hidden', 'true');
        svg.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden';
        svg.innerHTML = defs;
        document.body.appendChild(svg);
    }
    if (document.body) add(); else document.addEventListener('DOMContentLoaded', add);
})();
