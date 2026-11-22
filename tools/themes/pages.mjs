/* Which pages get dark mode / Arabic, and which stylesheets each page loads.
   The theme builders generate ONE file per page from exactly those stylesheets,
   so a rule written for one page can never leak onto another page.
   To add a page: add it to STAFF (dark mode + Arabic) or AUTH (Arabic only), then run
   tools/build-themes.sh. */
import fs from 'node:fs';
import path from 'node:path';

export const PUBLIC = path.join(process.cwd(), 'public');

export const STAFF = [
  'dashboard.html', 'students.html', 'classes.html', 'class.html', 'settings.html', 'attendance.html',
  'school-fees.html', 'online-class.html', 'subjects/index.html', 'class-subjects/index.html', 'staff-chat.html'
];
export const AUTH = [
  'login.html', 'teacher-login.html', 'management-login.html', 'exam-officer-login.html',
  'student-login.html', 'forgot-password.html', 'reset-password.html', 'online-class-login.html'
];

/* stylesheets that a page's scripts add after loading */
export const EXTRA_CSS = { 'dashboard.html': ['css/markaz-ai.css'] };

/* "subjects/index.html" -> "subjects", "dashboard.html" -> "dashboard" (same rule is used by the browser scripts) */
export const keyOf = page => {
  const parts = page.replace(/\.html$/, '').split('/');
  const last = parts[parts.length - 1];
  return last === 'index' && parts.length > 1 ? parts[parts.length - 2] : last;
};

export function cssFor(page) {
  const html = fs.readFileSync(path.join(PUBLIC, page), 'utf8');
  const dir = path.dirname(page);
  const files = [];
  for (const m of html.matchAll(/<link[^>]+rel=["']stylesheet["'][^>]*>/g)) {
    const href = (/href=["']([^"']+)["']/.exec(m[0]) || [])[1];
    if (!href || /^https?:/.test(href)) continue;
    const clean = href.split('?')[0];
    if (/backup|dark-mode|rtl|i18n-extras/.test(clean)) continue;
    const rel = clean.startsWith('/') ? clean.slice(1) : path.normalize(path.join(dir, clean)).replace(/\\/g, '/');
    if (fs.existsSync(path.join(PUBLIC, rel)) && !files.includes(rel)) files.push(rel);
  }
  (EXTRA_CSS[page] || []).forEach(f => { if (fs.existsSync(path.join(PUBLIC, f)) && !files.includes(f)) files.push(f); });
  return files;
}
