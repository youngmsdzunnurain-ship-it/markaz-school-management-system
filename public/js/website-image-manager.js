(() => {
  "use strict";

  const endpoint = "api/settings/website-images.php";
  const page = document.querySelector(".settings-page");
  if (!page || document.getElementById("websiteImageManager")) return;

  const section = document.createElement("section");
  section.id = "websiteImageManager";
  section.className = "settings-card website-image-manager";
  section.innerHTML = `
    <div class="settings-card-heading">
      <div>
        <span class="settings-label">PUBLIC WEBSITE</span>
        <h2>Website Image Manager</h2>
        <p>Replace images used across the website, including logos, building photos, portraits and CSS backgrounds. JPG, PNG and WebP are supported, up to 8 MB per upload.</p>
      </div>
    </div>
    <label class="website-image-search-label" for="websiteImageSearch">Find an image</label>
    <input id="websiteImageSearch" type="search" placeholder="Search by image URL or page name">
    <p id="websiteImageStatus" role="status" aria-live="polite">Loading website images…</p>
    <div id="websiteImageList" class="website-image-list"></div>
  `;

  page.appendChild(section);

  const list = section.querySelector("#websiteImageList");
  const search = section.querySelector("#websiteImageSearch");
  const status = section.querySelector("#websiteImageStatus");
  let catalogue = [];

  function escapeHTML(value) {
    return String(value).replace(/[&<>"']/g, character => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;"
    })[character]);
  }

  function imagePath(value) {
    if (!value) return "";
    return value.startsWith("/") ? value : `/${value}`;
  }

  function render() {
    const term = search.value.trim().toLowerCase();
    const filtered = catalogue.filter(item =>
      `${item.url} ${item.label}`.toLowerCase().includes(term)
    );

    list.innerHTML = "";

    if (!filtered.length) {
      list.innerHTML = "<p>No matching images found.</p>";
      return;
    }

    for (const item of filtered) {
      const card = document.createElement("article");
      card.className = "website-image-item";

      const current = item.replacement || item.url;
      const previewUrl = /^https?:\/\//i.test(current)
        ? current
        : imagePath(current);

      card.innerHTML = `
        <div class="website-image-preview">
          <img src="${escapeHTML(previewUrl)}" alt="${escapeHTML(item.label)}" loading="lazy">
        </div>
        <div class="website-image-details">
          <h3>${escapeHTML(item.label || "Website image")}</h3>
          <p class="website-image-url">${escapeHTML(item.url)}</p>
          <p class="website-image-state">
            ${item.replacement
              ? "Custom replacement is active"
              : "Using original image"}
          </p>
          <form class="website-image-upload">
            <input type="hidden" name="source" value="${escapeHTML(item.url)}">
            <label>
              Choose replacement
              <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
            </label>
            <button type="submit">Upload replacement</button>
          </form>
          ${item.replacement
            ? '<button type="button" class="website-image-reset">Restore original</button>'
            : ""}
        </div>
      `;

      const image = card.querySelector("img");
      image.addEventListener("error", () => {
        image.alt = "Preview unavailable";
        image.style.display = "none";
      });

      card.querySelector(".website-image-upload").addEventListener("submit", async event => {
        event.preventDefault();
        const form = event.currentTarget;
        const file = form.querySelector('input[type="file"]').files[0];

        if (!file) {
          status.textContent = "Choose an image first.";
          return;
        }

        if (file.size > 8 * 1024 * 1024) {
          status.textContent = "The image must not exceed 8 MB.";
          return;
        }

        const data = new FormData(form);
        data.set("action", "replace");
        status.textContent = `Uploading replacement for ${item.url}…`;

        try {
          const response = await fetch(endpoint, {
            method: "POST",
            credentials: "same-origin",
            body: data
          });
          const result = await response.json();

          if (!response.ok || !result.success) {
            throw new Error(result.message || "Upload failed.");
          }

          status.textContent = result.message || "Replacement saved.";
          await loadCatalogue();
        } catch (error) {
          status.textContent = error.message || "Could not upload the image.";
        }
      });

      const resetButton = card.querySelector(".website-image-reset");
      if (resetButton) {
        resetButton.addEventListener("click", async () => {
          if (!confirm("Restore the original image on every page where it is used?")) {
            return;
          }

          const data = new FormData();
          data.set("action", "reset");
          data.set("source", item.url);
          status.textContent = "Restoring original image…";

          try {
            const response = await fetch(endpoint, {
              method: "POST",
              credentials: "same-origin",
              body: data
            });
            const result = await response.json();

            if (!response.ok || !result.success) {
              throw new Error(result.message || "Restore failed.");
            }

            status.textContent = result.message || "Original image restored.";
            await loadCatalogue();
          } catch (error) {
            status.textContent = error.message || "Could not restore the original.";
          }
        });
      }

      list.appendChild(card);
    }

    status.textContent =
      `${filtered.length} image reference${filtered.length === 1 ? "" : "s"} shown.`;
  }

  async function loadCatalogue() {
    status.textContent = "Loading website images…";
    try {
      const response = await fetch(endpoint, {
        credentials: "same-origin",
        cache: "no-store"
      });
      const result = await response.json();

      if (!response.ok || !result.success || !Array.isArray(result.images)) {
        throw new Error(result.message || "Could not load the image catalogue.");
      }

      catalogue = result.images;
      render();
    } catch (error) {
      status.textContent =
        `${error.message || "Could not load website images."} Check that you are logged in as an administrator and refresh the page.`;
    }
  }

  search.addEventListener("input", render);
  loadCatalogue();
})();
