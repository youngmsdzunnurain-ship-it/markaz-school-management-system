/* MARKAZ right-to-left builder
   Reads the light/LTR stylesheets, flips every physical left/right rule with
   rtlcss, and writes ONLY the rules that change into public/css/rtl.css, prefixed
   with html[dir="rtl"]. The original files are never edited.
       npm i --no-save postcss rtlcss
       node tools/i18n/build-rtl.mjs
   Re-run whenever a stylesheet changes. */
import fs from 'node:fs';
import path from 'node:path';
import postcss from 'postcss';
import rtlcss from 'rtlcss';

const PUBLIC = path.join(process.cwd(), 'public');
const OUT = path.join(PUBLIC, 'css', 'rtl.css');
const FILES = [
  'css/global.css', 'css/dashboard.css', 'css/dashboard-2026.css', 'css/standard-page-heading.css',
  'css/management-shell.css', 'css/app-watermark.css', 'css/website-image-polish.css',
  'css/account-devices.css', 'css/students.css', 'css/classes.css', 'css/class.css',
  'css/attendance.css', 'css/settings.css', 'css/settings-panel-navigation.css',
  'css/online-class.css', 'subjects/subjects.css', 'class-subjects/class-subjects.css',
  'css/book-auth.css', 'css/student-results-portal.css', 'css/app-motion-2026.css'
].filter(f => fs.existsSync(path.join(PUBLIC, f)));

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

let rules = 0;
const chunks = [];
for (const file of FILES) {
  const css = fs.readFileSync(path.join(PUBLIC, file), 'utf8');
  const original = postcss.parse(css);
  const flipped = postcss.parse(rtlcss.process(css, { autoRename: false, clean: false }));
  const out = postcss.root();

  const diffRule = (o, f) => {
    /* Compare the FINAL value of every property before and after flipping, so
       swapped pairs (padding-left <-> padding-right) cannot cancel each other out. */
    const final = nodes => { const m = new Map(); nodes.filter(n => n.type === 'decl').forEach(n => m.set(n.prop, { value: n.value, important: n.important })); return m; };
    const before = final(o.nodes), after = final(f.nodes);
    const emit = [];
    after.forEach((v, prop) => {
      const was = before.get(prop);
      if (!was || was.value !== v.value) emit.push(postcss.decl({ prop, value: v.value, important: v.important }));
    });
    before.forEach((v, prop) => {
      if (after.has(prop)) return;
      const r = reset(prop);
      if (r !== null) emit.unshift(postcss.decl({ prop, value: r, important: v.important }));
    });
    if (!emit.length) return null;
    const r = postcss.rule({ selector: o.selectors.map(prefix).join(',\n') });
    emit.forEach(d => r.append(d)); rules++; return r;
  };

  const walk = (a, b, target) => {
    const an = a.nodes || [], bn = b.nodes || [];
    if (an.length !== bn.length) { console.warn(`! ${file}: structure differs, skipped one block`); return; }
    an.forEach((node, i) => {
      const other = bn[i];
      if (node.type === 'rule' && other.type === 'rule') { const r = diffRule(node, other); if (r) target.append(r); }
      else if (node.type === 'atrule' && other.type === 'atrule') {
        if (/keyframes|font-face|page/.test(node.name)) return;
        if (node.name === 'media' && /\bprint\b/.test(node.params)) return;
        if (['media', 'supports', 'layer'].includes(node.name)) {
          const holder = postcss.atRule({ name: node.name, params: node.params });
          walk(node, other, holder); if (holder.nodes && holder.nodes.length) target.append(holder);
        }
      }
    });
  };
  walk(original, flipped, out);
  if (out.nodes.length) chunks.push(`/* ---- ${file} ---- */\n` + out.toString().replace(/\n\s*\n/g, '\n'));
}

const header = `/* ==========================================================================
   MARKAZ right-to-left layout: GENERATED FILE. Do not edit by hand.
   Built by tools/i18n/build-rtl.mjs from the existing stylesheets. Applies only
   when the page has <html dir="rtl"> (Arabic). Hand-written Arabic details are
   in rtl-extras.css.
   ========================================================================== */
`;
fs.writeFileSync(OUT, header + chunks.join('\n') + '\n');
console.log(`rtl.css: ${(fs.statSync(OUT).size / 1024).toFixed(1)} KB, ${rules} rules from ${FILES.length} files`);
