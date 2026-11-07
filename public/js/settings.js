const form = document.getElementById("profileForm");
const nameInput = document.getElementById("name");
const emailInput = document.getElementById("email");
const passwordInput = document.getElementById("currentPassword");
const imageInput = document.getElementById("profileImage");
const message = document.getElementById("formMessage");
const saveButton = document.getElementById("saveProfileButton");

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

async function loadProfile() {
    try {
        const response = await fetch("./api/auth/me.php", {
            credentials: "same-origin",
            cache: "no-store"
        });

        const data = await response.json();

        if (!response.ok || !data.success || !data.admin) {
            window.location.href = "login.html";
            return;
        }

        if (data.admin.role !== "admin") {
            window.location.replace("dashboard.html");
            return;
        }

        nameInput.value = data.admin.name || "";
        emailInput.value = data.admin.email || "";

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
            "Unable to load administrator profile.",
            "error"
        );
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
});

imageInput.addEventListener("change", () => {

    const file = imageInput.files[0];

    if (!file) return;

    const reader = new FileReader();

    reader.onload = event => {

        profilePhoto.innerHTML = "";

        const image = document.createElement("img");

        image.src = event.target.result;
        image.alt = "Profile picture";

        profilePhoto.appendChild(image);
    };

    reader.readAsDataURL(file);

    if (removeProfileButton) {
        removeProfileButton.dataset.remove = "0";
    }
});

form.addEventListener("submit", async event => {

    event.preventDefault();

    saveButton.disabled = true;
    saveButton.textContent = "Saving...";

    showMessage("", "");

    const formData = new FormData(form);

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

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(
                data.message || "Unable to save changes.",
                "error"
            );
            return;
        }

        previewName.textContent = data.admin.name;
        previewEmail.textContent = data.admin.email;

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

    if (!portion) {
        showMemorizationMessage(
            "Please enter a portion of memorization.",
            "error"
        );
        input.focus();
        return;
    }

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

loadMemorizationSettings();

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
      <button type="submit">Save Support Number</button>
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
    } catch (error) {
      status.textContent = error.message || "Could not save the support number.";
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
      const data = await response.json();
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
                        const result = await request("POST", {
                            period_id: Number(period.period_id),
                            released: requestedState
                        });
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
    const message = document.getElementById("academicArchiveMessage");
    if (!currentTitle || !history || !finalizeButton) return;

    finalizeButton.disabled = true;
    currentTitle.textContent = "Loading current academic period…";
    currentDescription.textContent = "Checking term status.";
    history.replaceChildren();

    try {
        const [currentResponse, archiveResponse] = await Promise.all([
            fetch("./api/settings/academic-period.php", {
                credentials: "same-origin", cache: "no-store"
            }),
            fetch("./api/settings/finalize-academic-period.php", {
                credentials: "same-origin", cache: "no-store"
            })
        ]);
        const currentData = await currentResponse.json();
        const archiveData = await archiveResponse.json();

        if (!currentResponse.ok || !currentData.success) {
            throw new Error(currentData.message || "Unable to load the current academic period.");
        }
        if (!archiveResponse.ok || !archiveData.success) {
            throw new Error(archiveData.message || "Unable to load academic history. Apply the academic archive database migration first.");
        }

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

        const periods = Array.isArray(archiveData.periods) ? archiveData.periods : [];
        if (!periods.length) {
            const empty = document.createElement("p");
            empty.className = "settings-loading";
            empty.textContent = "No academic periods have been created yet.";
            history.appendChild(empty);
            return;
        }

        periods.forEach(item => {
            const row = document.createElement("article");
            row.className = "academic-archive-row";

            const details = document.createElement("div");
            details.className = "academic-archive-row-details";
            const title = document.createElement("strong");
            title.textContent = `${item.session_name} — ${item.term_name}`;
            const meta = document.createElement("span");
            meta.textContent = `${item.saved_result_rows} saved subject result(s) · ${item.behavioural_reports} behavioural report(s)`;
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

document.getElementById("finalizeAcademicPeriodButton")?.addEventListener("click", async event => {
    const button = event.currentTarget;
    const confirmed = window.confirm(
        "Finalize and lock this academic term? First verify that every student's scores and behavioural assessments are complete. This action cannot be undone from Settings."
    );
    if (!confirmed) return;

    button.disabled = true;
    const message = document.getElementById("academicArchiveMessage");
    if (message) {
        message.textContent = "Finalizing academic term…";
        message.className = "form-message";
    }

    try {
        const response = await fetch("./api/settings/finalize-academic-period.php", {
            method: "POST",
            credentials: "same-origin",
            cache: "no-store",
            headers: { "Content-Type": "application/json", "Accept": "application/json" },
            body: JSON.stringify({ confirm: true })
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to finalize this academic term.");
        }
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
        button.disabled = false;
    }
});

loadAcademicArchive();
