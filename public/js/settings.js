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



/* MARKAZ RESULT COMPLETION THRESHOLD */
(() => {
  "use strict";

  function addCompletionThresholdCard() {
    const host =
      document.querySelector(".settings-content") ||
      document.querySelector(".settings-container");

    if (!host || document.getElementById("resultCompletionThresholdCard")) {
      return;
    }

    const section = document.createElement("section");
    section.id = "resultCompletionThresholdCard";
    section.className = "settings-card";
    section.innerHTML = `
      <h3>Result Completion &amp; Print Eligibility</h3>
      <p>Set the minimum percentage of required result fields that must be completed before an individual student's result can be printed.</p>
      <form id="resultCompletionThresholdForm">
        <label for="resultCompletionThreshold">Print eligibility threshold (%)</label>
        <input id="resultCompletionThreshold" name="threshold"
               type="number" min="0" max="100" step="0.1"
               required value="95">
        <p id="resultCompletionThresholdHelp" role="status">
          Loading the saved threshold…
        </p>
        <button type="submit" id="saveResultCompletionThreshold">
          Save Threshold
        </button>
      </form>
    `;

    host.appendChild(section);

    const form = section.querySelector("#resultCompletionThresholdForm");
    const input = section.querySelector("#resultCompletionThreshold");
    const help = section.querySelector("#resultCompletionThresholdHelp");
    const button = section.querySelector("#saveResultCompletionThreshold");
    const endpoint = "./api/settings/result-completion.php";

    fetch(endpoint, {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store"
    })
      .then(async response => {
        const data = await response.json();
        if (!response.ok || !data.success) {
          throw new Error(data.message || "Unable to load the saved threshold.");
        }
        input.value = String(data.threshold);
        help.textContent = `Current print eligibility threshold: ${data.threshold}%.`;
      })
      .catch(error => {
        help.textContent = error.message || "Unable to load the saved threshold.";
      });

    form.addEventListener("submit", async event => {
      event.preventDefault();

      const threshold = Number(input.value);
      if (!input.value.trim() || !Number.isFinite(threshold) ||
          threshold < 0 || threshold > 100) {
        help.textContent = "Enter a number from 0 to 100.";
        input.focus();
        return;
      }

      button.disabled = true;
      help.textContent = "Saving threshold…";

      try {
        const response = await fetch(endpoint, {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ threshold })
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
          throw new Error(data.message || "Unable to save the threshold.");
        }

        input.value = String(data.threshold);
        help.textContent = `Saved. Individual results require at least ${data.threshold}% completion.`;
      } catch (error) {
        help.textContent = error.message || "Unable to save the threshold.";
      } finally {
        button.disabled = false;
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", addCompletionThresholdCard, { once: true });
  } else {
    addCompletionThresholdCard();
  }
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
      rows.replaceChildren();
      try {
        const data = await api();
        if (!data.accounts.length) {
          status.textContent = "There are no currently locked accounts.";
          return;
        }
        status.textContent = `${data.accounts.length} locked account(s).`;
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
              await load();
            } catch (error) {
              status.textContent = error.message;
              button.disabled = false;
            }
          });
          td.appendChild(button);
          tr.appendChild(td);
          rows.appendChild(tr);
        });
      } catch (error) {
        status.textContent = error.message;
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
