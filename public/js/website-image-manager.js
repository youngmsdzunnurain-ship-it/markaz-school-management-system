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
      <p>Choose replacements for your website photographs, logos and backgrounds. Review all selections, then save them together. JPG, PNG and WebP are supported, up to 8 MB each.</p>
    </div></div>
    <label class="website-image-search-label" for="websiteImageSearch">Find an image</label>
    <input id="websiteImageSearch" type="search" placeholder="Search by image URL or page name">
    <p id="websiteImageStatus" role="status" aria-live="polite">Loading website images…</p>
    <div id="websiteImageList" class="website-image-list"></div>
    <div class="website-image-savebar">
      <span id="websiteImagePendingCount">No unsaved changes</span>
      <button type="button" id="saveWebsiteImagesButton" disabled>Save Changes</button>
      <button type="button" id="discardWebsiteImagesButton" disabled>Discard Changes</button>
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
  const pendingResets = new Set();
  let saving = false;

  function escapeHTML(value) {
    return String(value).replace(/[&<>"']/g, character => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    })[character]);
  }

  function imagePath(value) {
    if (!value) return "";
    return value.startsWith("/") ? value : `/${value}`;
  }

  function updateSaveBar() {
    const count = pendingUploads.size + pendingResets.size;
    pendingCount.textContent = count
      ? `${count} unsaved image change${count === 1 ? "" : "s"}`
      : "No unsaved changes";
    saveButton.disabled = saving || count === 0;
    discardButton.disabled = saving || count === 0;
  }

  function previewFor(item) {
    const pending = pendingUploads.get(item.url);
    if (pending) return URL.createObjectURL(pending);
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
      const reset = pendingResets.has(item.url);
      const card = document.createElement("article");
      card.className = "website-image-item";
      const current = pending ? previewFor(item) : reset ? item.url : previewFor(item);
      card.innerHTML = `
        <div class="website-image-preview"><img src="${escapeHTML(current)}" alt="${escapeHTML(item.label)}" loading="lazy"></div>
        <div class="website-image-details">
          <h3>${escapeHTML(item.label || "Website image")}</h3>
          <p class="website-image-url">${escapeHTML(item.url)}</p>
          <p class="website-image-state">${pending ? "New image selected — not saved yet" : reset ? "Original will be restored when saved" : item.replacement ? "Custom replacement is active" : "Using original image"}</p>
          <label class="website-image-file-label">Choose replacement
            <input type="file" accept="image/jpeg,image/png,image/webp">
          </label>
          <button type="button" class="website-image-reset" ${(!item.replacement && !pending) || reset ? "disabled" : ""}>${reset ? "Restore cancelled" : "Restore original"}</button>
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
        if (file.size > 8 * 1024 * 1024) {
          status.textContent = "The image must not exceed 8 MB.";
          fileInput.value = "";
          return;
        }
        if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
          status.textContent = "Choose a JPG, PNG, or WebP image.";
          fileInput.value = "";
          return;
        }
        pendingUploads.set(item.url, file);
        pendingResets.delete(item.url);
        status.textContent = "Image selected. Review your changes, then select Save Changes.";
        render();
      });
      card.querySelector(".website-image-reset").addEventListener("click", () => {
        if (pending) pendingUploads.delete(item.url);
        pendingResets.add(item.url);
        status.textContent = "Original image marked for restoration. Select Save Changes to apply it.";
        render();
      });
      list.appendChild(card);
    }
    status.textContent = `${filtered.length} image reference${filtered.length === 1 ? "" : "s"} shown.`;
    updateSaveBar();
  }

  function discardPending() {
    pendingUploads.clear();
    pendingResets.clear();
    status.textContent = "Unsaved image changes discarded.";
    render();
  }

  async function saveChanges() {
    if (saving || (!pendingUploads.size && !pendingResets.size)) return;
    const data = new FormData();
    data.set("action", "batch-replace");
    for (const [source, file] of pendingUploads) {
      data.append("sources[]", source);
      data.append("images[]", file, file.name);
    }
    for (const source of pendingResets) data.append("resets[]", source);

    saving = true;
    updateSaveBar();
    status.textContent = "Saving your website image changes…";
    try {
      const response = await fetch(endpoint, {
        method: "POST", credentials: "same-origin", body: data
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || "Could not save image changes.");
      pendingUploads.clear();
      pendingResets.clear();
      status.textContent = result.message || "All website image changes saved.";
      await loadCatalogue();
    } catch (error) {
      status.textContent = error.message || "Could not save website images. Your selected files are still available; please try again.";
    } finally {
      saving = false;
      updateSaveBar();
    }
  }

  async function loadCatalogue() {
    status.textContent = "Loading website images…";
    try {
      const response = await fetch(endpoint, {credentials:"same-origin", cache:"no-store"});
      const result = await response.json();
      if (!response.ok || !result.success || !Array.isArray(result.images)) {
        throw new Error(result.message || "Could not load the image catalogue.");
      }
      catalogue = result.images;
      render();
    } catch (error) {
      status.textContent = `${error.message || "Could not load website images."} Check that you are logged in as an administrator and refresh the page.`;
    }
  }

  saveButton.addEventListener("click", saveChanges);
  discardButton.addEventListener("click", discardPending);
  search.addEventListener("input", render);
  loadCatalogue();
})();
