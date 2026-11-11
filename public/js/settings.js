const accountRequest = fetch("./api/auth/me.php", {
    credentials: "same-origin",
    cache: "no-store",
    headers: { Accept: "application/json" }
}).then(async response => ({
    ok: response.ok,
    status: response.status,
    data: await response.json()
}));
window.markazSettingsAccountPromise = accountRequest;
const form = document.getElementById("profileForm");
const nameInput = document.getElementById("name");
const emailInput = document.getElementById("email");
const passwordInput = document.getElementById("currentPassword");
const imageInput = document.getElementById("profileImage");
const message = document.getElementById("formMessage");
const saveButton = document.getElementById("saveProfileButton");
let originalProfileName = "";
let originalProfileEmail = "";
let currentProfileRole = "";

const previewName = document.getElementById("profilePreviewName");
const previewEmail = document.getElementById("profilePreviewEmail");
const profilePhoto = document.getElementById("profilePhoto");
const profileInitials = document.getElementById("profileInitials");

function initials(name) {
    const parts = String(name || "Administrator")
        .trim()
        .split(/\s+/);

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return (
        parts[0][0] +
        parts[parts.length - 1][0]
    ).toUpperCase();
}

function showMessage(text, type) {
    message.textContent = text;
    message.className = "form-message " + type;
}

function updateProfilePasswordRequirement() {
    if (!passwordInput) return;
    passwordInput.required = true;
    passwordInput.disabled = false;
    const securitySection = document.querySelector(".password-section");
    if (securitySection) securitySection.hidden = false;
}

nameInput.addEventListener("input", updateProfilePasswordRequirement);
emailInput.addEventListener("input", updateProfilePasswordRequirement);

async function loadProfile() {
    try {
        const accountResult = await window.markazSettingsAccountPromise;
        const response = { ok: accountResult.ok, status: accountResult.status };
        const data = accountResult.data;

        if (!response.ok || !data.success || !data.admin) {
            window.location.href = "login.html";
            return;
        }

        if (!["admin", "exam_officer", "management", "teacher"].includes(data.admin.role)) {
            window.location.replace("dashboard.html");
            return;
        }

        window.markazCurrentRole = data.admin.role;
        currentProfileRole = data.admin.role;
        originalProfileName = data.admin.name || "";
        originalProfileEmail = data.admin.email || "";
        const isExamOfficer = data.admin.role === "exam_officer";
        const isManagement = data.admin.role === "management";
        const isTeacher = data.admin.role === "teacher";
        const roleLabel = document.getElementById("profileRoleLabel");
        const pageDescription = document.getElementById("settingsPageDescription");
        if (roleLabel) roleLabel.textContent = isManagement ? "MANAGEMENT" : (isExamOfficer ? "EXAM OFFICER" : (isTeacher ? "TEACHER" : "ADMINISTRATOR"));
        if (pageDescription && isExamOfficer) {
            pageDescription.textContent = "Manage your profile and shared academic settings.";
        } else if (pageDescription && isManagement) {
            pageDescription.textContent = "View and update your management account profile.";
        } else if (pageDescription && isTeacher) {
            pageDescription.textContent = "View and update your teacher account profile.";
        }

        nameInput.value = data.admin.name || "";
        emailInput.value = data.admin.email || "";
        updateProfilePasswordRequirement();

        previewName.textContent =
            data.admin.name || "Administrator";

        previewEmail.textContent =
            data.admin.email || "";

        profilePhoto.innerHTML = "";

        if (data.admin.profile_image) {
            const image = document.createElement("img");
            image.src = "/" + data.admin.profile_image.replace(/^\/+/, "");
            image.alt = data.admin.name || "Administrator";
            image.loading = "eager";
            image.decoding = "async";
            profilePhoto.appendChild(image);
        } else {
            profileInitials.textContent =
                initials(data.admin.name);
            profilePhoto.appendChild(profileInitials);
        }

    } catch (error) {
        showMessage(
            "Unable to load account profile.",
            "error"
        );
    }
}

async function compressProfileImage(file) {
    const objectUrl = URL.createObjectURL(file);
    try {
        const image = new Image();
        await new Promise((resolve, reject) => {
            image.onload = resolve;
            image.onerror = () => reject(new Error("The selected image format could not be opened."));
            image.src = objectUrl;
        });

        const maxBytes = 1.5 * 1024 * 1024;
        const maxDimension = 1200;
        let scale = Math.min(1, maxDimension / Math.max(image.naturalWidth, image.naturalHeight));
        let quality = 0.84;
        let blob = null;
        const canvas = document.createElement("canvas");

        for (let attempt = 0; attempt < 10; attempt += 1) {
            const width = Math.max(1, Math.round(image.naturalWidth * scale));
            const height = Math.max(1, Math.round(image.naturalHeight * scale));
            canvas.width = width;
            canvas.height = height;
            const context = canvas.getContext("2d");
            if (!context) throw new Error("Image processing is unavailable.");
            context.clearRect(0, 0, width, height);
            context.drawImage(image, 0, 0, width, height);
            blob = await new Promise(resolve => canvas.toBlob(resolve, "image/jpeg", quality));
            if (!blob) throw new Error("Image compression failed.");
            if (blob.size <= maxBytes) break;

            if (quality > 0.60) {
                quality = Math.max(0.60, quality - 0.08);
            } else {
                scale *= 0.82;
                quality = 0.76;
            }
        }

        if (!blob || blob.size > maxBytes) {
            throw new Error("The image could not be reduced below the 1.5MB upload limit.");
        }

        const safeName = (file.name || "profile-photo").replace(/\.[^.]+$/, "");
        return new File([blob], safeName + ".jpg", {
            type: "image/jpeg",
            lastModified: Date.now()
        });
    } finally {
        URL.revokeObjectURL(objectUrl);
    }
}

const removeProfileButton = document.getElementById("removeProfileButton");

removeProfileButton?.addEventListener("click", () => {
    imageInput.value = "";
    profilePhoto.innerHTML = "";

    const initialsSpan = document.createElement("span");
    initialsSpan.id = "profileInitials";
    initialsSpan.textContent = initials(nameInput.value);
    profilePhoto.appendChild(initialsSpan);

    removeProfileButton.dataset.remove = "1";
    showMessage("Photo removal selected. Save Changes to apply it.", "success");
});

