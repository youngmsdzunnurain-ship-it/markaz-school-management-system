/* MARKAZ English / Arabic.
   1. The language switch (inside the avatar menu, or a small floating button on
      pages that have no avatar menu).
   2. In Arabic: translates everything that appears on the page, including text
      that scripts add later (messages, tables, pop-ups, alerts), using the
      dictionary in i18n-ar.js. Anything not in the dictionary stays as it is,
      so nothing breaks; such text is listed by MarkazI18n.missing().
   Names, numbers and data typed by the school are never translated. */
(() => {
    'use strict';

    const KEY = 'markaz-lang';
    const root = document.documentElement;
    const ARABIC_ON = root.getAttribute('lang') === 'ar' && root.getAttribute('dir') === 'rtl';
    const D = window.MARKAZ_I18N_AR || null;

    /* ===================== the switch ===================== */
    const GLOBE = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.7 3.9 5.7 3.9 9s-1.3 6.3-3.9 9c-2.6-2.7-3.9-5.7-3.9-9S9.4 5.7 12 3z"/></svg>';

    function setLanguage(lang) {
        try { window.localStorage.setItem(KEY, lang === 'ar' ? 'ar' : 'en'); } catch (error) { /* ignore */ }
        window.location.reload();
    }

    function buildRow(floating) {
        const row = document.createElement('div');
        row.className = 'mk-lang-row' + (floating ? ' mk-lang-floating' : '');
        row.setAttribute('role', 'group');
        row.setAttribute('aria-label', ARABIC_ON ? 'اللغة' : 'Language');
        row.innerHTML =
            (floating ? '' : '<span class="mk-lang-label"><span class="mk-lang-icon">' + GLOBE + '</span><span class="mk-lang-text"></span></span>') +
            '<span class="mk-lang-seg">' +
            '<button type="button" data-lang="en" lang="en">English</button>' +
            '<button type="button" data-lang="ar" lang="ar">العربية</button></span>';
        const text = row.querySelector('.mk-lang-text');
        if (text) text.textContent = ARABIC_ON ? 'اللغة' : 'Language';
        row.querySelectorAll('button').forEach(button => {
            button.setAttribute('aria-pressed', String((button.dataset.lang === 'ar') === ARABIC_ON));
            button.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                if ((button.dataset.lang === 'ar') !== ARABIC_ON) setLanguage(button.dataset.lang);
            });
        });
        return row;
    }

    /* The avatar menus are built by other scripts. Language (and the Light/Dark
       switch, if present) are grouped neatly between the profile links and Logout. */
    function group(menu, logoutSelector) {
        let box = menu.querySelector(':scope > .mk-prefs');
        if (!box) {
            box = document.createElement('div');
            box.className = 'mk-prefs';
            const logout = menu.querySelector(logoutSelector);
            if (logout) menu.insertBefore(box, logout); else menu.appendChild(box);
        }
        const theme = menu.querySelector('.mk-theme-toggle');
        if (theme && theme.parentElement !== box) box.insertBefore(theme, box.firstChild);
        if (!box.querySelector('.mk-lang-row')) box.appendChild(buildRow(false));
        return box;
    }

    function place() {
        let found = 0;
        [['.profile-dropdown', '#topProfileLogoutButton'], ['.markaz-shell-profile-menu', '#markazShellLogout']]
            .forEach(([menuSelector, logoutSelector]) => {
                const menu = document.querySelector(menuSelector);
                if (!menu) return;
                found++;
                group(menu, logoutSelector);
            });
        return found > 0;
    }

    function startSwitch() {
        place();
        const observer = new MutationObserver(() => { place(); });
        observer.observe(document.body, { childList: true, subtree: true });
        window.setTimeout(() => observer.disconnect(), 20000);
        window.setTimeout(() => {
            if (!document.querySelector('.mk-lang-row')) {
                const floating = buildRow(true);
                document.body.appendChild(floating);
            }
        }, 1500);
    }

    /* ===================== translation ===================== */
    const missing = new Set();
    window.MarkazI18n = {
        language: () => (ARABIC_ON ? 'ar' : 'en'),
        set: setLanguage,
        missing: () => Array.from(missing).sort()
    };

    if (ARABIC_ON && D) {
        const ARABIC = /[\u0600-\u06FF]/;
        const LATIN = /[A-Za-z]/;
        const soft = s => s.replace(/[’‘]/g, "'").replace(/[“”]/g, '"').replace(/…/g, '...').replace(/\s+/g, ' ').trim();
        const keyOf = s => soft(s).toLowerCase().replace(/(\.\.\.|\.)+$/, '').trim();

        const PATTERNS = D.patterns.map(([source, template]) => [new RegExp(source, 'i'), template]);
        const MONTHS = { january: 'يناير', february: 'فبراير', march: 'مارس', april: 'أبريل', may: 'مايو', june: 'يونيو', july: 'يوليو', august: 'أغسطس', september: 'سبتمبر', october: 'أكتوبر', november: 'نوفمبر', december: 'ديسمبر', jan: 'يناير', feb: 'فبراير', mar: 'مارس', apr: 'أبريل', jun: 'يونيو', jul: 'يوليو', aug: 'أغسطس', sep: 'سبتمبر', sept: 'سبتمبر', oct: 'أكتوبر', nov: 'نوفمبر', dec: 'ديسمبر' };
        const DAYS = { sunday: 'الأحد', monday: 'الاثنين', tuesday: 'الثلاثاء', wednesday: 'الأربعاء', thursday: 'الخميس', friday: 'الجمعة', saturday: 'السبت', sun: 'الأحد', mon: 'الاثنين', tue: 'الثلاثاء', wed: 'الأربعاء', thu: 'الخميس', fri: 'الجمعة', sat: 'السبت' };
        const GREETING = { morning: 'صباح الخير', afternoon: 'طاب يومك', evening: 'مساء الخير' };
        const NOUNS = { student: 'طالب', students: 'طلاب', learner: 'طالب', learners: 'طلاب', class: 'فصل', classes: 'فصول', subject: 'مادة', subjects: 'مواد', record: 'سجل', records: 'سجلات', entry: 'مدخل', entries: 'مدخلات', day: 'يوم', days: 'أيام', teacher: 'معلم', teachers: 'معلمون', account: 'حساب', accounts: 'حسابات', device: 'جهاز', devices: 'أجهزة', term: 'فصل دراسي', terms: 'فصول دراسية' };
        /* words that may appear together with numbers, e.g. "3 present · 1 absent" */
        const WORDS = Object.assign({
            present: 'حاضر', absent: 'غائب', late: 'متأخر', excused: 'بعذر', marked: 'مسجَّل', unmarked: 'غير مسجَّل',
            active: 'نشط', inactive: 'غير نشط', complete: 'مكتمل', completed: 'مكتمل', total: 'الإجمالي', of: 'من',
            and: 'و', average: 'المعدل', score: 'الدرجة', scores: 'الدرجات', rate: 'النسبة', out: 'من', in: 'في'
        }, NOUNS);

        const cache = new Map();

        function fill(template, match, depth) {
            return template.replace(/\{(\d+)\}/g, (all, index) => {
                const part = match[Number(index) + 1];
                if (part == null) return all;
                const inner = depth < 2 && LATIN.test(part) ? translate(part, depth + 1) : null;
                return inner == null ? part : inner;
            });
        }

        function dates(s) {
            let changed = false;
            let out = s.replace(/\b(Mon|Tue|Wed|Thu|Fri|Sat|Sun)[a-z]*day?\b,?\s*/gi, (m, d) => { const k = m.replace(/[^a-z]/gi, '').toLowerCase(); const name = DAYS[k] || DAYS[k.slice(0, 3)]; if (!name) return m; changed = true; return name + '، '; });
            out = out.replace(/\b(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sept|Sep|Oct|Nov|Dec)\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/g, (m, mo, d, y) => { changed = true; return d + ' ' + MONTHS[mo.toLowerCase()] + ' ' + y; });
            out = out.replace(/\b(\d{1,2})(?:st|nd|rd|th)?\s+(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sept|Sep|Oct|Nov|Dec)\.?,?\s+(\d{4})\b/g, (m, d, mo, y) => { changed = true; return d + ' ' + MONTHS[mo.toLowerCase()] + ' ' + y; });
            out = out.replace(/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})\b/g, (m, mo, y) => { changed = true; return MONTHS[mo.toLowerCase()] + ' ' + y; });
            out = out.replace(/(\d{1,2}:\d{2}(?::\d{2})?)\s?(AM|PM)\b/gi, (m, t, ap) => { changed = true; return t + ' ' + (ap.toUpperCase() === 'AM' ? 'ص' : 'م'); });
            return changed ? out : null;
        }

        function builtin(s, depth) {
            let m;
            if ((m = /^Good (morning|afternoon|evening)(?:\s*,\s*|\s+)?(.*)$/i.exec(s))) return GREETING[m[1].toLowerCase()] + (m[2] ? '، ' + m[2] : '');
            if ((m = /^Welcome(?:\s*,\s*|\s+)(.+)$/i.exec(s))) return 'مرحباً، ' + m[1];
            if ((m = /^(.+?)\s\|\s(MARKAZ(?: [A-Za-z ]+)?)$/.exec(s))) { const left = translate(m[1], depth + 1); if (left) return left + ' | ' + (translate(m[2], depth + 1) || m[2]); }
            if ((m = /^(\d+(?:\.\d+)?)%\s*complete$/i.exec(s))) return m[1] + '% مكتمل';
            if ((m = /^(\d+)\s+of\s+(\d+)\s+marked$/i.exec(s))) return 'تم تسجيل ' + m[1] + ' من ' + m[2];
            if ((m = /^(\d+)\s+of\s+(\d+)\s+required subject entries completed$/i.exec(s))) return 'اكتمل ' + m[1] + ' من ' + m[2] + ' من مدخلات المواد المطلوبة';
            if ((m = /^(\d+)\s+students?\s+awaiting required subject assignments$/i.exec(s))) return m[1] + ' طالب بانتظار إسناد المواد المطلوبة';
            if ((m = /^(\d+)\s+(student|students|learner|learners|class|classes|subject|subjects|record|records|entry|entries|day|days|teacher|teachers|account|accounts|device|devices)$/i.exec(s))) return m[1] + ' ' + NOUNS[m[2].toLowerCase()];
            return null;
        }

        function tokens(s) {
            /* only strings made of numbers, punctuation and known words, e.g. "3 present · 1 absent" */
            const parts = s.split(/([A-Za-z]+)/);
            let any = false;
            const out = parts.map(p => {
                if (!/^[A-Za-z]+$/.test(p)) return p;
                const w = WORDS[p.toLowerCase()];
                if (w === undefined) { any = null; return p; }
                if (any !== null) any = true;
                return w;
            });
            return any ? out.join('') : null;
        }

        function translate(raw, depth = 0) {
            const whole = translateCore(raw, depth);
            if (whole != null) return whole;
            /* a leading icon character (for example "⌂　Dashboard") is kept as it is */
            const icon = /^[^A-Za-z0-9\u0600-\u06FF]+/.exec(raw);
            if (icon && /[A-Za-z]/.test(raw)) {
                const rest = translateCore(raw.slice(icon[0].length), depth);
                return rest == null ? null : icon[0] + rest;
            }
            return null;
        }

        function translateCore(raw, depth) {
            const core = soft(raw);
            if (!core || !LATIN.test(core) || ARABIC.test(core)) return null;
            if (depth === 0 && cache.has(core)) return cache.get(core);
            let result = null;
            const exact = D.exact[keyOf(core)];
            if (exact !== undefined) result = /…$/.test(core) ? exact.replace(/\.\.\.$/, '…') : exact;
            if (result == null) {
                for (const [re, template] of PATTERNS) {
                    const m = re.exec(core);
                    if (m) { result = fill(template, m, depth); break; }
                }
            }
            if (result == null) result = builtin(core, depth);
            if (result == null) result = dates(core);
            if (result == null && /[0-9·%/]/.test(core)) result = tokens(core);
            if (result != null) result = result.replace(/←/g, '\u0001').replace(/→/g, '←').replace(/\u0001/g, '→');
            if (depth === 0) cache.set(core, result);
            return result;
        }

        const ATTRS = ['placeholder', 'title', 'aria-label', 'alt', 'data-title'];
        const SKIP_TAGS = new Set(['SCRIPT', 'STYLE', 'TEXTAREA', 'CODE', 'PRE', 'NOSCRIPT', 'SVG', 'CANVAS']);

        function skipped(element) {
            return !element || SKIP_TAGS.has(element.tagName) || (element.closest && element.closest('[data-i18n-skip],[translate="no"],textarea,[contenteditable="true"]'));
        }

        function recordMissing(text) {
            const t = soft(text);
            if (t.length > 2 && missing.size < 400 && /[A-Za-z]{3}/.test(t) && !/^[A-Z0-9 _.-]{2,}$/.test(t) && !/^[a-z-]+$/.test(t)) missing.add(t);
        }

        function textNode(node) {
            const value = node.nodeValue;
            if (!value || !LATIN.test(value) || ARABIC.test(value)) return;
            if (skipped(node.parentElement)) return;
            const lead = value.match(/^\s*/)[0];
            const trail = value.match(/\s*$/)[0];
            const out = translate(value);
            if (out == null) { recordMissing(value); return; }
            const next = lead + out.replace(/,\s*$/, '،') + trail;
            if (next !== value) node.nodeValue = next;
        }

        function attrs(element) {
            if (skipped(element)) return;
            ATTRS.forEach(name => {
                const value = element.getAttribute && element.getAttribute(name);
                if (!value || !LATIN.test(value) || ARABIC.test(value)) return;
                const out = translate(value);
                if (out != null && out !== value) element.setAttribute(name, out);
                else if (out == null) recordMissing(value);
            });
            if (element.tagName === 'INPUT' && /^(button|submit|reset)$/i.test(element.type) && element.value && !ARABIC.test(element.value)) {
                const out = translate(element.value);
                if (out != null) element.value = out;
            }
        }

        function walk(node) {
            if (node.nodeType === 3) { textNode(node); return; }
            if (node.nodeType !== 1 || SKIP_TAGS.has(node.tagName)) return;
            if (node.matches && node.matches('[data-i18n-skip],[translate="no"]')) return;
            attrs(node);
            for (let child = node.firstChild; child; child = child.nextSibling) walk(child);
        }

        function run() {
            walk(document.documentElement);
            const title = translate(document.title);
            if (title) document.title = title;
        }

        const observer = new MutationObserver(records => {
            records.forEach(record => {
                if (record.type === 'childList') record.addedNodes.forEach(walk);
                else if (record.type === 'characterData') textNode(record.target);
                else if (record.type === 'attributes') attrs(record.target);
            });
        });

        function start() {
            run();
            observer.observe(document.documentElement, { subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ATTRS.concat(['value']) });
            root.classList.remove('mk-i18n-pending');
        }

        /* messages shown with alert / confirm / prompt */
        ['alert', 'confirm', 'prompt'].forEach(name => {
            const original = window[name];
            window[name] = function (message, ...rest) {
                let text = message == null ? '' : String(message);
                if (LATIN.test(text) && !ARABIC.test(text)) {
                    const whole = translate(text);
                    text = whole != null ? whole : text.split('\n').map(line => (line.trim() ? (translate(line) ?? line) : line)).join('\n');
                }
                return original.call(window, text, ...rest);
            };
        });

        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
        else start();
        window.setTimeout(() => root.classList.remove('mk-i18n-pending'), 1500);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startSwitch);
    else startSwitch();
})();
