# MARKAZ dark theme

`public/css/dark-mode.css` is GENERATED. Do not edit it by hand.

It is built from the light stylesheets by `build.mjs`, which translates every colour
with one set of rules (backgrounds, text, borders, shadows). To change how dark mode
looks, change the palette numbers at the top of `build.mjs`, then run:

    npm i --no-save postcss culori
    node tools/dark-theme/build.mjs

Run it again whenever a light stylesheet changes (new colours, new components).

Hand-written dark details (the switch itself, SVG charts, autofill, scrollbars) live in
`public/css/dark-mode-extras.css`.

Scope: only the staff pages load the theme files (Dashboard, Students, Classes, Class results,
Subjects, Class Subjects, Attendance, Settings, School Fees, MARKAZ Online). Public pages, sign-in
pages, the student portal and report/print pages never load them.