/* Local square cropper shared by Admin, Exam Officer, Management and Teacher. */
(() => {
    const modal = document.getElementById("staffPhotoCropModal");
    const canvas = document.getElementById("staffPhotoCropCanvas");
    const zoomInput = document.getElementById("staffPhotoCropZoom");
    const zoomLabel = document.getElementById("staffPhotoCropZoomValue");
    const confirmButton = document.getElementById("confirmStaffPhotoCrop");
    const closeButton = document.getElementById("cancelStaffPhotoCrop");
    const cancelButton = document.getElementById("cancelStaffPhotoCropBottom");
    const errorNode = document.getElementById("staffPhotoCropError");
    const ctx = canvas?.getContext("2d");
    if (!modal || !canvas || !ctx || !zoomInput || !confirmButton) return;

    const size = canvas.width;
    const confirmedFiles = new WeakMap();
    let sourceImage = null;
    let sourceUrl = null;
    let activePointer = null;
    let drag = null;
    let activeFile = null;
    let offsetX = 0;
    let offsetY = 0;
    let baseScale = 1;
    let zoom = 1;
    let preparing = false;

    function setError(message = "") {
        errorNode.textContent = message;
        errorNode.hidden = !message;
    }

    function releaseSource() {
        if (sourceImage && typeof sourceImage.close === "function") sourceImage.close();
        sourceImage = null;
        if (sourceUrl) URL.revokeObjectURL(sourceUrl);
        sourceUrl = null;
    }

    function setFile(input, file) {
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
    }

    function restoreFile() {
        const previous = confirmedFiles.get(imageInput);
        if (previous) {
            try { setFile(imageInput, previous); }
            catch (_) { imageInput.value = ""; }
        } else {
            imageInput.value = "";
        }
    }

    function clamp() {
        if (!sourceImage) return;
        const scale = baseScale * zoom;
        const maxX = Math.max(0, (sourceImage.width * scale - size) / 2);
        const maxY = Math.max(0, (sourceImage.height * scale - size) / 2);
        offsetX = Math.max(-maxX, Math.min(maxX, offsetX));
        offsetY = Math.max(-maxY, Math.min(maxY, offsetY));
    }

    function draw() {
        if (!sourceImage) return;
        clamp();
        const scale = baseScale * zoom;
        const width = sourceImage.width * scale;
        const height = sourceImage.height * scale;
        ctx.clearRect(0, 0, size, size);
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, size, size);
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = "high";
        ctx.drawImage(sourceImage, (size - width) / 2 + offsetX, (size - height) / 2 + offsetY, width, height);
    }

    function closeCropper(cancelled = false) {
        if (cancelled) restoreFile();
        modal.hidden = true;
        activeFile = null;
        activePointer = null;
        drag = null;
        preparing = false;
        confirmButton.disabled = false;
        confirmButton.textContent = "Use This Photo";
        setError();
        releaseSource();
        ctx.clearRect(0, 0, size, size);
    }

    async function loadSource(file) {
        if ("createImageBitmap" in window) {
            try { return await createImageBitmap(file, { imageOrientation: "from-image" }); }
            catch (_) { return await createImageBitmap(file); }
        }
        sourceUrl = URL.createObjectURL(file);
        return await new Promise((resolve, reject) => {
            const image = new Image();
            image.onload = () => resolve(image);
            image.onerror = () => reject(new Error("This photo could not be opened."));
            image.src = sourceUrl;
        });
    }

    async function openCropper(file) {
        if (!file) return;
        if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) {
            imageInput.value = "";
            showMessage("Please choose a JPG, PNG or WEBP image.", "error");
            return;
        }
        if (file.size > 3 * 1024 * 1024) {
            imageInput.value = "";
            showMessage("Profile image must not exceed 3MB.", "error");
            return;
        }

        preparing = true;
        activeFile = file;
        modal.hidden = false;
        confirmButton.disabled = true;
        confirmButton.textContent = "Preparing...";
        setError();
        try {
            releaseSource();
            sourceImage = await loadSource(file);
            baseScale = Math.max(size / sourceImage.width, size / sourceImage.height);
            zoom = 1;
            offsetX = 0;
            offsetY = 0;
            zoomInput.value = "1";
            zoomLabel.textContent = "100%";
            draw();
            preparing = false;
            confirmButton.disabled = false;
            confirmButton.textContent = "Use This Photo";
        } catch (error) {
            console.error("Staff profile crop preparation failed:", error);
            setError("Unable to open this image. Please choose another photo.");
            confirmButton.textContent = "Photo unavailable";
        }
    }

    function previewCroppedFile(file) {
        const url = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            profilePhoto.replaceChildren(image);
            image.alt = "Cropped profile photo";
            image.style.width = "100%";
            image.style.height = "100%";
            image.style.objectFit = "cover";
            image.style.objectPosition = "center";
            URL.revokeObjectURL(url);
        };
        image.onerror = () => URL.revokeObjectURL(url);
        image.src = url;
    }

    imageInput.addEventListener("change", () => {
        const file = imageInput.files?.[0];
        if (file) openCropper(file);
    });

    zoomInput.addEventListener("input", () => {
        zoom = Number(zoomInput.value) || 1;
        zoomLabel.textContent = Math.round(zoom * 100) + "%";
        draw();
    });

    canvas.addEventListener("pointerdown", event => {
        if (!sourceImage || preparing) return;
        activePointer = event.pointerId;
        canvas.setPointerCapture(event.pointerId);
        drag = { x:event.clientX, y:event.clientY, offsetX, offsetY };
    });
    canvas.addEventListener("pointermove", event => {
        if (!drag || event.pointerId !== activePointer) return;
        const rect = canvas.getBoundingClientRect();
        const factor = size / rect.width;
        offsetX = drag.offsetX + (event.clientX - drag.x) * factor;
        offsetY = drag.offsetY + (event.clientY - drag.y) * factor;
        draw();
    });
    const endDrag = event => {
        if (event.pointerId !== activePointer) return;
        activePointer = null;
        drag = null;
    };
    canvas.addEventListener("pointerup", endDrag);
    canvas.addEventListener("pointercancel", endDrag);

    confirmButton.addEventListener("click", async () => {
        if (!sourceImage || preparing || !activeFile) return;
        confirmButton.disabled = true;
        confirmButton.textContent = "Preparing photo...";
        try {
            const output = document.createElement("canvas");
            output.width = 600;
            output.height = 600;
            const outputContext = output.getContext("2d");
            if (!outputContext) throw new Error("Photo editing is unavailable.");
            const scale = baseScale * zoom;
            const width = sourceImage.width * scale;
            const height = sourceImage.height * scale;
            outputContext.fillStyle = "#ffffff";
            outputContext.fillRect(0, 0, 600, 600);
            outputContext.imageSmoothingEnabled = true;
            outputContext.imageSmoothingQuality = "high";
            outputContext.drawImage(sourceImage, (600 - width) / 2 + offsetX, (600 - height) / 2 + offsetY, width, height);
            const blob = await new Promise((resolve, reject) => output.toBlob(result => result ? resolve(result) : reject(new Error("Unable to create the cropped photo.")), "image/jpeg", .9));
            const safeName = (activeFile.name || "profile-photo").replace(/\.[^.]+$/, "").replace(/[^a-z0-9_-]/gi, "-").slice(0, 50) || "profile-photo";
            const cropped = new File([blob], safeName + "-cropped.jpg", {type:"image/jpeg", lastModified:Date.now()});
            if (cropped.size > 3 * 1024 * 1024) throw new Error("The cropped photo exceeds the 3MB upload limit.");
            setFile(imageInput, cropped);
            confirmedFiles.set(imageInput, cropped);
            previewCroppedFile(cropped);
            if (removeProfileButton) removeProfileButton.dataset.remove = "0";
            showMessage("Cropped photo ready. Confirm your password and save changes.", "success");
            closeCropper(false);
        } catch (error) {
            setError(error.message || "Unable to crop this photo.");
            confirmButton.disabled = false;
            confirmButton.textContent = "Try Again";
        }
    });

    [closeButton, cancelButton].forEach(button => button?.addEventListener("click", () => closeCropper(true)));
    modal.addEventListener("click", event => { if (event.target === modal) closeCropper(true); });
    document.addEventListener("keydown", event => {
        if (event.key === "Escape" && !modal.hidden) closeCropper(true);
    });
})();


form.addEventListener("submit", async event => {

    event.preventDefault();
    updateProfilePasswordRequirement();

    saveButton.disabled = true;
    saveButton.textContent = "Saving...";

    showMessage("", "");

    const formData = new FormData(form);

    if (imageInput.files[0]) {
        try {
            const selectedImage = imageInput.files[0];
            const compressedImage = await compressProfileImage(selectedImage);
            formData.set("profile_image", compressedImage, compressedImage.name);
        } catch (imageError) {
            showMessage(imageError.message || "The selected image could not be prepared. Please choose another image.", "error");
            saveButton.disabled = false;
            saveButton.textContent = "Save Changes";
            return;
        }
    }

    if (removeProfileButton?.dataset.remove === "1") {
        formData.set("remove_profile_image", "1");
    }

    try {

        const response = await fetch(
            "./api/settings/admin-profile.php",
            {
                method: "POST",
                credentials: "same-origin",
                body: formData
            }
        );

        if (response.redirected) {
            showMessage("The profile request was redirected to sign-in. Please sign in again; the photo was not saved.", "error");
            return;
        }
        const data = await response.json().catch(() => null);

        if (!response.ok || !data || !data.success) {
            showMessage(
                data?.message || (response.status === 401 ? "Your session has expired. Sign in again before saving." : "Unable to save changes."),
                "error"
            );
            return;
        }

        previewName.textContent = data.admin.name;
        previewEmail.textContent = data.admin.email;
        originalProfileName = data.admin.name || "";
        originalProfileEmail = data.admin.email || "";

        profilePhoto.innerHTML = "";

        if (data.admin.profile_image) {
            const image = document.createElement("img");
            image.src = "/" + data.admin.profile_image.replace(/^\/+/, "");
            image.alt = data.admin.name || "Administrator";
            image.loading = "eager";
            image.decoding = "async";
            profilePhoto.appendChild(image);
        } else {
            const initialsSpan = document.createElement("span");
            initialsSpan.id = "profileInitials";
            initialsSpan.textContent = initials(data.admin.name);
            profilePhoto.appendChild(initialsSpan);
        }

        if (removeProfileButton) {
            removeProfileButton.dataset.remove = "0";
        }

        passwordInput.value = "";
        updateProfilePasswordRequirement();

        showMessage(
            "Profile updated successfully.",
            "success"
        );

    } catch (error) {

        showMessage(
            "Unable to connect to the server.",
            "error"
        );

    } finally {

        saveButton.disabled = false;
        saveButton.textContent = "Save Changes";
    }
});

