(() => {
  "use strict";
  const KEY = "markaz.theme";
  const root = document.documentElement;
  const media = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;
  const readStored = () => { try { const value = localStorage.getItem(KEY); return value === "light" || value === "dark" ? value : null; } catch (_) { return null; } };
  const systemTheme = () => media && media.matches ? "dark" : "light";
  let theme = root.dataset.theme === "dark" ? "dark" : (readStored() || systemTheme());
  const meta = () => document.querySelector('meta[name="theme-color"]');
  function paint(next, persist) {
    theme = next === "dark" ? "dark" : "light";
    root.dataset.theme = theme;
    root.style.colorScheme = theme;
    const tag = meta();
    if (tag) tag.content = theme === "dark" ? "#0a120d" : "#f3f6f2";
    if (persist) { try { localStorage.setItem(KEY, theme); } catch (_) {} }
    document.querySelectorAll("[data-markaz-theme-toggle]").forEach(button => {
      button.setAttribute("aria-pressed", String(theme === "dark"));
      button.setAttribute("aria-label", theme === "dark" ? "Switch to light mode" : "Switch to dark mode");
      const icon = button.querySelector("[data-markaz-theme-icon]");
      if (icon) icon.innerHTML = theme === "dark" ? '<i class="fa-solid fa-sun" aria-hidden="true"></i>' : '<i class="fa-solid fa-moon" aria-hidden="true"></i>';
    });
  }
  function mount() {
    const host = document.querySelector("[data-markaz-preferences],.markaz-shell-profile-menu,.dashboard-profile-menu");
    if (!host || host.querySelector("[data-markaz-theme-toggle]")) { paint(theme, false); return; }
    const button = document.createElement("button");
    button.type = "button";
    button.className = "markaz-preference-action markaz-theme-action";
    button.dataset.markazThemeToggle = "";
    button.innerHTML = '<span class="markaz-preference-icon" data-markaz-theme-icon aria-hidden="true"></span><span class="markaz-preference-copy"><strong>Appearance</strong><small>Light / Dark</small></span><span class="markaz-preference-switch" aria-hidden="true"><i></i></span>';
    host.appendChild(button);
    paint(theme, false);
  }
  document.addEventListener("click", event => {
    const button = event.target.closest("[data-markaz-theme-toggle]");
    if (button) paint(theme === "dark" ? "light" : "dark", true);
  });
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", mount, { once: true }); else mount();
  const observer = new MutationObserver(mount);
  observer.observe(document.documentElement, { childList: true, subtree: true });
  if (media && media.addEventListener) media.addEventListener("change", event => { if (!readStored()) paint(event.matches ? "dark" : "light", false); });
  window.addEventListener("storage", event => { if (event.key === KEY) paint(readStored() || systemTheme(), false); });
  paint(theme, false);
  window.markazTheme = { get theme() { return theme; }, setTheme(next) { paint(next, true); } };
})();