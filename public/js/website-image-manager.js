(() => {
  "use strict";

  const endpoint = "api/settings/website-images.php";
  const page = document.querySelector(".settings-page");
  if (!page || document.getElementById("websiteImageManager")) return;

  const section = document.createElement("section");
  section.id = "websiteImageManager";
  section.className = "settings-card website-image-manager";
  section.innerHTML = `
    <div class="settings-card-heading"><div>
      <span class="settings-label">PUBLIC WEBSITE</span>
      <h2>Website Image Manager</h2>
      <p>Choose replacements for your website photographs, logos and backgrounds. Preview your selections, then save them together. JPG, PNG and WebP are supported, up to 8 MB each.</p>
    </div></div>
    <div id="websiteImageSaveForm">
      <label class="website-image-search-label" for="websiteImageSearch">Find an image</label>
      <input id="websiteImageSearch" type="search" placeholder="Search by image URL or page name">
      <p id="websiteImageStatus" role="status" aria-live="polite">Loading website images…</p>
      <div id="websiteImageList" class="website-image-list"></div>
      <div class="website-image-savebar">
        <span id="websiteImagePendingCount">No unsaved changes</span>
        <button type="button" id="saveWebsiteImagesButton" disabled>Save Changes</button>
        <button type="button" id="discardWebsiteImagesButton" disabled>Discard Changes</button>
      </div>
    </div>
  `;
  page.appendChild(section);

  const list = section.querySelector("#websiteImageList");
  const search = section.querySelector("#websiteImageSearch");
  const status = section.querySelector("#websiteImageStatus");
  const saveButton = section.querySelector("#saveWebsiteImagesButton");
  const discardButton = section.querySelector("#discardWebsiteImagesButton");
  const pendingCount = section.querySelector("#websiteImagePendingCount");
  let catalogue = [];
  const pendingUploads = new Map();
  const previewUrls = new Map();
  let saving = false;

  function escapeHTML(value) {
    return String(value).replace(/[&<>"']/g, character => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    })[character]);
  }

  async function readApiResponse(response, operation) {
    const raw = await response.text();
    const contentType = (response.headers.get("content-type") || "").toLowerCase();
    let result;
    try {
      result = JSON.parse(raw);
    } catch (_) {
      const type = contentType || "missing content type";
      throw new Error(
        `${operation}: server returned a non-JSON response (HTTP ${response.status}; ${type}). Check the PHP error log and administrator session.`
      );
    }
    if (!result || typeof result !== "object") {
      throw new Error(`${operation}: server returned an invalid JSON response (HTTP ${response.status}).`);
    }
    return result;
  }

  function imagePath(value) {
    if (!value) return "";
    return value.startsWith("/") ? value : `/${value}`;
  }

  function updateSaveBar() {
    const count = pendingUploads.size;
    pendingCount.textContent = count
      ? `${count} selected image change${count === 1 ? "" : "s"}`
      : "No unsaved changes";
    saveButton.disabled = saving || count === 0;
    discardButton.disabled = saving || count === 0;
  }

  function previewFor(item) {
    if (pendingUploads.has(item.url)) return previewUrls.get(item.url) || "";
    const current = item.replacement || item.url;
    return /^https?:\/\//i.test(current) ? current : imagePath(current);
  }

  function render() {
    const term = search.value.trim().toLowerCase();
    const filtered = catalogue.filter(item => `${item.url} ${item.label}`.toLowerCase().includes(term));
    list.innerHTML = "";
    if (!filtered.length) {
      list.innerHTML = "<p>No matching images found.</p>";
      updateSaveBar();
      return;
    }

    for (const item of filtered) {
      const pending = pendingUploads.has(item.url);
      const card = document.createElement("article");
      card.className = "website-image-item";
      card.innerHTML = `
        <div class="website-image-preview"><img src="${escapeHTML(previewFor(item))}" alt="${escapeHTML(item.label)}" loading="lazy"></div>
        <div class="website-image-details">
          <h3>${escapeHTML(item.label || "Website image")}</h3>
          <p class="website-image-url">${escapeHTML(item.url)}</p>
          <p class="website-image-state">${pending ? "New image selected — not saved yet" : item.replacement ? "Custom replacement is active" : "Using original image"}</p>
          <label class="website-image-file-label">Choose replacement
            <input type="file" accept="image/jpeg,image/png,image/webp">
          </label>
        </div>`;
      const image = card.querySelector("img");
      image.addEventListener("error", () => {
        image.alt = "Preview unavailable";
        image.style.display = "none";
      });
      const fileInput = card.querySelector('input[type="file"]');
      fileInput.addEventListener("change", () => {
        const file = fileInput.files && fileInput.files[0];
        if (!file) return;
        if (file.size <= 0 || file.size > 8 * 1024 * 1024) {
          status.textContent = "Each image must be greater than 0 bytes and no larger than 8 MB.";
          fileInput.value = "";
          return;
        }
        if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
          status.textContent = "Choose a JPG, PNG, or WebP image.";
          fileInput.value = "";
          return;
        }
        pendingUploads.set(item.url, file);
        if (previewUrls.has(item.url)) URL.revokeObjectURL(previewUrls.get(item.url));
        previewUrls.set(item.url, URL.createObjectURL(file));
        status.textContent = "Image selected. Review the preview, then select Save Changes.";
        render();
      });
      list.appendChild(card);
    }
    status.textContent = `${filtered.length} image reference${filtered.length === 1 ? "" : "s"} shown.`;
    updateSaveBar();
  }

  function discardPending() {
    for (const url of previewUrls.values()) URL.revokeObjectURL(url);
    previewUrls.clear();
    pendingUploads.clear();
    status.textContent = "Unsaved selections discarded.";
    render();
  }

  async function loadCatalogue() {
    const response = await fetch(endpoint, {
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json" }
    });
    const result = await readApiResponse(response, "Loading website images");
    if (!response.ok || !result.success || !Array.isArray(result.images)) {
      throw new Error(result.message || `Could not load website images (HTTP ${response.status}).`);
    }
    catalogue = result.images;
    render();
    return catalogue;
  }

  async function saveChanges() {
    if (saving || pendingUploads.size === 0) return;
    const expected = new Map(pendingUploads);
    const data = new FormData();
    data.set("action", "batch-replace");
    for (const [source, file] of expected) {
      data.append("sources[]", source);
      data.append("images[]", file, file.name);
    }

    saving = true;
    updateSaveBar();
    status.textContent = "Uploading images and verifying the saved records…";
    try {
      const response = await fetch(endpoint, {
        method: "POST",
        credentials: "same-origin",
        headers: { Accept: "application/json" },
        body: data
      });
      const result = await readApiResponse(response, "Saving website images");
      if (!response.ok || !result.success) {
        throw new Error(result.message || `Image save failed (HTTP ${response.status}).`);
      }
      if (!Array.isArray(result.saved_images) || result.saved_images.length !== expected.size) {
        throw new Error("The server did not confirm every saved image. Your selections have been kept.");
      }
      for (const source of expected.keys()) {
        if (!result.saved_images.some(item => item && item.source === source && typeof item.replacement === "string" && item.replacement)) {
          throw new Error("The server did not confirm every saved image. Your selections have been kept.");
        }
      }

      await loadCatalogue();
      for (const saved of result.saved_images) {
        const persisted = catalogue.find(item => item.url === saved.source);
        if (!persisted || persisted.replacement !== saved.replacement) {
          throw new Error("The uploaded files were received, but the saved image settings could not be verified after reload. Your selections have been kept.");
        }
      }

      for (const url of previewUrls.values()) URL.revokeObjectURL(url);
      previewUrls.clear();
      pendingUploads.clear();
      status.textContent = `${result.saved_images.length} image replacement${result.saved_images.length === 1 ? "" : "s"} saved and verified. Refresh the public page to view them.`;
      render();
    } catch (error) {
      status.textContent = error.message || "Could not save website images. Your selected files are still available; please try again.";
    } finally {
      saving = false;
      updateSaveBar();
    }
  }

  // Bind directly to the visible button; do not rely on implicit form submission.
  saveButton.addEventListener("click", event => {
    event.preventDefault();
    void saveChanges();
  });
  discardButton.addEventListener("click", discardPending);
  search.addEventListener("input", render);
  loadCatalogue().catch(error => {
    status.textContent = `${error.message || "Could not load website images."} Check that you are logged in as an administrator and refresh the page.`;
  });
})();