loadProfile();

const memorizationList = document.getElementById("memorizationSettingsList");
const memorizationMessage = document.getElementById("memorizationSettingsMessage");

function showMemorizationMessage(text, type = "") {
    memorizationMessage.textContent = text;
    memorizationMessage.className = "form-message " + type;
}

async function loadMemorizationSettings() {
    try {
        const response = await fetch("./api/settings/memorization.php", {
            credentials: "same-origin",
            cache: "no-store"
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to load memorization settings.");
        }

        memorizationList.innerHTML = "";

        if (!data.arabic_classes.length) {
            memorizationList.innerHTML =
                '<div class="settings-loading">No Arabic classes found.</div>';
            return;
        }

        data.arabic_classes.forEach((arabicClass) => {
            const row = document.createElement("div");
            row.className = "memorization-setting-row";

            row.innerHTML = `
                <div class="memorization-class-info">
                    <strong>${escapeHtml(arabicClass.name)}</strong>
                    <span>Arabic Class</span>
                </div>

                <div class="memorization-setting-field">
                    <label>
                        <span>Portion of Memorization</span>
                        <input
                            type="text"
                            value="${escapeHtml(arabicClass.memorization_portion || "")}"
                            data-arabic-class-id="${arabicClass.id}"
                            placeholder="e.g. Juz 1–5"
                        >
                    </label>

                    <button
                        type="button"
                        class="save-memorization-button"
                        data-arabic-class-id="${arabicClass.id}"
                    >
                        Save
                    </button>
                </div>
            `;

            memorizationList.appendChild(row);
        });
    } catch (error) {
        memorizationList.innerHTML = "";
        showMemorizationMessage(error.message, "error");
    }
}

function escapeHtml(value) {
    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

document.addEventListener("click", async (event) => {
    const button = event.target.closest(".save-memorization-button");

    if (!button) return;

    const classId = button.dataset.arabicClassId;
    const input = document.querySelector(
        `input[data-arabic-class-id="${classId}"]`
    );

    if (!input) return;

    const portion = input.value.trim();

    button.disabled = true;
    button.textContent = "Saving...";

    const formData = new FormData();
    formData.append("arabic_class_id", classId);
    formData.append("memorization_portion", portion);

    try {
        const response = await fetch("./api/settings/memorization.php", {
            method: "POST",
            credentials: "same-origin",
            body: formData
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to save setting.");
        }

        showMemorizationMessage(
            "Memorization portion saved successfully.",
            "success"
        );

        button.textContent = "Saved ✓";

        setTimeout(() => {
            button.disabled = false;
            button.textContent = "Save";
        }, 1800);

    } catch (error) {
        showMemorizationMessage(error.message, "error");
        button.disabled = false;
        button.textContent = "Save";
    }
});

if (["admin", "exam_officer"].includes(currentProfileRole)) loadMemorizationSettings();

/* Keep Qur'an memorization settings permanently expanded. */
const memorizationToggle = document.getElementById("memorizationSettingsToggle");
const memorizationBody = document.getElementById("memorizationSettingsBody");

if (memorizationToggle && memorizationBody) {
    memorizationToggle.setAttribute("aria-expanded", "true");
    memorizationBody.hidden = false;
    memorizationToggle.classList.add("is-open");
    memorizationToggle.setAttribute("type", "button");
}

/* ACADEMIC PERIOD SETTINGS */
const academicPeriodForm = document.getElementById("academicPeriodForm");
const academicSessionSelect = document.getElementById("academicSessionSelect");
const academicTermSelect = document.getElementById("academicTermSelect");
const resumptionDateInput = document.getElementById("resumptionDate");
const academicPeriodMessage = document.getElementById("academicPeriodMessage");
const saveAcademicPeriodButton = document.getElementById("saveAcademicPeriodButton");

function showAcademicPeriodMessage(text, type = "") {
    if (!academicPeriodMessage) return;

    academicPeriodMessage.textContent = text;
    academicPeriodMessage.className = "form-message " + type;
}

async function loadAcademicPeriodSettings() {
    try {
        const response = await fetch("./api/settings/academic-period.php", {
            credentials: "same-origin",
            cache: "no-store"
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "Unable to load academic period settings."
            );
        }

        const period = data.academic_period || {};

        if (academicSessionSelect) {
            academicSessionSelect.innerHTML = "";

            (data.sessions || []).forEach(session => {
                const option = document.createElement("option");
                option.value = String(session.id);
                option.textContent = session.name;
                academicSessionSelect.appendChild(option);
            });

            if (period.session_name) {
                const currentSession = (data.sessions || []).find(
                    session => session.name === period.session_name
                );

                if (currentSession) {
                    academicSessionSelect.value = String(currentSession.id);
                }
            }
        }

        if (academicTermSelect) {
            academicTermSelect.value = String(period.term_id || "");
        }

        if (resumptionDateInput) {
            resumptionDateInput.value = period.resumption_date || "";
        }
    } catch (error) {
        showAcademicPeriodMessage(error.message, "error");
    }
}

academicPeriodForm?.addEventListener("submit", async event => {
    event.preventDefault();

    if (!resumptionDateInput?.value) {
        showAcademicPeriodMessage(
            "Please select the Next Term Begins date.",
            "error"
        );
        return;
    }

    saveAcademicPeriodButton.disabled = true;
    saveAcademicPeriodButton.textContent = "Saving...";
    showAcademicPeriodMessage("", "");

    const formData = new FormData(academicPeriodForm);

    if (academicSessionSelect?.value) {
        formData.set("academic_session_id", academicSessionSelect.value);
    }

    if (academicTermSelect?.value) {
        formData.set("term_id", academicTermSelect.value);
    }

    try {
        const response = await fetch(
            "./api/settings/academic-period.php",
            {
                method: "POST",
                credentials: "same-origin",
                body: formData
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "Unable to save academic calendar."
            );
        }

        showAcademicPeriodMessage(
            "Academic calendar saved successfully.",
            "success"
        );
        await loadAcademicArchive();

        setTimeout(() => {
            showAcademicPeriodMessage("", "");
        }, 2000);
    } catch (error) {
        showAcademicPeriodMessage(error.message, "error");
    } finally {
        saveAcademicPeriodButton.disabled = false;
        saveAcademicPeriodButton.textContent = "Save Academic Calendar";
    }
});

loadAcademicPeriodSettings();

/* SHARED HANDWRITTEN RESULT SIGNATURES */
(() => {
    "use strict";

    const form = document.getElementById("resultFooterForm");
    if (!form) return;

    const dateInput = document.getElementById("resultSignatureDate");
    const message = document.getElementById("resultFooterMessage");
    const saveDateButton = document.getElementById("saveResultFooterButton");
    const dialog = document.getElementById("signatureEditorDialog");
    const canvas = document.getElementById("signatureCanvas");
    const context = canvas?.getContext("2d", { willReadFrequently: true });
    const hint = document.getElementById("signatureCanvasHint");
    const sizeInput = document.getElementById("signatureSize");
    const sizeOutput = document.getElementById("signatureSizeValue");
    const livePreview = document.getElementById("signatureLivePreview");
    const editorMessage = document.getElementById("signatureEditorMessage");
    const signatures = {
        class_teacher: { path: "", legacy: "", preview: document.getElementById("classTeacherSignaturePreview") },
        director: { path: "", legacy: "", preview: document.getElementById("directorSignaturePreview") }
    };

    let activeRole = null;
    let drawing = false;
    let history = [];

    function showMessage(text, type = "") {
        message.textContent = text;
        message.className = "form-message " + type;
    }

    function showEditorMessage(text) {
        editorMessage.textContent = text;
    }

    function renderSignature(role) {
        const item = signatures[role];
        if (!item.preview) return;
        item.preview.replaceChildren();

        if (item.path) {
            const image = document.createElement("img");
            image.src = "./" + item.path.replace(/^\/+/, "") + "?v=" + Date.now();
            image.alt = role === "class_teacher" ? "Saved Class Teacher signature" : "Saved Director signature";
            item.preview.appendChild(image);
        } else if (item.legacy) {
            const legacy = document.createElement("span");
            legacy.className = "signature-legacy-text";
            legacy.textContent = item.legacy;
            item.preview.appendChild(legacy);
        } else {
            const empty = document.createElement("span");
            empty.textContent = "No signature saved";
            item.preview.appendChild(empty);
        }
    }

    async function requestSettings(method = "GET", payload) {
        const response = await fetch("./api/settings/result-footer.php", {
            method,
            credentials: "same-origin",
            cache: "no-store",
            headers: { "Content-Type": "application/json", "Accept": "application/json" },
            ...(payload === undefined ? {} : { body: JSON.stringify(payload) })
        });
        const responseText = await response.text();
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            const preview = responseText.replace(/\s+/g, " ").trim().slice(0, 180);
            throw new Error(
                `Invalid JSON response from signature API (HTTP ${response.status}). ` +
                (preview ? `Server response: ${preview}` : "The server returned an empty response.")
            );
        }
        if (!response.ok || !data.success) {
            throw new Error(data.message || `Signature API request failed (HTTP ${response.status}).`);
        }
        return data;
    }

    async function loadSettings() {
        try {
            const data = await requestSettings();
            const settings = data.settings || {};
            signatures.class_teacher.path = settings.class_teacher_signature_image || "";
            signatures.class_teacher.legacy = settings.class_teacher_signature || "";
            signatures.director.path = settings.director_signature_image || "";
            signatures.director.legacy = settings.director_signature || "";
            if (dateInput) dateInput.value = settings.result_signature_date || "";
            renderSignature("class_teacher");
            renderSignature("director");
        } catch (error) {
            showMessage(error.message, "error");
        }
    }

    function clearCanvas() {
        context.clearRect(0, 0, canvas.width, canvas.height);
        history = [];
        hint.hidden = false;
        if (livePreview) livePreview.removeAttribute("src");
        showEditorMessage("");
    }

    function snapshot() {
        history.push(context.getImageData(0, 0, canvas.width, canvas.height));
        if (history.length > 30) history.shift();
    }

    function pointFromEvent(event) {
        const rect = canvas.getBoundingClientRect();
        return {
            x: (event.clientX - rect.left) * canvas.width / rect.width,
            y: (event.clientY - rect.top) * canvas.height / rect.height
        };
    }

    canvas?.addEventListener("pointerdown", event => {
        if (!context) return;
        event.preventDefault();
        snapshot();
        drawing = true;
        hint.hidden = true;
        canvas.setPointerCapture(event.pointerId);
        const point = pointFromEvent(event);
        context.beginPath();
        context.moveTo(point.x, point.y);
        context.lineWidth = 5;
        context.lineCap = "round";
        context.lineJoin = "round";
        context.strokeStyle = "#111111";
        context.lineTo(point.x + 0.1, point.y + 0.1);
        context.stroke();
    });

    canvas?.addEventListener("pointermove", event => {
        if (!drawing || !context) return;
        event.preventDefault();
        const point = pointFromEvent(event);
        context.lineTo(point.x, point.y);
        context.stroke();
    });

    function stopDrawing() {
        drawing = false;
        context?.closePath();
        updateSignaturePreview();
    }
    canvas?.addEventListener("pointerup", stopDrawing);
    canvas?.addEventListener("pointercancel", stopDrawing);
    canvas?.addEventListener("lostpointercapture", stopDrawing);

    document.getElementById("undoSignatureStroke")?.addEventListener("click", () => {
        const previous = history.pop();
        if (!previous) return;
        context.putImageData(previous, 0, 0);
        hint.hidden = !isCanvasEmpty();
        updateSignaturePreview();
    });

    function isCanvasEmpty() {
        const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
        for (let i = 3; i < pixels.length; i += 4) {
            if (pixels[i] !== 0) return false;
        }
        return true;
    }

    document.getElementById("clearSignatureCanvas")?.addEventListener("click", clearCanvas);
    sizeInput?.addEventListener("input", () => {
        sizeOutput.value = sizeInput.value + "%";
        sizeOutput.textContent = sizeInput.value + "%";
        updateSignaturePreview();
    });

    function createTransparentSignature() {
        const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
        let minX = canvas.width, minY = canvas.height, maxX = -1, maxY = -1;
        for (let y = 0; y < canvas.height; y++) {
            for (let x = 0; x < canvas.width; x++) {
                if (pixels.data[(y * canvas.width + x) * 4 + 3] > 12) {
                    minX = Math.min(minX, x);
                    minY = Math.min(minY, y);
                    maxX = Math.max(maxX, x);
                    maxY = Math.max(maxY, y);
                }
            }
        }
        if (maxX < minX || maxY < minY) {
            throw new Error("Please draw the signature before saving.");
        }

        const padding = 18;
        minX = Math.max(0, minX - padding);
        minY = Math.max(0, minY - padding);
        maxX = Math.min(canvas.width - 1, maxX + padding);
        maxY = Math.min(canvas.height - 1, maxY + padding);
        const cropWidth = maxX - minX + 1;
        const cropHeight = maxY - minY + 1;

        const output = document.createElement("canvas");
        output.width = 1200;
        output.height = 300;
        const outputContext = output.getContext("2d");
        // Fit the complete signature first, then apply the selected percentage.
        // This keeps every slider value effective even when height is the limiting dimension.
        const fitScale = Math.min(
            (output.width * 0.9) / cropWidth,
            (output.height * 0.8) / cropHeight
        );
        const scale = fitScale * (Number(sizeInput.value) / 100);
        const drawWidth = cropWidth * scale;
        const drawHeight = cropHeight * scale;
        outputContext.drawImage(
            canvas,
            minX, minY, cropWidth, cropHeight,
            (output.width - drawWidth) / 2,
            (output.height - drawHeight) / 2,
            drawWidth, drawHeight
        );
        return output.toDataURL("image/png");
    }

    function updateSignaturePreview() {
        if (!livePreview) return;
        if (isCanvasEmpty()) {
            livePreview.removeAttribute("src");
            return;
        }
        try {
            livePreview.src = createTransparentSignature();
        } catch (error) {
            livePreview.removeAttribute("src");
        }
    }

    function openEditor(role) {
        activeRole = role;
        clearCanvas();
        document.getElementById("signatureEditorTitle").textContent =
            role === "class_teacher" ? "Class Teacher Signature" : "Director / Principal Signature";
        sizeInput.value = "85";
        sizeOutput.value = "85%";
        sizeOutput.textContent = "85%";
        if (livePreview) livePreview.removeAttribute("src");
        showEditorMessage("");
        if (typeof dialog.showModal === "function") dialog.showModal();
        else dialog.setAttribute("open", "");
    }

    document.querySelectorAll("[data-signature-role]").forEach(button => {
        button.addEventListener("click", () => openEditor(button.dataset.signatureRole));
    });

    function closeEditor() {
        if (typeof dialog.close === "function") dialog.close();
        else dialog.removeAttribute("open");
        activeRole = null;
    }

    document.getElementById("closeSignatureEditor")?.addEventListener("click", closeEditor);
    document.getElementById("cancelSignatureEditor")?.addEventListener("click", closeEditor);

    document.getElementById("saveDrawnSignature")?.addEventListener("click", async () => {
        if (!activeRole) return;
        const button = document.getElementById("saveDrawnSignature");
        button.disabled = true;
        showEditorMessage("Preparing transparent signature…");
        try {
            const dataUrl = createTransparentSignature();
            const payload = {
                class_teacher_signature_image: activeRole === "class_teacher" ? dataUrl : undefined,
                director_signature_image: activeRole === "director" ? dataUrl : undefined,
                result_signature_date: dateInput?.value || ""
            };
            const data = await requestSettings("POST", payload);
            const settings = data.settings || {};
            if (activeRole === "class_teacher") {
                signatures.class_teacher.path = settings.class_teacher_signature_image || "";
                signatures.class_teacher.legacy = "";
            } else {
                signatures.director.path = settings.director_signature_image || "";
                signatures.director.legacy = "";
            }
            renderSignature(activeRole);
            showMessage("Signature saved and applied to all student results.", "success");
            closeEditor();
        } catch (error) {
            showEditorMessage(error.message || "Unable to save signature.");
        } finally {
            button.disabled = false;
        }
    });

    document.querySelectorAll("[data-signature-clear]").forEach(button => {
        button.addEventListener("click", async () => {
            const role = button.dataset.signatureClear;
            if (!window.confirm("Remove this shared signature from all student results?")) return;
            button.disabled = true;
            try {
                const payload = {
                    class_teacher_signature_image: role === "class_teacher" ? "" : undefined,
                    director_signature_image: role === "director" ? "" : undefined,
                    result_signature_date: dateInput?.value || ""
                };
                const data = await requestSettings("POST", payload);
                const settings = data.settings || {};
                signatures[role].path = settings[role === "class_teacher" ? "class_teacher_signature_image" : "director_signature_image"] || "";
                signatures[role].legacy = "";
                renderSignature(role);
                showMessage("Signature removed from all student results.", "success");
            } catch (error) {
                showMessage(error.message, "error");
            } finally {
                button.disabled = false;
            }
        });
    });

    form.addEventListener("submit", async event => {
        event.preventDefault();
        saveDateButton.disabled = true;
        saveDateButton.textContent = "Saving…";
        showMessage("", "");
        try {
            await requestSettings("POST", {
                result_signature_date: dateInput?.value || ""
            });
            showMessage("Shared result date saved successfully.", "success");
        } catch (error) {
            showMessage(error.message, "error");
        } finally {
            saveDateButton.disabled = false;
            saveDateButton.textContent = "Save Result Date";
        }
    });

    loadSettings();
})();


/* MARKAZ Support WhatsApp setting — independent of existing settings handlers */
(() => {
  "use strict";
  if (!/settings\.html?$/i.test(location.pathname) || document.getElementById("markazSupportSettings")) return;

  const host = document.querySelector("main") ||
    document.querySelector(".settings-content") ||
    document.querySelector(".settings-container") ||
    document.body;

  const section = document.createElement("section");
  section.id = "markazSupportSettings";
  section.className = "settings-card markaz-support-settings";
  section.innerHTML = `
    <h2>System Support &amp; Emergency Contact</h2>
    <p>Set the official WhatsApp contact for technical assistance, account or access problems, and MARKAZ system-management emergencies. This contact is for authorized users who need help with the system.</p>
    <form id="markazSupportForm">
      <label for="markazSupportNumber">System Support WhatsApp Number</label>
      <input id="markazSupportNumber" name="whatsapp_number" type="tel"
        inputmode="tel" autocomplete="tel" required value="07048657955"
        placeholder="e.g. 07048657955 or +2347048657955">
      <button type="submit" id="saveSupportContactButton">Save Support Number</button>
      <p id="markazSupportStatus" role="status" aria-live="polite"></p>
    </form>`;
  host.appendChild(section);

  const input = section.querySelector("#markazSupportNumber");
  const status = section.querySelector("#markazSupportStatus");
  const form = section.querySelector("#markazSupportForm");

  fetch("./api/settings/support-contact.php", { credentials: "same-origin" })
    .then(r => r.json())
    .then(data => {
      if (data.success && data.whatsapp_number) input.value = data.whatsapp_number;
    })
    .catch(() => { status.textContent = "Could not load the saved number. You can still enter one and save."; });

  form.addEventListener("submit", async event => {
    event.preventDefault();
    const saveButton = form.querySelector("#saveSupportContactButton");
    saveButton.disabled = true;
    saveButton.textContent = "Saving…";
    status.textContent = "Saving…";
    try {
      const response = await fetch("./api/settings/support-contact.php", {
        method: "POST",
        credentials: "same-origin",
        body: new FormData(form)
      });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || "Save failed.");
      input.value = data.whatsapp_number || input.value;
      status.textContent = data.message || "Support WhatsApp number saved.";
      saveButton.textContent = "Saved";
    } catch (error) {
      status.textContent = error.message || "Could not save the support number.";
      saveButton.textContent = "Retry";
    } finally {
      saveButton.disabled = false;
      window.setTimeout(() => { saveButton.textContent = "Save Support Number"; }, 1400);
    }
  });
})();


/* MARKAZ ROLE ACCESS AND RESULT PRINT/EXPORT PERMISSIONS */
(() => {
    "use strict";

    const roles = [
        ["admin", "Admin"],
        ["exam_officer", "Exam Officer"],
        ["teacher", "Teacher"],
        ["student", "Student"],
        ["management", "Management"]
    ];
    const access = [
        ["exam_officer_access", "Exam Officer portal"],
        ["teacher_access", "Teacher portal"],
        ["student_result_access", "Student portal"],
        ["management_access", "Management portal"],
        ["login_lockout_enabled", "Enable login lockout after 3 failed attempts (staff accounts: 7 days)"]
    ];

    const section = document.createElement("section");
    section.id = "markazRolePermissions";
    section.className = "settings-card markaz-role-permissions-card";
    section.innerHTML = `
        <div class="settings-card-heading">
            <div>
                <span class="settings-label">SENIOR ADMIN</span>
                <h2>Role Access &amp; Result Permissions</h2>
                <p>Set portal access and independently grant printing or exporting.</p>
            </div>
        </div>
        <form id="markazRolePermissionsForm">
            <div id="markazPermissionFields" class="markaz-permission-fields" style="display:grid;gap:16px">
                <div class="markaz-role-permissions-access"></div>
                <div class="markaz-role-permissions-grid"></div>
            </div>
            <p id="markazPermissionMessage" role="status" aria-live="polite"></p>
            <button type="submit" id="markazSavePermissions">Save Permissions</button>
        </form>`;
    (document.querySelector("main") || document.body).appendChild(section);

    const fields = section.querySelector("#markazPermissionFields");
    const accessFields = fields.querySelector(".markaz-role-permissions-access");
    const roleFields = fields.querySelector(".markaz-role-permissions-grid");
    const form = section.querySelector("#markazRolePermissionsForm");
    const message = section.querySelector("#markazPermissionMessage");
    const save = section.querySelector("#markazSavePermissions");
    const inputs = {};

    function addCheckbox(parent, key, label) {
        const row = document.createElement("label");
        row.style.cssText = "display:flex;align-items:center;gap:10px;padding:7px 0";
        if (key === "login_lockout_enabled") {
            row.classList.add("markaz-login-lockout-setting");
        }
        const input = document.createElement("input");
        input.type = "checkbox";
        input.name = key;
        input.style.cssText = "width:18px;height:18px;flex:0 0 auto";
        const caption = document.createElement("span");
        caption.textContent = label;
        row.append(input, caption);
        parent.appendChild(row);
        inputs[key] = input;
    }

    access.forEach(([key, label]) => addCheckbox(accessFields, key, label));

    roles.forEach(([role, label]) => {
        const group = document.createElement("fieldset");
        group.style.cssText = "border:1px solid #d5dfd8;border-radius:12px;padding:12px";
        const legend = document.createElement("legend");
        legend.textContent = label;
        group.appendChild(legend);
        addCheckbox(group, `${role}_print_results`, "Allow printing student results");
        addCheckbox(group, `${role}_export_results`, "Allow downloading/exporting student results");
        roleFields.appendChild(group);
    });

    async function request(method = "GET", body) {
        const response = await fetch("./api/settings/access-control.php", {
            method,
            credentials: "same-origin",
            cache: "no-store",
            headers: { "Content-Type": "application/json" },
            ...(body === undefined ? {} : { body: JSON.stringify(body) })
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to update permissions.");
        }
        return data;
    }

    request().then(data => {
        Object.entries(inputs).forEach(([key, input]) => {
            input.checked = Boolean(data.settings?.[key]);
        });
        message.textContent = "Permissions loaded.";
    }).catch(error => {
        message.textContent = error.message;
    });

    form.addEventListener("submit", async event => {
        event.preventDefault();
        save.disabled = true;
        message.textContent = "Saving…";
        const updates = {};
        Object.entries(inputs).forEach(([key, input]) => {
            updates[key] = input.checked;
        });
        try {
            const data = await request("POST", updates);
            message.textContent = data.message || "Permissions saved.";
        } catch (error) {
            message.textContent = error.message;
        } finally {
            save.disabled = false;
        }
    });
})();



/* MARKAZ LOGIN LOCKOUT ADMIN CONTROLS */
(() => {
    "use strict";
    if (document.getElementById("markazLoginLockouts")) return;

    const section = document.createElement("section");
    section.id = "markazLoginLockouts";
    section.className = "settings-card markaz-locked-accounts-card";
    section.innerHTML = `
      <div class="settings-card-heading">
        <div>
          <span class="settings-label">SENIOR ADMIN</span>
          <h2>Locked Accounts</h2>
          <p>View active login lockouts and manually unlock accounts.</p>
        </div>
      </div>
      <p id="markazLockoutStatus" role="status" aria-live="polite"></p>
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
          <thead><tr><th>Role</th><th>Account identifier</th><th>Attempts</th><th>Unlocks at</th><th>Time left</th><th>Action</th></tr></thead>
          <tbody id="markazLockoutRows"></tbody>
        </table>
      </div>
      <button type="button" id="markazRefreshLockouts">Refresh Locked Accounts</button>`;
    (document.querySelector("main") || document.body).appendChild(section);

    const rows = section.querySelector("#markazLockoutRows");
    const status = section.querySelector("#markazLockoutStatus");
    const refresh = section.querySelector("#markazRefreshLockouts");

    async function api(method = "GET", body) {
      const response = await fetch("./api/settings/login-lockouts.php", {
        method, credentials: "same-origin", cache: "no-store",
        headers: { "Content-Type": "application/json" },
        ...(body ? { body: JSON.stringify(body) } : {})
      });
      const raw = await response.text();
      let data;
      try {
        data = JSON.parse(raw);
      } catch {
        throw new Error("The locked-accounts endpoint returned a non-JSON response. Check the API route and server error log.");
      }
      if (!response.ok || !data.success) throw new Error(data.message || "Request failed.");
      return data;
    }

    function cell(row, value) {
      const td = document.createElement("td");
      td.style.cssText = "padding:10px;border-bottom:1px solid #d5dfd8";
      td.textContent = value;
      row.appendChild(td);
      return td;
    }

    async function load() {
      refresh.disabled = true;
      status.textContent = "Loading locked accounts…";
      status.className = "form-message";
      rows.replaceChildren();
      try {
        const data = await api();
        if (!data.accounts.length) {
          status.textContent = "There are no currently locked accounts.";
          status.className = "form-message success";
          return;
        }
        status.textContent = `${data.accounts.length} locked account(s).`;
        status.className = "form-message success";
        data.accounts.forEach(account => {
          const tr = document.createElement("tr");
          cell(tr, account.role);
          cell(tr, account.identifier);
          cell(tr, String(account.failed_attempts));
          cell(tr, account.locked_until);
          const seconds = Number(account.remaining_seconds) || 0;
          cell(tr, `${Math.floor(seconds / 86400)}d ${Math.floor(seconds % 86400 / 3600)}h ${Math.floor(seconds % 3600 / 60)}m`);
          const td = document.createElement("td");
          td.style.padding = "10px";
          const button = document.createElement("button");
          button.type = "button";
          button.textContent = "Unlock";
          button.addEventListener("click", async () => {
            if (!window.confirm(`Unlock ${account.identifier}?`)) return;
            button.disabled = true;
            try {
              const result = await api("POST", { action: "unlock", id: Number(account.id) });
              status.textContent = result.message;
              status.className = "form-message success";
              await load();
            } catch (error) {
              status.textContent = error.message;
              status.className = "form-message error";
              button.disabled = false;
            }
          });
          td.appendChild(button);
          tr.appendChild(td);
          rows.appendChild(tr);
        });
      } catch (error) {
        status.textContent = error.message || "Unable to load locked accounts.";
        status.className = "form-message error";
      } finally {
        refresh.disabled = false;
      }
    }

    refresh.addEventListener("click", load);
    load();
})();

/* MARKAZ SENIOR ADMIN — ACCOUNT DEVICES AND USAGE HISTORY */
(() => {
    "use strict";
    const section = document.getElementById("markazAccountDevices") || document.createElement("section");
    section.id = "markazAccountDevices";
    section.className = "settings-card markaz-account-devices-card account-devices-card";
    section.hidden = true;
    section.innerHTML = `
      <div class="settings-card-heading">
        <div>
          <span class="settings-label">SENIOR ADMIN · SECURITY</span>
          <h2>Account Devices</h2>
          <p>Review recognised staff and student devices, monitor active usage, and manage device access.</p>
        </div>
      </div>

      <section class="account-device-section" aria-labelledby="deviceAccessHeading">
        <div class="account-device-section-heading">
          <div><h3 id="deviceAccessHeading">Device Access</h3><p>Freeze or restore access for a recognised staff device.</p></div>
          <button type="button" class="account-device-secondary" id="markazRefreshDevices">Refresh Devices</button>
        </div>
        <p id="markazDeviceStatus" class="account-device-status" role="status" aria-live="polite"></p>
        <div class="account-device-table-wrap">
          <table class="account-device-table">
            <thead><tr><th>Account</th><th>Role</th><th>Device / Browser</th><th>Last sign-in</th><th>Status</th><th>Action</th></tr></thead>
            <tbody id="markazDeviceRows"></tbody>
          </table>
        </div>
      </section>

      <section class="account-device-section account-device-usage" aria-labelledby="deviceUsageHeading">
        <div class="account-device-section-heading">
          <div><h3 id="deviceUsageHeading">Device Usage History</h3><p>Active time is accumulated while the page is visible and the user is interacting with MARKAZ. Historical records remain until the Senior Admin clears them.</p></div>
          <button type="button" class="account-device-danger" id="markazClearDeviceActivity">Clear Activity History</button>
        </div>
        <p id="markazActivityStatus" class="account-device-status" role="status" aria-live="polite"></p>
        <div class="account-device-table-wrap">
          <table class="account-device-table">
            <thead><tr><th>Account</th><th>Role</th><th>Device / Browser</th><th>Total Active Time</th><th>Last Activity</th><th>Presence</th></tr></thead>
            <tbody id="markazActivityRows"></tbody>
          </table>
        </div>
      </section>`;
    if (!section.isConnected) {
        (document.querySelector(".settings-page") || document.querySelector("main") || document.body).appendChild(section);
    }

    const rows = section.querySelector("#markazDeviceRows");
    const status = section.querySelector("#markazDeviceStatus");
    const refresh = section.querySelector("#markazRefreshDevices");
    const activityRows = section.querySelector("#markazActivityRows");
    const activityStatus = section.querySelector("#markazActivityStatus");
    const clearActivity = section.querySelector("#markazClearDeviceActivity");

    async function requestDevices(method = "GET", body) {
        const response = await fetch("./api/settings/account-devices.php", {
            method, credentials: "same-origin", cache: "no-store",
            headers: { "Content-Type": "application/json", "Accept": "application/json" },
            ...(body === undefined ? {} : { body: JSON.stringify(body) })
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || "Unable to update device access.");
        return data;
    }

    async function requestActivity(method = "GET", body) {
        const response = await fetch("./api/settings/account-device-activity.php", {
            method, credentials: "same-origin", cache: "no-store",
            headers: { "Content-Type": "application/json", "Accept": "application/json" },
            ...(body === undefined ? {} : { body: JSON.stringify(body) })
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || "Unable to load device activity.");
        return data;
    }

    function addCell(row, value, className) {
        const cell = document.createElement("td");
        if (className) cell.className = className;
        cell.textContent = String(value ?? "—");
        row.appendChild(cell);
        return cell;
    }

    function formatDuration(value) {
        const seconds = Math.max(0, Number(value) || 0);
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainder = seconds % 60;
        if (hours) return hours + "h " + minutes + "m";
        if (minutes) return minutes + "m " + remainder + "s";
        return remainder + "s";
    }

    function formatDate(value) {
        if (!value) return "Not recorded";
        const date = new Date(String(value).replace(" ", "T"));
        return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
    }

    async function loadDevices() {
        refresh.disabled = true;
        status.textContent = "Loading recognised staff devices…";
        status.className = "account-device-status";
        rows.replaceChildren();
        try {
            const data = await requestDevices();
            if (!data.devices.length) {
                status.textContent = "No recognised staff devices have signed in yet.";
                return;
            }
            status.textContent = data.devices.length + " recognised staff device(s).";
            data.devices.forEach(device => {
                const row = document.createElement("tr");
                addCell(row, device.account_name);
                addCell(row, device.role);
                addCell(row, device.user_agent, "account-device-agent");
                addCell(row, formatDate(device.last_seen_at));
                const state = addCell(row, device.is_blocked ? "Frozen" : "Allowed");
                state.className = device.is_blocked ? "account-device-state is-blocked" : "account-device-state is-allowed";
                const actionCell = document.createElement("td");
                const button = document.createElement("button");
                button.type = "button";
                button.className = device.is_blocked ? "account-device-secondary" : "account-device-danger";
                button.textContent = device.is_blocked ? "Unfreeze" : "Freeze";
                button.addEventListener("click", async () => {
                    const action = device.is_blocked ? "unblock" : "block";
                    const reason = action === "block"
                        ? window.prompt("Why are you freezing this device for " + device.account_name + "?")
                        : "";
                    if (action === "block" && (reason === null || !reason.trim())) return;
                    if (!window.confirm((action === "block" ? "Freeze" : "Unfreeze") + " this device for " + device.account_name + "?")) return;
                    button.disabled = true;
                    try {
                        const result = await requestDevices("POST", { action, id: Number(device.id), reason: reason || "" });
                        status.textContent = result.message;
                        await loadDevices();
                    } catch (error) {
                        status.textContent = error.message;
                        button.disabled = false;
                    }
                });
                actionCell.appendChild(button);
                row.appendChild(actionCell);
                rows.appendChild(row);
            });
        } catch (error) {
            status.textContent = error.message || "Unable to load recognised devices.";
        } finally {
            refresh.disabled = false;
        }
    }

    async function loadActivity() {
        activityStatus.textContent = "Loading staff and student activity…";
        activityRows.replaceChildren();
        try {
            const data = await requestActivity();
            if (!data.activity.length) {
                activityStatus.textContent = "No device activity has been recorded yet.";
                return;
            }
            activityStatus.textContent = data.activity.length + " account/device record(s).";
            data.activity.forEach(item => {
                const row = document.createElement("tr");
                addCell(row, item.display_name);
                addCell(row, item.role === "student" ? "Student" : item.role.replaceAll("_", " "));
                addCell(row, item.user_agent, "account-device-agent");
                addCell(row, formatDuration(item.total_active_seconds), "account-device-duration");
                addCell(row, formatDate(item.last_activity_at));
                const presence = addCell(row, item.is_active ? "Active now" : "Inactive");
                presence.className = item.is_active ? "account-device-state is-active" : "account-device-state is-inactive";
                activityRows.appendChild(row);
            });
        } catch (error) {
            activityStatus.textContent = error.message || "Unable to load device activity.";
        }
    }

    refresh.addEventListener("click", () => { loadDevices(); loadActivity(); });
    clearActivity.addEventListener("click", async () => {
        if (!window.confirm("Clear all staff and student device activity history? This cannot be undone.")) return;
        clearActivity.disabled = true;
        try {
            const result = await requestActivity("POST", { action: "clear_history" });
            activityStatus.textContent = result.message;
            await loadActivity();
        } catch (error) {
            activityStatus.textContent = error.message || "Unable to clear activity history.";
        } finally {
            clearActivity.disabled = false;
        }
    });

    fetch("./api/auth/me.php", { credentials: "same-origin", cache: "no-store" })
        .then(response => response.json())
        .then(data => {
            if (!data.success || data.admin?.role !== "admin") {
                section.remove();
                return;
            }
            // The grouped settings navigator owns this section's visibility.
            loadDevices();
            loadActivity();
        })
        .catch(() => section.remove());
})();

/* MARKAZ ADMIN-ONLY ACADEMIC RESULT RELEASE */
(() => {
    "use strict";
    if (document.getElementById("markazResultRelease")) return;

    const section = document.createElement("section");
    section.id = "markazResultRelease";
    section.className = "settings-card";
    section.innerHTML = `
      <div class="settings-card-heading">
        <div>
          <span class="settings-label">SENIOR ADMIN</span>
          <h2>Academic Results Release</h2>
          <p>Results remain hidden from students until you release each term.</p>
        </div>
      </div>
      <p id="markazReleaseMessage" role="status" aria-live="polite"></p>
      <div id="markazReleasePeriods"></div>`;
    (document.querySelector(".settings-page") ||
     document.querySelector("main") ||
     document.body).appendChild(section);

    const list = section.querySelector("#markazReleasePeriods");
    const message = section.querySelector("#markazReleaseMessage");

    async function request(method = "GET", body) {
        const response = await fetch("./api/settings/result-release.php", {
            method,
            credentials: "same-origin",
            cache: "no-store",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            ...(body === undefined ? {} : { body: JSON.stringify(body) })
        });

        const raw = await response.text();
        let data;

        try {
            data = JSON.parse(raw);
        } catch {
            throw new Error(
                "The server returned an invalid response. Check your login session and the result-release API."
            );
        }

        if (!response.ok || !data || !data.success) {
            throw new Error(
                (data && typeof data.message === "string" && data.message) ||
                `Unable to save release settings (HTTP ${response.status}).`
            );
        }

        return data;
    }

    async function loadPeriods() {
        message.textContent = "Loading academic periods…";
        list.replaceChildren();

        try {
            const data = await request();
            if (!data.periods.length) {
                message.textContent = "No academic periods are configured yet.";
                return;
            }

            data.periods.forEach(period => {
                const row = document.createElement("label");
                row.className = "markaz-release-row";

                const title = document.createElement("span");
                title.className = "markaz-release-term";
                title.textContent = `${period.session_name} — ${period.term_name}`;

                const control = document.createElement("span");
                control.className = "markaz-release-control";

                const toggle = document.createElement("input");
                toggle.type = "checkbox";
                toggle.className = "markaz-release-toggle";
                toggle.checked = Number(period.results_released) === 1;
                toggle.setAttribute(
                    "aria-label",
                    `Release results for ${period.session_name}, ${period.term_name}`
                );

                const status = document.createElement("span");
                status.className = "markaz-release-status";

                const updateStatus = () => {
                    status.textContent = toggle.checked ? "ON · Released" : "OFF · Hidden";
                    status.classList.toggle("is-released", toggle.checked);
                    status.classList.toggle("is-hidden", !toggle.checked);
                };
                updateStatus();

                toggle.addEventListener("change", async () => {
                    const requestedState = toggle.checked;
                    toggle.disabled = true;
                    message.textContent = "Saving release status…";
                    try {
                        const payload = {
                            period_id: Number(period.period_id),
                            released: requestedState
                        };
                        if (window.markazCurrentRole === "exam_officer") {
                            const password = window.prompt("Enter your Exam Officer password to change result visibility.");
                            if (password === null || password === "") {
                                toggle.checked = !requestedState;
                                message.textContent = "Result visibility was not changed.";
                                return;
                            }
                            payload.password = password;
                        }
                        const result = await request("POST", payload);
                        message.textContent = result.message || "Release status saved.";
                    } catch (error) {
                        toggle.checked = !requestedState;
                        message.textContent = error.message;
                    } finally {
                        updateStatus();
                        toggle.disabled = false;
                    }
                });

                control.append(toggle, status);
                row.append(title, control);
                list.appendChild(row);
            });

            message.textContent = "Switch a term ON to release results, or OFF to hide them.";
        } catch (error) {
            message.textContent = error.message;
        }
    }

    loadPeriods();
})();


/* ACADEMIC RESULT ARCHIVE — ADMIN SETTINGS */
async function loadAcademicArchive() {
    const currentTitle = document.getElementById("academicArchiveCurrentTitle");
    const currentDescription = document.getElementById("academicArchiveCurrentDescription");
    const currentPanel = document.getElementById("academicArchiveCurrent");
    const history = document.getElementById("academicArchiveHistory");
    const finalizeButton = document.getElementById("finalizeAcademicPeriodButton");
    if (!currentTitle || !history || !finalizeButton) return;

    finalizeButton.disabled = true;
    currentTitle.textContent = "Loading current academic period…";
    currentDescription.textContent = "Checking term status.";
    history.replaceChildren();

    try {
        const [currentResponse, archiveResponse] = await Promise.all([
            fetch("./api/settings/academic-period.php", {
                credentials: "same-origin", cache: "no-store",
                headers: { "Accept": "application/json" }
            }),
            fetch("./api/settings/finalize-academic-period.php", {
                credentials: "same-origin", cache: "no-store",
                headers: { "Accept": "application/json" }
            })
        ]);

        async function readJson(response, label) {
            const body = await response.text();
            let data;
            try {
                data = JSON.parse(body);
            } catch {
                throw new Error(`${label} returned a non-JSON response (HTTP ${response.status}). Check that the matching public API route exists.`);
            }
            if (!response.ok || !data.success) {
                throw new Error(data.message || `${label} failed (HTTP ${response.status}).`);
            }
            return data;
        }

        const [currentData, archiveData] = await Promise.all([
            readJson(currentResponse, "Current academic period API"),
            readJson(archiveResponse, "Academic archive API")
        ]);

        const period = currentData.academic_period || {};
        const finalized = Number(period.is_finalized) === 1;
        currentTitle.textContent = [period.session_name, period.term_name].filter(Boolean).join(" — ") || "Current term";
        currentDescription.textContent = finalized
            ? `Finalized ${period.finalized_at ? "on " + period.finalized_at : ""}. This term is locked against further edits.`
            : "This term is active and still accepts authorized result and behavioural entries.";
        currentPanel?.classList.toggle("is-finalized", finalized);
        currentPanel?.classList.toggle("is-open", !finalized);
        finalizeButton.disabled = finalized;
        finalizeButton.innerHTML = finalized
            ? '<i class="fa-solid fa-lock" aria-hidden="true"></i> Term Finalized'
            : '<i class="fa-solid fa-lock" aria-hidden="true"></i> Finalize &amp; Lock Term';

        const historyItems = Array.isArray(archiveData.periods) ? archiveData.periods : [];
        if (!historyItems.length) {
            const empty = document.createElement("p");
            empty.className = "settings-loading";
            empty.textContent = "No academic periods have been created yet.";
            history.appendChild(empty);
            return;
        }

        historyItems.forEach(item => {
            const row = document.createElement("article");
            row.className = "academic-archive-row";

            const details = document.createElement("div");
            details.className = "academic-archive-row-details";
            const title = document.createElement("strong");
            title.textContent = `${item.session_name} — ${item.term_name}`;
            const meta = document.createElement("span");
            meta.textContent = `${item.saved_result_rows} subject(s) with scores · ${item.behavioural_reports} behavioural report(s) with ratings`;
            details.append(title, meta);

            const statuses = document.createElement("div");
            statuses.className = "academic-archive-row-statuses";
            const state = document.createElement("span");
            state.className = "academic-archive-pill " + (item.is_finalized ? "is-finalized" : item.is_current ? "is-current" : "is-open");
            state.textContent = item.is_finalized ? "Finalized · Archived" : item.is_current ? "Current · In progress" : "Not finalized";
            const release = document.createElement("span");
            release.className = "academic-archive-release " + (item.results_released ? "is-released" : "is-hidden");
            release.textContent = item.results_released ? "Results released" : "Results hidden";
            statuses.append(state, release);
            row.append(details, statuses);
            history.appendChild(row);
        });
    } catch (error) {
        currentTitle.textContent = "Academic archive unavailable";
        currentDescription.textContent = error.message || "Unable to load academic history.";
        currentPanel?.classList.remove("is-finalized", "is-open");
        const retry = document.createElement("p");
        retry.className = "form-message error";
        retry.textContent = error.message || "Unable to load academic history.";
        history.appendChild(retry);
        finalizeButton.disabled = true;
    }
}

(() => {
    const button = document.getElementById("finalizeAcademicPeriodButton");
    const dialog = document.getElementById("academicFinalizePasswordDialog");
    const form = document.getElementById("academicFinalizePasswordForm");
    const passwordInput = document.getElementById("academicFinalizePassword");
    const cancelButton = document.getElementById("cancelAcademicFinalize");
    const message = document.getElementById("academicArchiveMessage");
    if (!button || !dialog || !form || !passwordInput) return;

    button.addEventListener("click", () => {
        if (button.disabled) return;
        passwordInput.value = "";
        if (typeof dialog.showModal === "function") dialog.showModal();
        else dialog.setAttribute("open", "");
        passwordInput.focus();
    });

    cancelButton?.addEventListener("click", () => {
        if (typeof dialog.close === "function") dialog.close();
        else dialog.removeAttribute("open");
        passwordInput.value = "";
    });

    form.addEventListener("submit", async event => {
        event.preventDefault();
        const password = passwordInput.value;
        if (!password) {
            passwordInput.focus();
            return;
        }

        const confirmed = window.confirm(
            "FINAL WARNING: This permanently locks the current term against score and behavioural edits. Have all results been checked?"
        );
        if (!confirmed) return;

        const submit = form.querySelector('[type="submit"]');
        button.disabled = true;
        if (submit) submit.disabled = true;
        if (message) {
            message.textContent = "Verifying password and finalizing term…";
            message.className = "form-message";
        }

        try {
            const response = await fetch("./api/settings/finalize-academic-period.php", {
                method: "POST",
                credentials: "same-origin",
                cache: "no-store",
                headers: { "Content-Type": "application/json", "Accept": "application/json" },
                body: JSON.stringify({ confirm: true, password })
            });
            const responseText = await response.text();
            let data;
            try {
                data = JSON.parse(responseText);
            } catch {
                throw new Error(`Academic archive API returned a non-JSON response (HTTP ${response.status}).`);
            }
            if (!response.ok || !data.success) {
                throw new Error(data.message || "Unable to finalize this academic term.");
            }

            if (typeof dialog.close === "function") dialog.close();
            else dialog.removeAttribute("open");
            passwordInput.value = "";
            if (message) {
                message.textContent = data.message || "Academic term finalized.";
                message.className = "form-message success";
            }
            await loadAcademicArchive();
        } catch (error) {
            if (message) {
                message.textContent = error.message || "Unable to finalize this academic term.";
                message.className = "form-message error";
            }
        } finally {
            if (submit) submit.disabled = false;
            passwordInput.value = "";
            await loadAcademicArchive();
        }
    });
})();

loadAcademicArchive();


/* PROTECTED ACADEMIC ENTRY RESET — password + accessible typed confirmation. */
(() => {
    const button = document.getElementById("clearAcademicEntryDataButton");
    const dialog = document.getElementById("clearAcademicEntryDataDialog");
    const form = document.getElementById("clearAcademicEntryDataForm");
    const password = document.getElementById("clearAcademicEntryDataPassword");
    const confirmation = document.getElementById("clearAcademicEntryDataConfirmation");
    const cancel = document.getElementById("cancelClearAcademicEntryData");
    const message = document.getElementById("clearAcademicEntryDataMessage");
    const dialogMessage = document.getElementById("clearAcademicEntryDataDialogMessage");
    const submit = form?.querySelector('[type="submit"]');

    if (!button || !dialog || !form || !password || !confirmation || !dialogMessage) return;

    const normalizePhrase = value => String(value || "")
        .trim()
        .replace(/\s+/g, " ")
        .toUpperCase();

    const showDialogMessage = (text, type = "error") => {
        dialogMessage.textContent = text;
        dialogMessage.className = "form-message " + type;
        dialogMessage.hidden = !text;
    };

    const closeDialog = () => {
        if (typeof dialog.close === "function") dialog.close();
        else dialog.removeAttribute("open");
        password.value = "";
        confirmation.value = "";
        showDialogMessage("");
        if (submit) submit.disabled = false;
    };

    button.addEventListener("click", () => {
        password.value = "";
        confirmation.value = "";
        showDialogMessage("");
        if (message) {
            message.textContent = "";
            message.className = "form-message";
        }
        if (typeof dialog.showModal === "function") dialog.showModal();
        else dialog.setAttribute("open", "");
        password.focus();
    });

    cancel?.addEventListener("click", closeDialog);
    confirmation.addEventListener("input", () => showDialogMessage(""));
    password.addEventListener("input", () => showDialogMessage(""));

    form.addEventListener("submit", async event => {
        event.preventDefault();
        const enteredPassword = password.value;
        const enteredPhrase = normalizePhrase(confirmation.value);

        if (!enteredPassword) {
            showDialogMessage("Enter your Senior Admin password to continue.");
            password.focus();
            return;
        }
        if (enteredPhrase !== "CLEAR ACADEMIC ENTRY DATA") {
            showDialogMessage('The confirmation phrase must read: CLEAR ACADEMIC ENTRY DATA. Letter case and extra spaces do not matter.');
            confirmation.focus();
            return;
        }

        if (!window.confirm("FINAL WARNING: All CA, examination and behavioural entries across every term will be permanently deleted. Student records and school settings will remain. Continue?")) return;

        button.disabled = true;
        if (submit) submit.disabled = true;
        showDialogMessage("Verifying your password and clearing academic entry data…", "info");

        try {
            const response = await fetch("./api/settings/clear-academic-entry-data.php", {
                method: "POST",
                credentials: "same-origin",
                cache: "no-store",
                headers: { "Content-Type": "application/json", "Accept": "application/json" },
                body: JSON.stringify({
                    confirm: true,
                    password: enteredPassword,
                    confirmation: enteredPhrase
                })
            });
            const raw = await response.text();
            let data;
            try {
                data = JSON.parse(raw);
            } catch {
                throw new Error(`Academic reset API returned a non-JSON response (HTTP ${response.status}).`);
            }
            if (!response.ok || !data.success) {
                throw new Error(data.message || `Academic entry reset failed (HTTP ${response.status}). No data was cleared unless success is confirmed.`);
            }

            closeDialog();
            if (message) {
                const counts = data.deleted || {};
                message.textContent = `Academic entries cleared: ${counts.subject_result_entries || 0} subject result rows, ${counts.behavioural_reports || 0} behavioural reports and ${counts.legacy_student_assessments || 0} legacy assessments. Student details and school setup were preserved.`;
                message.className = "form-message success";
            }
            await loadAcademicArchive();
        } catch (error) {
            showDialogMessage(error.message || "Unable to clear academic entry data. No success was reported.");
        } finally {
            button.disabled = false;
            if (submit) submit.disabled = false;
        }
    });
})();
