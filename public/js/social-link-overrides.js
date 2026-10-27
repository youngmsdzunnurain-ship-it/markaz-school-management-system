(() => {
  "use strict";

  const endpoint = "api/settings/social-links.php";

  async function applySocialLinks() {
    try {
      const response = await fetch(endpoint, {
        credentials: "same-origin",
        cache: "no-store"
      });

      if (!response.ok) throw new Error("Could not load social links.");

      const result = await response.json();
      if (!result.success || !result.links) return;

      const platforms = {
        facebook: "facebook",
        instagram: "instagram",
        x: "x",
        youtube: "youtube",
        tiktok: "tiktok"
      };

      document.querySelectorAll(".footer-socials a[aria-label]").forEach(link => {
        const label = link.getAttribute("aria-label").toLowerCase();
        const platform = Object.keys(platforms).find(key =>
          label.startsWith(platforms[key])
        );

        if (!platform) return;

        const url = result.links[platform] || "";

        if (url) {
          link.href = url;
          link.hidden = false;
          link.target = "_blank";
          link.rel = "noopener noreferrer";
        } else {
          link.hidden = true;
        }
      });
    } catch (error) {
      console.error("MARKAZ social links could not be updated:", error);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", applySocialLinks);
  } else {
    applySocialLinks();
  }
})();
