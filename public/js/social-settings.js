(() => {
  "use strict";

  const page = document.querySelector(".settings-page");
  if (!page || document.getElementById("markazSocialSettings")) return;

  const section = document.createElement("section");
  section.id = "markazSocialSettings";
  section.className = "settings-card social-settings-card";
  section.innerHTML = `
    <h2>Website &amp; Social Links</h2>
    <p>Manage the official MARKAZ social-media profile URLs shown on the public website. Leave a field blank if the school does not use that platform.</p>
    <form id="markazSocialForm">
      <div class="social-settings-fields">
        <label>Facebook
          <input type="url" name="facebook" placeholder="https://www.facebook.com/your-page">
        </label>
        <label>Instagram
          <input type="url" name="instagram" placeholder="https://www.instagram.com/your-profile">
        </label>
        <label>X
          <input type="url" name="x" placeholder="https://x.com/your-profile">
        </label>
        <label>YouTube
          <input type="url" name="youtube" placeholder="https://www.youtube.com/@your-channel">
        </label>
        <label>TikTok
          <input type="url" name="tiktok" placeholder="https://www.tiktok.com/@your-profile">
        </label>
      </div>
      <button type="submit" id="markazSocialSave">Save Social Links</button>
      <p id="markazSocialStatus" role="status" aria-live="polite"></p>
    </form>`;

  page.appendChild(section);

  const form = section.querySelector("#markazSocialForm");
  const status = section.querySelector("#markazSocialStatus");
  const saveButton = section.querySelector("#markazSocialSave");

  fetch("api/settings/social-links.php", {
    credentials: "same-origin",
    cache: "no-store"
  })
    .then(response => response.json())
    .then(data => {
      if (!data.success || !data.links) {
        throw new Error(data.message || "Could not load social links.");
      }

      Object.entries(data.links).forEach(([platform, url]) => {
        const input = form.elements.namedItem(platform);
        if (input) input.value = url || "";
      });
      status.textContent = "Saved social links loaded.";
    })
    .catch(error => {
      status.textContent = error.message || "Could not load social links.";
    });

  form.addEventListener("submit", async event => {
    event.preventDefault();
    saveButton.disabled = true;
    status.textContent = "Saving social links…";

    try {
      const response = await fetch("api/settings/social-links.php", {
        method: "POST",
        credentials: "same-origin",
        body: new FormData(form)
      });
      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.message || "Could not save social links.");
      }

      status.textContent = data.message || "Social links saved.";
      document.dispatchEvent(new Event("markaz:social-links-updated"));
    } catch (error) {
      status.textContent = error.message || "Could not save social links.";
    } finally {
      saveButton.disabled = false;
    }
  });
})();
