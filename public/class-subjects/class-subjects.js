const params = new URLSearchParams(window.location.search);
const classId = Number(params.get("class_id"));

const className = document.getElementById("className");
const classDescription = document.getElementById("classDescription");
const summaryClassName = document.getElementById("summaryClassName");

const assignedCount = document.getElementById("assignedCount");
const availableCount = document.getElementById("availableCount");

const assignedSubjects = document.getElementById("assignedSubjects");
const availableSubjects = document.getElementById("availableSubjects");

const subjectSearch = document.getElementById("subjectSearch");
const saveButton = document.getElementById("saveButton");
const cancelButton = document.getElementById("cancelButton");

let assigned = [];
let available = [];
let currentClass = null;

document.addEventListener("DOMContentLoaded", init);

async function init() {
    if (!classId || classId < 1) {
        showPageError("A valid class was not selected.");
        return;
    }

    await loadClass();
    // Load assigned subjects first: this endpoint synchronizes the SS curriculum.
    // The available list must run afterwards so it cannot race that synchronization.
    await loadAssignedSubjects();
    await loadAvailableSubjects();

    renderAll();
}

async function loadClass() {
    try {
        const response = await fetch(
            `../api/classes/list.php?id=${encodeURIComponent(classId)}`,
            {
                credentials: "same-origin"
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to load class.");
        }

        const classes = Array.isArray(data.classes)
            ? data.classes
            : [];

        currentClass = classes.find(
            item => Number(item.id) === classId
        );

        if (!currentClass) {
            throw new Error("Class not found.");
        }

        updateClassHeader();

    } catch (error) {
        showPageError(error.message);
    }
}

async function loadAssignedSubjects() {
    try {
        const response = await fetch(
            `../api/class-subjects/list.php?class_id=${encodeURIComponent(classId)}`,
            {
                credentials: "same-origin"
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "Unable to load assigned subjects."
            );
        }

        assigned = Array.isArray(data.subjects)
            ? data.subjects
            : [];

        assigned.sort(
            (a, b) => Number(a.sort_order) - Number(b.sort_order)
        );

    } catch (error) {
        assigned = [];
        assignedSubjects.innerHTML = `
            <div class="empty-state">
                ${escapeHtml(error.message)}
            </div>
        `;
    }
}

async function loadAvailableSubjects() {
    try {
        const response = await fetch(
            `../api/class-subjects/available.php?class_id=${encodeURIComponent(classId)}`,
            {
                credentials: "same-origin"
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "Unable to load subjects."
            );
        }

        available = Array.isArray(data.subjects)
            ? data.subjects
            : [];

    } catch (error) {
        available = [];
        availableSubjects.innerHTML = `
            <div class="empty-state">
                ${escapeHtml(error.message)}
            </div>
        `;
    }
}

function updateClassHeader() {
    const name = currentClass?.name || "Class";

    className.textContent = name;
    summaryClassName.textContent = name;

    classDescription.textContent =
        `Manage the subjects assigned to ${name}.`;
}

function renderAll() {
    renderAssignedSubjects();
    renderAvailableSubjects();
    updateCounts();
}

function renderAssignedSubjects() {
    if (!assigned.length) {
        assignedSubjects.innerHTML = `
            <div class="empty-state">
                No subjects have been assigned to this class yet.
            </div>
        `;
        return;
    }

    assignedSubjects.innerHTML = assigned
        .map((subject, index) => {
            const subjectId = Number(subject.subject_id);

            return `
                <div class="subject-row" data-subject-id="${subjectId}">

                    <div class="subject-order">
                        ${index + 1}
                    </div>

                    <div class="subject-info">
                        <p class="subject-name">
                            ${escapeHtml(subject.name)}
                        </p>

                        <div class="subject-meta">
                            ${subject.code
                                ? `<span>${escapeHtml(subject.code)}</span>`
                                : ""
                            }

                            ${subject.category
                                ? `<span>${escapeHtml(subject.category)}</span>`
                                : ""
                            }
                        </div>

                        <label class="required-control">
                            <input
                                type="checkbox"
                                class="required-checkbox"
                                data-subject-id="${subjectId}"
                                ${subject.is_required ? "checked" : ""}
                            >
                            Required subject
                        </label>
                    </div>

                    <div class="subject-actions">

                        <button
                            type="button"
                            class="icon-btn"
                            title="Move up"
                            data-action="up"
                            data-subject-id="${subjectId}"
                            ${index === 0 ? "disabled" : ""}
                        >
                            ↑
                        </button>

                        <button
                            type="button"
                            class="icon-btn"
                            title="Move down"
                            data-action="down"
                            data-subject-id="${subjectId}"
                            ${index === assigned.length - 1 ? "disabled" : ""}
                        >
                            ↓
                        </button>

                        <button
                            type="button"
                            class="icon-btn danger"
                            title="Remove subject"
                            data-action="remove"
                            data-subject-id="${subjectId}"
                        >
                            ×
                        </button>

                    </div>

                </div>
            `;
        })
        .join("");

    assignedSubjects
        .querySelectorAll(".required-checkbox")
        .forEach(checkbox => {
            checkbox.addEventListener("change", () => {
                const id = Number(checkbox.dataset.subjectId);

                const subject = assigned.find(
                    item => Number(item.subject_id) === id
                );

                if (subject) {
                    subject.is_required = checkbox.checked;
                }
            });
        });

    assignedSubjects
        .querySelectorAll("[data-action]")
        .forEach(button => {
            button.addEventListener("click", () => {
                const id = Number(button.dataset.subjectId);
                const action = button.dataset.action;

                handleAssignedAction(action, id);
            });
        });
}

function renderAvailableSubjects() {
    const query = subjectSearch.value.trim().toLowerCase();

    const assignedIds = new Set(
        assigned.map(subject => Number(subject.subject_id))
    );

    const filtered = available.filter(subject => {
        const id = Number(subject.id);

        if (assignedIds.has(id)) {
            return false;
        }

        if (!query) {
            return true;
        }

        const searchable = [
            subject.name,
            subject.code,
            subject.category
        ]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        return searchable.includes(query);
    });

    availableCount.textContent = filtered.length;

    if (!filtered.length) {
        availableSubjects.innerHTML = `
            <div class="empty-state">
                ${
                    query
                        ? "No matching subjects found."
                        : "No additional active subjects are available."
                }
            </div>
        `;
        return;
    }

    availableSubjects.innerHTML = filtered
        .map(subject => {
            return `
                <div class="subject-row">

                    <div class="subject-order">
                        +
                    </div>

                    <div class="subject-info">
                        <p class="subject-name">
                            ${escapeHtml(subject.name)}
                        </p>

                        <div class="subject-meta">
                            ${subject.code
                                ? `<span>${escapeHtml(subject.code)}</span>`
                                : ""
                            }

                            ${subject.category
                                ? `<span>${escapeHtml(subject.category)}</span>`
                                : ""
                            }
                        </div>
                    </div>

                    <div class="subject-actions">
                        <button
                            type="button"
                            class="add-subject-btn"
                            data-add-subject="${Number(subject.id)}"
                        >
                            Add
                        </button>
                    </div>

                </div>
            `;
        })
        .join("");

    availableSubjects
        .querySelectorAll("[data-add-subject]")
        .forEach(button => {
            button.addEventListener("click", () => {
                addSubject(
                    Number(button.dataset.addSubject)
                );
            });
        });
}

function handleAssignedAction(action, subjectId) {
    const index = assigned.findIndex(
        subject => Number(subject.subject_id) === subjectId
    );

    if (index === -1) {
        return;
    }

    if (action === "remove") {
        assigned.splice(index, 1);
    }

    if (action === "up" && index > 0) {
        [
            assigned[index - 1],
            assigned[index]
        ] = [
            assigned[index],
            assigned[index - 1]
        ];
    }

    if (
        action === "down" &&
        index < assigned.length - 1
    ) {
        [
            assigned[index],
            assigned[index + 1]
        ] = [
            assigned[index + 1],
            assigned[index]
        ];
    }

    normalizeOrder();
    renderAll();
}

function addSubject(subjectId) {
    const subject = available.find(
        item => Number(item.id) === subjectId
    );

    if (!subject) {
        return;
    }

    if (
        assigned.some(
            item => Number(item.subject_id) === subjectId
        )
    ) {
        return;
    }

    assigned.push({
        subject_id: Number(subject.id),
        name: subject.name,
        code: subject.code,
        category: subject.category,
        max_ca1: subject.max_ca1,
        max_ca2: subject.max_ca2,
        max_exam: subject.max_exam,
        is_required: true,
        sort_order: assigned.length + 1
    });

    normalizeOrder();
    renderAll();
}

function normalizeOrder() {
    assigned.forEach((subject, index) => {
        subject.sort_order = index + 1;
    });
}

async function saveSubjects() {
    saveButton.disabled = true;
    saveButton.textContent = "Saving...";

    try {
        normalizeOrder();

        const response = await fetch(
            "../api/class-subjects/save.php",
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    class_id: classId,
                    subjects: assigned.map(subject => ({
                        subject_id: Number(subject.subject_id),
                        is_required: Boolean(subject.is_required)
                    }))
                })
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "Unable to save subjects."
            );
        }

        alert("Class subjects saved successfully.");

        await loadAssignedSubjects();
        renderAll();

    } catch (error) {
        alert(error.message);
    } finally {
        saveButton.disabled = false;
        saveButton.textContent = "Save Subjects";
    }
}

function showPageError(message) {
    assignedSubjects.innerHTML = `
        <div class="empty-state">
            ${escapeHtml(message)}
        </div>
    `;

    availableSubjects.innerHTML = `
        <div class="empty-state">
            ${escapeHtml(message)}
        </div>
    `;
}

function updateCounts() {
    assignedCount.textContent = assigned.length;
}

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

subjectSearch.addEventListener("input", renderAvailableSubjects);

saveButton.addEventListener("click", saveSubjects);

cancelButton.addEventListener("click", () => {
    window.history.back();
});
