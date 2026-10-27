(() => {
  "use strict";

  const endpoint = new URL("api/settings/website-images.php", document.baseURI);

  function normalise(value, base = document.baseURI) {
    if (!value || /^(data:|blob:|javascript:)/i.test(value)) return null;

    try {
      const url = new URL(value, base);
      if (!["http:", "https:"].includes(url.protocol)) return null;
      return url.origin === location.origin ? url.pathname : url.href;
    } catch {
      return null;
    }
  }

  function applyOverrides(images) {
    const replacements = new Map();

    for (const item of images) {
      if (!item.replacement) continue;
      const key = normalise(item.url);
      if (key) replacements.set(key, item.replacement);
    }

    if (!replacements.size) return;

    function findReplacement(value, base = document.baseURI) {
      const key = normalise(value, base);
      return key ? replacements.get(key) : null;
    }

    document.querySelectorAll("img[src], source[src]").forEach(element => {
      const replacement = findReplacement(element.getAttribute("src"));
      if (replacement) element.setAttribute("src", replacement);
    });

    document.querySelectorAll("img[srcset], source[srcset]").forEach(element => {
      const srcset = element.getAttribute("srcset");
      if (!srcset) return;

      const updated = srcset.split(",").map(candidate => {
        const parts = candidate.trim().split(/\s+/);
        const replacement = findReplacement(parts[0]);
        if (replacement) parts[0] = replacement;
        return parts.join(" ");
      }).join(", ");

      element.setAttribute("srcset", updated);
    });

    document.querySelectorAll('link[rel~="icon"], link[rel="apple-touch-icon"]').forEach(element => {
      const replacement = findReplacement(element.getAttribute("href"));
      if (replacement) element.setAttribute("href", replacement);
    });

    function updateBackground(value, base) {
      return value.replace(
        /url\(\s*(["']?)(.*?)\1\s*\)/gi,
        (whole, quote, url) => {
          const replacement = findReplacement(url, base);
          if (!replacement) return whole;

          try {
            return `url("${new URL(replacement, document.baseURI).href}")`;
          } catch {
            return whole;
          }
        }
      );
    }

    document.querySelectorAll("[style]").forEach(element => {
      const original = element.style.backgroundImage;
      if (original) element.style.backgroundImage =
        updateBackground(original, document.baseURI);
    });

    function visitRules(rules, base) {
      for (const rule of rules) {
        if (rule.style && rule.style.backgroundImage) {
          const original = rule.style.backgroundImage;
          const updated = updateBackground(original, base);
          if (updated !== original) rule.style.backgroundImage = updated;
        }

        // Also inspect nested @media and other grouping rules.
        try {
          if (rule.cssRules) visitRules(rule.cssRules, base);
        } catch {
          // Some browser rules cannot be inspected.
        }
      }
    }

    for (const sheet of document.styleSheets) {
      try {
        visitRules(sheet.cssRules, sheet.href || document.baseURI);
      } catch {
        // Cross-origin stylesheets are protected by browser security.
      }
    }
  }

  fetch(endpoint.href, {
    credentials: "same-origin",
    cache: "no-store"
  })
    .then(response => {
      if (!response.ok) throw new Error("Image catalogue unavailable");
      return response.json();
    })
    .then(data => {
      if (data.success && Array.isArray(data.images)) {
        applyOverrides(data.images);
      }
    })
    .catch(error => console.warn("MARKAZ image overrides:", error.message));
})();
