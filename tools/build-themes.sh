#!/bin/sh
# Rebuilds every generated theme file: dark mode, right-to-left layout and the Arabic dictionary.
# Run from the repo root after changing any stylesheet, adding a page, or adding translations.
#   npm i --no-save postcss culori rtlcss      (first time only)
#   sh tools/build-themes.sh
set -e
node tools/dark-theme/build.mjs
node tools/i18n/build-rtl.mjs
python3 tools/i18n/build-dictionary.py
