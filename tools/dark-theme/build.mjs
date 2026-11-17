/* MARKAZ dark theme builder
   --------------------------------------------------------------------------
   For every staff page, reads the light stylesheets THAT PAGE LOADS and writes
   public/css/theme/<page>.dark.css: the same rules prefixed with
   html[data-theme="dark"], with every colour translated by ONE set of rules
   (mapBackground / mapText / mapBorder / mapShadow below). One file per page
   means a rule written for one page can never leak onto another.

   No page is styled by hand. To change how dark mode looks, change the palette
   numbers below and run tools/build-themes.sh. When new CSS or a new page is
   added, run it again (a GitHub Action also does this automatically).
*/
import fs from 'node:fs';
import path from 'node:path';
import postcss from 'postcss';
import { parse, converter, clampChroma, formatHex, formatRgb } from 'culori';
import { STAFF, PUBLIC, cssFor, keyOf } from '../themes/pages.mjs';

const OUTDIR = path.join(PUBLIC, 'css', 'theme');
const toOklch = converter('oklch');
const PREFIX = 'html[data-theme="dark"]';

/* ---- palette rules ------------------------------------------------------ */
function curve(points, x) {
  if (x >= points[0][0]) return points[0][1];
  for (let i = 1; i < points.length; i++) {
    const [x1, y1] = points[i - 1], [x2, y2] = points[i];
    if (x >= x2) return y2 + (y1 - y2) * ((x - x2) / (x1 - x2));
  }
  return points[points.length - 1][1];
}
/* light surface lightness -> dark surface lightness (page < card < raised) */
const SURFACE = [[1.0, .24], [.985, .23], [.97, .205], [.94, .205], [.91, .265], [.85, .30], [.75, .34], [.62, .38]];
/* the page itself (body / html) sits one step below the cards */
const PAGE = [[1.0, .19], [.985, .175], [.94, .17], [.91, .20], [.75, .30], [.62, .36]];
/* hover / selected states must get LIGHTER than the surface under them */
const RAISED = [[1.0, .30], [.97, .30], [.93, .32], [.88, .35], [.75, .39], [.62, .42]];
/* dark text lightness -> light text lightness */
const TEXT = [[.78, .78], [.6, .74], [.5, .79], [.4, .84], [.25, .92], [0, .96]];
/* light border lightness -> dark border lightness */
const EDGE = [[1.0, .37], [.8, .37], [.62, .44]];

function out(l, c, h, alpha) {
  const neutral = (c ?? 0) < .008 || h == null;
  const col = clampChroma({ mode: 'oklch', l, c: neutral ? .012 : c, h: neutral ? 165 : h, alpha }, 'oklch');
  return alpha != null && alpha < 1 ? formatRgb(col) : formatHex(col);
}

let raisedState = false;   /* translating a :hover / :focus / .active rule */
let pageState = false;     /* translating body / html */

function mapBackground(color) {
  const k = toOklch(color); const a = color.alpha ?? 1;
  if (a === 0) return null;
  const c = k.c ?? 0;
  if (k.l >= .62 && c < .085) return out(curve(raisedState ? RAISED : pageState ? PAGE : SURFACE, k.l), Math.min(c * 1.1, .03), k.h, a);
  return null; /* dark surfaces and saturated brand colours stay as they are */
}
function mapText(color) {
  const k = toOklch(color); const a = color.alpha ?? 1;
  if (a === 0 || k.l >= .78) return null;       /* light text is meant for dark surfaces */
  return out(curve(TEXT, k.l), (k.c ?? 0) * .85, k.h, a);
}
function mapBorder(color) {
  const k = toOklch(color); const a = color.alpha ?? 1;
  if (a === 0) return null;
  const c = k.c ?? 0;
  if (k.l >= .62 && c < .085) return out(curve(EDGE, k.l), Math.min(c, .03), k.h, a);
  return null;
}
function mapShadow(color) {
  const k = toOklch(color); const a = color.alpha ?? 1;
  if (a === 0) return null;
  if (k.l >= .93 && (k.c ?? 0) < .03) return `rgba(255,255,255,${+(a * .06).toFixed(3)})`; /* white highlights */
  if (k.l < .75 && (k.c ?? 0) < .18) return `rgba(0,0,0,${+Math.min(.85, a * 1.8 + .04).toFixed(3)})`;
  return null; /* bright coloured glows stay */
}

