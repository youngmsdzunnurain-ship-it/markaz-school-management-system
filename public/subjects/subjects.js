const subjectsTableBody = document.getElementById("subjectsTableBody");

const totalSubjectCount = document.getElementById("totalSubjectCount");
const activeSubjectCount = document.getElementById("activeSubjectCount");
const inactiveSubjectCount = document.getElementById("inactiveSubjectCount");

const subjectSearch = document.getElementById("subjectSearch");
const subjectStatusFilter = document.getElementById("subjectStatusFilter");

const addSubjectButton = document.getElementById("addSubjectButton");

const subjectModal = document.getElementById("subjectModal");
const closeSubjectModal = document.getElementById("closeSubjectModal");
const cancelSubjectButton = document.getElementById("cancelSubjectButton");

const subjectModalTitle = document.getElementById("subjectModalTitle");
const subjectForm = document.getElementById("subjectForm");
const subjectFormMessage = document.getElementById("subjectFormMessage");

const subjectId = document.getElementById("subjectId");
const subjectName = document.getElementById("subjectName");
const subjectCode = document.getElementById("subjectCode");
const subjectCategory = document.getElementById("subjectCategory");
const subjectCa1 = document.getElementById("subjectCa1");
const subjectCa2 = document.getElementById("subjectCa2");
const subjectExam = document.getElementById("subjectExam");
const saveSubjectButton = document.getElementById("saveSubjectButton");

let subjectRecords = [];


/* -----------------------------
   Helpers
----------------------------- */

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}


function showFormMessage(message) {
    subjectFormMessage.textContent = message;
    subjectFormMessage.hidden = false;
}


function hideFormMessage() {
    subjectFormMessage.textContent = "";
    subjectFormMessage.hidden = true;
}


/* -----------------------------
   Load subjects
----------------------------- */

async function loadSubjects() {
    subjectsTableBody.innerHTML = `
        <tr>
            <td colspan="6" class="empty-state">
                Loading subjects...
            </td>
        </tr>
    `;

    try {
        const response = await fetch("../api/subjects/list.php", {
            credentials: "same-origin"
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to load subjects.");
        }

        subjectRecords = data.subjects || [];

        updateSubjectSummary();
        renderSubjects();

    } catch (error) {
        subjectsTableBody.innerHTML = `
            <tr>
                <td colspan="6" class="empty-state">
                    ${escapeHtml(error.message)}
                </td>
            </tr>
        `;

        updateSubjectSummary();
    }
}


/* -----------------------------
   Summary
----------------------------- */

function updateSubjectSummary() {
    const total = subjectRecords.length;

    const active = subjectRecords.filter(
        subject => subject.is_active
    ).length;

    const inactive = total - active;

    totalSubjectCount.textContent = String(total);
    activeSubjectCount.textContent = String(active);
    inactiveSubjectCount.textContent = String(inactive);
}


/* -----------------------------
   Filtering
----------------------------- */

function getFilteredSubjects() {
    const searchTerm = subjectSearch.value
        .trim()
        .toLowerCase();

    const status = subjectStatusFilter.value;

    return subjectRecords.filter(subject => {

        const matchesSearch =
            !searchTerm ||
            String(subject.name || "")
                .toLowerCase()
                .includes(searchTerm) ||
            String(subject.code || "")
                .toLowerCase()
                .includes(searchTerm) ||
            String(subject.category || "")
                .toLowerCase()
                .includes(searchTerm);

        const matchesStatus =
            status === "all" ||
            (status === "active" && subject.is_active) ||
            (status === "inactive" && !subject.is_active);

        return matchesSearch && matchesStatus;
    });
}


/* -----------------------------
   Render
----------------------------- */

function renderSubjects() {
    const filteredSubjects = getFilteredSubjects();

    if (!filteredSubjects.length) {
        subjectsTableBody.innerHTML = `
            <tr>
                <td colspan="6" class="empty-state">
                    No subjects found.
                </td>
            </tr>
        `;
        return;
    }

    subjectsTableBody.innerHTML = filteredSubjects
        .map(subject => {

            const statusClass = subject.is_active
                ? "active"
                : "inactive";

            const statusText = subject.is_active
                ? "Active"
                : "Inactive";

            const toggleText = subject.is_active
                ? "Deactivate"
                : "Activate";

            const toggleClass = subject.is_active
                ? "danger"
                : "";

            const code = subject.code
                ? escapeHtml(subject.code)
                : "—";

            const category = subject.category
                ? escapeHtml(subject.category)
                : "—";

            return `
                <tr>

                    <td>
                        <div class="subject-name">
                            ${escapeHtml(subject.name)}
                        </div>
                    </td>

                    <td>
                        <span class="subject-code">
                            ${code}
                        </span>
                    </td>

                    <td>
                        <span class="category-text">
                            ${category}
                        </span>
                    </td>

                    <td>
                        <div class="assessment-values">
                            <span class="assessment-pill">
                                CA1 <strong>${formatNumber(subject.max_ca1)}</strong>
                            </span>

                            <span class="assessment-pill">
                                CA2 <strong>${formatNumber(subject.max_ca2)}</strong>
                            </span>

                            <span class="assessment-pill">
                                Exam <strong>${formatNumber(subject.max_exam)}</strong>
                            </span>
                        </div>
                    </td>

                    <td>
                        <span class="status-badge ${statusClass}">
                            ${statusText}
                        </span>
                    </td>

                    <td>
                        <div class="action-buttons">

                            <button
                                type="button"
                                class="table-action"
                                data-action="edit"
                                data-id="${subject.id}"
                            >
                                Edit
                            </button>

                            <button
                                type="button"
                                class="table-action ${toggleClass}"
                                data-action="toggle"
                                data-id="${subject.id}"
                            >
                                ${toggleText}
                            </button>

                        </div>
                    </td>

                </tr>
            `;
        })
        .join("");
}


function formatNumber(value) {
    const number = Number(value);

    if (!Number.isFinite(number)) {
        return "0";
    }

    return Number.isInteger(number)
        ? String(number)
        : number.toFixed(2);
}


/* -----------------------------
   Modal
----------------------------- */

function openAddSubjectModal() {
    subjectForm.reset();

    subjectId.value = "";
    subjectCa1.value = "20";
    subjectCa2.value = "20";
    subjectExam.value = "60";

    subjectModalTitle.textContent = "Add Subject";
    saveSubjectButton.textContent = "Save Subject";

    hideFormMessage();

    subjectModal.hidden = false;
    subjectName.focus();
}


function openEditSubjectModal(subject) {
    subjectId.value = subject.id;
    subjectName.value = subject.name || "";
    subjectCode.value = subject.code || "";
    subjectCategory.value = subject.category || "";

    subjectCa1.value = subject.max_ca1 ?? 20;
    subjectCa2.value = subject.max_ca2 ?? 20;
    subjectExam.value = subject.max_exam ?? 60;

    subjectModalTitle.textContent = "Edit Subject";
    saveSubjectButton.textContent = "Update Subject";

    hideFormMessage();

    subjectModal.hidden = false;
    subjectName.focus();
}


function closeModal() {
    subjectModal.hidden = true;
    hideFormMessage();
}


/* -----------------------------
   Save subject
----------------------------- */

async function saveSubject(event) {
    event.preventDefault();

    hideFormMessage();

    const name = subjectName.value.trim();
    const code = subjectCode.value.trim();
    const category = subjectCategory.value.trim();

    const maxCa1 = Number(subjectCa1.value);
    const maxCa2 = Number(subjectCa2.value);
    const maxExam = Number(subjectExam.value);

    if (!name) {
        showFormMessage("Please enter the subject name.");
        subjectName.focus();
        return;
    }

    if (
        !Number.isFinite(maxCa1) ||
        !Number.isFinite(maxCa2) ||
        !Number.isFinite(maxExam) ||
        maxCa1 < 0 ||
        maxCa2 < 0 ||
        maxExam < 0
    ) {
        showFormMessage("Please enter valid assessment maximums.");
        return;
    }

    if (maxCa1 + maxCa2 + maxExam !== 100) {
        showFormMessage(
            "CA 1 + CA 2 + Exam should equal 100."
        );
        return;
    }

    const editing = Boolean(subjectId.value);

    const endpoint = editing
        ? "../api/subjects/update.php"
        : "../api/subjects/create.php";

    const payload = {
        name,
        code: code || null,
        category: category || null,
        max_ca1: maxCa1,
        max_ca2: maxCa2,
        max_exam: maxExam
    };

    if (editing) {
        payload.id = Number(subjectId.value);
    }

    saveSubjectButton.disabled = true;

    saveSubjectButton.textContent = editing
        ? "Updating..."
        : "Saving...";

    try {
        const response = await fetch(endpoint, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message ||
                "Unable to save subject."
            );
        }

        closeModal();

        await loadSubjects();

    } catch (error) {
        showFormMessage(error.message);
    } finally {
        saveSubjectButton.disabled = false;

        saveSubjectButton.textContent = editing
            ? "Update Subject"
            : "Save Subject";
    }
}


