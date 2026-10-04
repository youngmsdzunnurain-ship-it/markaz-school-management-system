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

        if (!response.ok || !data.success) {
            window.location.href = "login.html";
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

/* Collapsible memorization settings */
const memorizationToggle = document.getElementById("memorizationSettingsToggle");
const memorizationBody = document.getElementById("memorizationSettingsBody");

if (memorizationToggle && memorizationBody) {
    memorizationToggle.addEventListener("click", () => {
        const isOpen = memorizationToggle.getAttribute("aria-expanded") === "true";

        memorizationToggle.setAttribute("aria-expanded", String(!isOpen));
        memorizationBody.hidden = isOpen;
        memorizationToggle.classList.toggle("is-open", !isOpen);
    });
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

/* RESULT FOOTER & SIGNATURE SETTINGS */
const resultFooterForm = document.getElementById("resultFooterForm");
const classTeacherSignature = document.getElementById("classTeacherSignature");
const directorSignature = document.getElementById("directorSignature");
const resultSignatureDate = document.getElementById("resultSignatureDate");
const resultFooterMessage = document.getElementById("resultFooterMessage");
const saveResultFooterButton = document.getElementById("saveResultFooterButton");

function showResultFooterMessage(text, type = "") {
    if (!resultFooterMessage) return;
    resultFooterMessage.textContent = text;
    resultFooterMessage.className = "form-message " + type;
}

async function loadResultFooterSettings() {
    try {
        const response = await fetch("./api/settings/result-footer.php", {
            credentials: "same-origin",
            cache: "no-store"
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "Unable to load result footer settings."
            );
        }

        const settings = data.settings || {};

        if (classTeacherSignature) {
            classTeacherSignature.value =
                settings.class_teacher_signature || "";
        }

        if (directorSignature) {
            directorSignature.value =
                settings.director_signature || "";
        }

        if (resultSignatureDate) {
            resultSignatureDate.value =
                settings.result_signature_date || "";
        }
    } catch (error) {
        showResultFooterMessage(error.message, "error");
    }
}

resultFooterForm?.addEventListener("submit", async event => {
    event.preventDefault();

    if (!saveResultFooterButton) return;

    saveResultFooterButton.disabled = true;
    saveResultFooterButton.textContent = "Saving...";
    showResultFooterMessage("", "");

    try {
        const response = await fetch("./api/settings/result-footer.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                class_teacher_signature:
                    classTeacherSignature?.value.trim() || "",
                director_signature:
                    directorSignature?.value.trim() || "",
                result_signature_date:
                    resultSignatureDate?.value || ""
            })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "Unable to save result footer settings."
            );
        }

        showResultFooterMessage(
            "Result footer settings saved successfully.",
            "success"
        );
    } catch (error) {
        showResultFooterMessage(error.message, "error");
    } finally {
        saveResultFooterButton.disabled = false;
        saveResultFooterButton.textContent = "Save Result Footer";
    }
});

loadResultFooterSettings();

