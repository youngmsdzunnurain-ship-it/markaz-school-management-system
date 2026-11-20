# Dark mode, Arabic and English: how it works and how to keep it up to date

Nothing in the existing pages was restyled by hand. Three **generated** things keep every page in step:

| What | Generated from | Output |
|---|---|---|
| Dark mode | the light stylesheets each staff page loads | `public/css/theme/<page>.dark.css` |
| Arabic (right-to-left) layout | the same stylesheets, mirrored | `public/css/theme/<page>.rtl.css` |
| Arabic text | `tools/i18n/dictionary/*.tsv` | `public/js/i18n-ar.js` |

Hand-written details live in `public/css/dark-mode-extras.css`, `public/css/rtl-extras.css` and
`public/css/i18n-extras.css`. The browser scripts are `lang-init.js`, `i18n.js`, `theme-init.js`, `theme-toggle.js`.

## Everything is automatic
A GitHub Action (`.github/workflows/rebuild-themes.yml`) rebuilds the generated files and commits them whenever
a stylesheet, a page or a translation file changes on `main`. You can also run it by hand:

    npm i --no-save postcss culori rtlcss      # first time only
    sh tools/build-themes.sh

## A new feature or page on an existing staff page
Style it normally (light theme). Dark mode and the Arabic layout follow automatically after the rebuild.
New on-screen English text: add one line `English<TAB>Arabic` to a file in `tools/i18n/dictionary/` (use `{}` for a
changing number or name and `{0}`, `{1}` in the Arabic). Until a line is added, that text simply stays English.

## A brand-new page
1. Add the page to `STAFF` (dark mode + Arabic) or `AUTH` (Arabic only) in `tools/themes/pages.mjs`.
2. Copy the 3-6 head lines (after `<meta name="viewport">`) from `dashboard.html` (staff) or `login.html` (sign-in).
3. Run the rebuild.

## Finding English text that is not translated yet
Open any page in Arabic, press F12, and run `MarkazI18n.missing()` in the console. It lists every untranslated text
the page has shown so far. Add them to the dictionary.

## Deliberately left out
Report cards, printed results and the student result pages stay in their official form (light, English) so a printed
document is always the same. Printing is always light, whatever theme is on screen.

## Dashboard chart and card polish
`public/css/dashboard-polish-2026.css` (with `js/chart-defs-2026.js`, which only adds colour gradients) styles the attendance and
result charts and cards on every dashboard role. The MARKAZ Online Class workspace uses `js/page-fit.js`, the same fit-to-screen
scaling as the Dashboard, so both have the same proportions. It is hand-written CSS: edit it directly.