const MAPPERS = [
  [/^(background|background-color|background-image)$/, mapBackground],
  [/^(color|fill|stroke|caret-color|text-decoration-color|-webkit-text-fill-color|text-emphasis-color)$/, mapText],
  [/^(border(-top|-right|-bottom|-left|-block|-inline)?(-start|-end)?(-color)?|outline(-color)?|column-rule(-color)?)$/, mapBorder],
  [/^(box-shadow|text-shadow)$/, mapShadow]
];

/* ---- colour tokens in a value ------------------------------------------ */
const TOKEN = /#[0-9a-fA-F]{8}\b|#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3,4}\b|rgba?\([^)]*\)|hsla?\([^)]*\)|\b(?:white|black)\b/g;

function transformValue(value, mapper) {
  return value.split(/(url\([^)]*\))/g).map(part => {          /* never touch url(...) */
    if (part.startsWith('url(')) return part;
    return part.replace(TOKEN, token => {
      const parsed = parse(token);
      if (!parsed) return token;
      return mapper(parsed) ?? token;
    });
  }).join('');
}

/* ---- sources and CSS variables ------------------------------------------ */
function source(file) {
  const text = fs.readFileSync(path.join(PUBLIC, file), 'utf8');
  if (!file.endsWith('.html')) return text;
  return [...text.matchAll(/<style[^>]*>([\s\S]*?)<\/style>/g)].map(m => m[1]).join('\n');
}

function collectVars(files) {
  const perFile = new Map(); const global = new Map();
  for (const file of files) {
    const vars = new Map();
    postcss.parse(source(file)).walkRules(rule => {
      if (!/^(:root|html)$/.test(rule.selector.trim())) return;
      rule.walkDecls(/^--/, d => { vars.set(d.prop, d.value); global.set(d.prop, d.value); });
    });
    perFile.set(file, vars);
  }
  return { perFile, global };
}

function resolve(value, local, global, depth = 0) {
  if (depth > 8) return null;
  let failed = false;
  const result = value.replace(/var\(\s*(--[\w-]+)\s*(?:,\s*([^()]*(?:\([^()]*\)[^()]*)*))?\)/g, (m, name, fallback) => {
    const found = local.get(name) ?? global.get(name);
    if (found != null) { const inner = resolve(found, local, global, depth + 1); if (inner == null) { failed = true; return m; } return inner; }
    if (fallback != null) { const inner = resolve(fallback.trim(), local, global, depth + 1); if (inner == null) { failed = true; return m; } return inner; }
    failed = true; return m;
  });
  return failed ? null : result;
}

