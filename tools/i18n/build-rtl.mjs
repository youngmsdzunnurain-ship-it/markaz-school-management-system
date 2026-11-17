/* MARKAZ right-to-left builder
   For every page, flips the stylesheets THAT PAGE LOADS with rtlcss and writes only
   the rules that change into public/css/theme/<page>.rtl.css, prefixed with
   html[dir="rtl"]. The original stylesheets are never edited. One file per page
   means a rule written for one page can never leak onto another.
   Run tools/build-themes.sh after changing CSS or adding a page. */
import fs from 'node:fs';
import path from 'node:path';
import postcss from 'postcss';
import rtlcss from 'rtlcss';
import { STAFF, AUTH, PUBLIC, cssFor, keyOf } from '../themes/pages.mjs';

const OUTDIR = path.join(PUBLIC, 'css', 'theme');
const PREFIX = 'html[dir="rtl"]';
const prefix = sel => {
  const s = sel.trim();
  if (s === ':root' || s === 'html') return PREFIX;
  if (/^(:root|html)(?=[.:#\[])/.test(s)) return s.replace(/^(:root|html)/, PREFIX);
  if (/^(:root|html)\s/.test(s)) return s.replace(/^(:root|html)/, PREFIX);
  return `${PREFIX} ${s}`;
};
const RESET = [
  [/^margin-(left|right)$/, '0'], [/^padding-(left|right)$/, '0'], [/^(left|right)$/, 'auto'],
  [/^border-(left|right)(-width)?$/, '0'], [/^border-(top|bottom)-(left|right)-radius$/, '0'],
  [/^inset-inline-(start|end)$/, 'auto']
];
const reset = prop => { const hit = RESET.find(([re]) => re.test(prop)); return hit ? hit[1] : null; };

/* ---- spacing / position sides ------------------------------------------------
   padding, margin and left/right are mirrored by SWAPPING the effective left and
   right values of a selector (so a rule that sets only padding-right can never wipe
   out a padding-left that another rule gave the same selector). */
function splitValues(value) {
  const out = []; let depth = 0, cur = '';
  for (const ch of value.trim()) {
    if (ch === '(') depth++; if (ch === ')') depth--;
    if (/\s/.test(ch) && depth === 0) { if (cur) out.push(cur); cur = ''; } else cur += ch;
  }
  if (cur) out.push(cur); return out;
}
function expandBox(prop, value) {
  const v = splitValues(value.replace(/\s*!important\s*$/i, ''));
  if (!v.length || v.length > 4) return [];
  const [t, r = t, bo = t, l = r] = v;
  return [[`${prop}-top`, t], [`${prop}-right`, r], [`${prop}-bottom`, bo], [`${prop}-left`, l]];
}
const SIDE = /^(padding|margin)-(left|right)$/;
const cloneEff = eff => new Map([...eff].map(([k, v]) => [k, new Map(v)]));

function buildPage(page) {
  const files = cssFor(page);
  const chunks = []; let rules = 0;
  const rootEff = new Map();               /* selector -> Map(prop -> {value, important}) */

  for (const file of files) {
    const css = fs.readFileSync(path.join(PUBLIC, file), 'utf8');
    const original = postcss.parse(css);
    const flipped = postcss.parse(rtlcss.process(css, { autoRename: false, clean: false }));
    const out = postcss.root();

    const diffRule = (o, f, eff) => {
      const key = o.selector;
      const e = eff.get(key) || new Map(); eff.set(key, e);
      let touched = false;
      o.nodes.forEach(d => {
        if (d.type !== 'decl') return;
        const imp = d.important;
        if (d.prop === 'padding' || d.prop === 'margin') {
          expandBox(d.prop, d.value).forEach(([p, v]) => e.set(p, { value: v, important: imp }));
          touched = true;
        } else if (d.prop === 'inset') {
          const sides = expandBox('inset', d.value);
          sides.forEach(([p, v]) => { if (p === 'inset-left') e.set('left', { value: v, important: imp }); if (p === 'inset-right') e.set('right', { value: v, important: imp }); });
          if (sides.length) touched = true;
        } else if (SIDE.test(d.prop) || d.prop === 'left' || d.prop === 'right') {
          e.set(d.prop, { value: d.value, important: imp }); touched = true;
        } else if (/^(padding|margin)-(top|bottom)$/.test(d.prop)) {
          e.set(d.prop, { value: d.value, important: imp });
        }
      });

      /* everything EXCEPT spacing/position is compared before and after flipping */
      const skip = p => /^(padding|margin)(-(left|right|top|bottom))?$/.test(p) || p === 'left' || p === 'right';
      const final = nodes => { const m = new Map(); nodes.filter(n => n.type === 'decl' && !skip(n.prop)).forEach(n => m.set(n.prop, { value: n.value, important: n.important })); return m; };
      const before = final(o.nodes), after = final(f.nodes);
      const emit = [];
      after.forEach((v, prop) => {
        const was = before.get(prop);
        if (!was || was.value !== v.value) emit.push(postcss.decl({ prop, value: v.value, important: v.important }));
      });
      before.forEach((v, prop) => {
        if (after.has(prop)) return;
        const m = /^border-(left|right)(-width)?$/.test(prop) || /^border-(top|bottom)-(left|right)-radius$/.test(prop);
        if (m) emit.unshift(postcss.decl({ prop, value: '0', important: v.important }));
      });

      if (touched) {
        ['padding', 'margin'].forEach(fam => {
          const L = e.get(`${fam}-left`), R = e.get(`${fam}-right`);
          if ((L && L.value) === (R && R.value)) return;
          emit.push(postcss.decl({ prop: `${fam}-left`, value: R ? R.value : '0', important: !!(R && R.important) }));
          emit.push(postcss.decl({ prop: `${fam}-right`, value: L ? L.value : '0', important: !!(L && L.important) }));
        });
        const L = e.get('left'), R = e.get('right');
        if ((L && L.value) !== (R && R.value) && (L || R)) {
          emit.push(postcss.decl({ prop: 'left', value: R ? R.value : 'auto', important: !!(R && R.important) }));
          emit.push(postcss.decl({ prop: 'right', value: L ? L.value : 'auto', important: !!(L && L.important) }));
        }
      }
      if (!emit.length) return null;
      const r = postcss.rule({ selector: o.selectors.map(prefix).join(',\n') });
      emit.forEach(d => r.append(d)); rules++; return r;
    };

    const walk = (a, b, target, eff) => {
      const an = a.nodes || [], bn = b.nodes || [];
      if (an.length !== bn.length) { console.warn(`! ${file}: structure differs, skipped one block`); return; }
      an.forEach((node, i) => {
        const other = bn[i];
        if (node.type === 'rule' && other.type === 'rule') { const r = diffRule(node, other, eff); if (r) target.append(r); }
        else if (node.type === 'atrule' && other.type === 'atrule') {
          if (/keyframes|font-face|page/.test(node.name)) return;
          if (node.name === 'media' && /\bprint\b/.test(node.params)) return;
          if (['media', 'supports', 'layer'].includes(node.name)) {
            const holder = postcss.atRule({ name: node.name, params: node.params });
            walk(node, other, holder, eff); if (holder.nodes && holder.nodes.length) target.append(holder);
          }
        }
      });
    };
    walk(original, flipped, out, rootEff);
    if (out.nodes.length) chunks.push(`/* ---- ${file} ---- */\n` + out.toString().replace(/\n\s*\n/g, '\n'));
  }
  const header = `/* ==========================================================================
   MARKAZ right-to-left layout for ${page}: GENERATED FILE. Do not edit by hand.
   Built by tools/i18n/build-rtl.mjs from the stylesheets this page loads.
   Applies only when <html dir="rtl"> (Arabic). Hand-written details: rtl-extras.css.
   ========================================================================== */
`;
  const target = path.join(OUTDIR, keyOf(page) + '.rtl.css');
  fs.writeFileSync(target, header + chunks.join('\n') + '\n');
  console.log(`${keyOf(page).padEnd(24)} ${(fs.statSync(target).size / 1024).toFixed(1).padStart(6)} KB  ${String(rules).padStart(4)} rules  (${files.length} stylesheets)`);
}

fs.mkdirSync(OUTDIR, { recursive: true });
[...STAFF, ...AUTH].forEach(buildPage);