/* -----------------------------
   Toggle status
----------------------------- */

async function toggleSubject(id) {
    const subject = subjectRecords.find(
        item => Number(item.id) === Number(id)
    );

    if (!subject) {
        return;
    }

    const action = subject.is_active
        ? "deactivate"
        : "activate";

    const confirmation = subject.is_active
        ? `Deactivate "${subject.name}"?`
        : `Activate "${subject.name}"?`;

    if (!window.confirm(confirmation)) {
        return;
    }

    try {
        const response = await fetch(
            "../api/subjects/toggle-status.php",
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    id: Number(id),
                    action
                })
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message ||
                "Unable to change subject status."
            );
        }

        await loadSubjects();

    } catch (error) {
        alert(error.message);
    }
}


/* -----------------------------
   Events
----------------------------- */

addSubjectButton.addEventListener(
    "click",
    openAddSubjectModal
);

closeSubjectModal.addEventListener(
    "click",
    closeModal
);

cancelSubjectButton.addEventListener(
    "click",
    closeModal
);

subjectForm.addEventListener(
    "submit",
    saveSubject
);

subjectSearch.addEventListener(
    "input",
    renderSubjects
);

subjectStatusFilter.addEventListener(
    "change",
    renderSubjects
);

subjectsTableBody.addEventListener(
    "click",
    event => {

        const button = event.target.closest(
            "button[data-action]"
        );

        if (!button) {
            return;
        }

        const id = Number(button.dataset.id);
        const action = button.dataset.action;

        if (action === "edit") {

            const subject = subjectRecords.find(
                item => Number(item.id) === id
            );

            if (subject) {
                openEditSubjectModal(subject);
            }

            return;
        }

        if (action === "toggle") {
            toggleSubject(id);
        }
    }
);

subjectModal.addEventListener(
    "click",
    event => {
        if (event.target === subjectModal) {
            closeModal();
        }
    }
);

document.addEventListener(
    "keydown",
    event => {
        if (
            event.key === "Escape" &&
            !subjectModal.hidden
        ) {
            closeModal();
        }
    }
);


/* -----------------------------
   Initial load
----------------------------- */

loadSubjects();