function prefixSelector(sel) {
  const s = sel.trim();
  if (s === ':root' || s === 'html') return PREFIX;
  if (/^(:root|html)(?=[.:#\[])/.test(s)) return s.replace(/^(:root|html)/, PREFIX);
  if (/^(:root|html)\s/.test(s)) return s.replace(/^(:root|html)/, PREFIX);
  return `${PREFIX} ${s}`;
}

/* ---- build one page ------------------------------------------------------ */
function buildPage(page) {
  const hasStyle = /<style[^>]*>[^<]*[#r]/.test(fs.readFileSync(path.join(PUBLIC, page), 'utf8'));
  const FILES = [...cssFor(page), ...(hasStyle ? [page] : [])];
  const { perFile, global } = collectVars(FILES);
  const chunks = []; const stats = { rules: 0, unresolved: new Set() };

  for (const file of FILES) {
    const local = perFile.get(file);
    const root = postcss.parse(source(file));
    const emitted = postcss.root();

    /* a rule that keeps a brand/dark background keeps its text colour too */
    const keepsBackground = rule => {
      let kept = false;
      rule.each(node => {
        if (node.type !== 'decl' || !/^(background|background-color|background-image)$/.test(node.prop)) return;
        let v = node.value;
        if (/var\(/.test(v)) { const r = resolve(v, local, global); if (r == null) { kept = true; return; } v = r; }
        const tokens = v.split(/(url\([^)]*\))/g).filter(x => !x.startsWith('url(')).join(' ').match(TOKEN) || [];
        let mapped = false, any = false;
        tokens.forEach(t => { const c = parse(t); if (!c || (c.alpha ?? 1) === 0) return; any = true; if (mapBackground(c) != null) mapped = true; });
        if (any && !mapped) kept = true;
      });
      return kept;
    };

    const roleByName = name => /(border|line|edge|divider)/i.test(name) ? mapBorder
      : /(bg|background|surface|card|soft|tint|track|fill|panel)/i.test(name) ? mapBackground
      : /(text|muted|ink|color|dark|green|gold|red|blue|label)/i.test(name) ? mapText : null;

    const handleRule = rule => {
      const decls = [];
      const isRoot = /^(:root|html)$/.test(rule.selector.trim());
      const keepText = keepsBackground(rule);
      pageState = rule.selectors.some(x => /^(html|body|:root)(?![\w-])/.test(x.trim()));
      raisedState = /:hover|:focus|:active|:checked|\.active\b|\.is-active\b|\.selected\b|\[aria-selected=.true.\]|\[open\]/.test(rule.selector);
      rule.each(node => {
        if (node.type !== 'decl') return;
        if (node.prop.startsWith('--')) {
          if (isRoot) return;
          const mapper = roleByName(node.prop); if (!mapper) return;
          const nv = transformValue(node.value, mapper);
          if (nv !== node.value) decls.push(postcss.decl({ prop: node.prop, value: nv, important: node.important }));
          return;
        }
        const entry = MAPPERS.find(([re]) => re.test(node.prop));
        if (!entry) return;
        if (keepText && entry[1] === mapText) return;
        let value = node.value;
        if (/var\(/.test(value)) {
          const resolved = resolve(value, local, global);
          if (resolved == null) {
            stats.unresolved.add(`${page}  ${file}  ${rule.selector}  ${node.prop}`);
            const part = transformValue(value, entry[1]);
            if (part !== value) decls.push(postcss.decl({ prop: node.prop, value: part, important: node.important }));
            return;
          }
          value = resolved;
        }
        const next = transformValue(value, entry[1]);
        if (next !== value || (node.value !== value && next !== node.value)) {
          decls.push(postcss.decl({ prop: node.prop, value: next, important: node.important }));
        }
      });
      if (!decls.length) return null;
      stats.rules++;
      const r = postcss.rule({ selector: rule.selectors.map(prefixSelector).join(',\n') });
      decls.forEach(d => r.append(d)); return r;
    };

    const walk = (container, target) => {
      container.each(node => {
        if (node.type === 'rule') { const r = handleRule(node); if (r) target.append(r); }
        else if (node.type === 'atrule') {
          if (/keyframes|font-face|page|counter-style/.test(node.name)) return;
          if (node.name === 'media' && /\bprint\b/.test(node.params)) return;
          if (['media', 'supports', 'layer'].includes(node.name)) {
            const holder = postcss.atRule({ name: node.name, params: node.params });
            walk(node, holder); if (holder.nodes && holder.nodes.length) target.append(holder);
          }
        }
      });
    };
    walk(root, emitted);
    if (emitted.nodes.length) chunks.push(`/* ---- ${file} ---- */\n` + emitted.toString().replace(/\n\s*\n/g, '\n'));
  }

  const header = `/* ==========================================================================
   MARKAZ dark mode for ${page}: GENERATED FILE. Do not edit by hand.
   Built by tools/dark-theme/build.mjs from the light stylesheets this page loads.
   Applies only when <html data-theme="dark"> (staff pages only). Not applied when printing.
   ========================================================================== */
@media screen {
${PREFIX} { color-scheme: dark; }
`;
  const target = path.join(OUTDIR, keyOf(page) + '.dark.css');
  fs.writeFileSync(target, header + chunks.join('\n') + '\n}\n');
  console.log(`${keyOf(page).padEnd(16)} ${(fs.statSync(target).size / 1024).toFixed(1).padStart(6)} KB  ${String(stats.rules).padStart(4)} rules  (${FILES.length} stylesheets, ${stats.unresolved.size} unresolved)`);
}

fs.mkdirSync(OUTDIR, { recursive: true });
STAFF.forEach(buildPage);
