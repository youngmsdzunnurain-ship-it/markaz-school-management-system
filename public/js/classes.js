const classesTableBody = document.getElementById("classesTableBody");
const classCount = document.getElementById("classCount");
const classSearch = document.getElementById("classSearch");
const classStatusFilter = document.getElementById("classStatusFilter");

const classModal = document.getElementById("classModal");
const classForm = document.getElementById("classForm");

let classRecords = [];


/* ================================
   LOAD CLASSES
================================ */

async function loadClasses() {
    try {
        const response = await fetch("./api/classes/list.php", {
            credentials: "same-origin"
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "Unable to load classes."
            );
        }

        classRecords = data.classes || [];

        renderClasses();

    } catch (error) {

        classesTableBody.innerHTML = `
            <tr>
                <td colspan="5" class="empty-state">
                    ${escapeHtml(error.message)}
                </td>
            </tr>
        `;

        classCount.textContent = "0 Classes";
    }
}


/* ================================
   RENDER CLASSES
================================ */

function renderClasses() {

    const searchTerm =
        classSearch.value.trim().toLowerCase();

    const status =
        classStatusFilter.value;

    const filtered =
        classRecords.filter(classItem => {

            const name =
                String(classItem.name || "")
                    .toLowerCase();

            const group =
                String(classItem.level_group || "")
                    .toLowerCase();

            const matchesSearch =
                !searchTerm ||
                name.includes(searchTerm) ||
                group.includes(searchTerm);

            const matchesStatus =
                status === "" ||
                String(classItem.is_active) === status;

            return matchesSearch && matchesStatus;
        });


    classCount.textContent =
        `${filtered.length} ${
            filtered.length === 1
                ? "Class"
                : "Classes"
        }`;


    if (!filtered.length) {

        classesTableBody.innerHTML = `
            <tr>
                <td colspan="5" class="empty-state">
                    No classes found.
                </td>
            </tr>
        `;

        return;
    }


    classesTableBody.innerHTML =
        filtered.map(classItem => {

            const active =
                Number(classItem.is_active) === 1;

            return `
                <tr>

                    <td>
                        <span class="class-name">
                            ${escapeHtml(classItem.name)}
                        </span>
                    </td>

                    <td>
                        <span class="class-group">
                            ${escapeHtml(
                                classItem.level_group || "—"
                            )}
                        </span>
                    </td>

                    <td>
                        ${escapeHtml(classItem.sort_order)}
                    </td>

                    <td>
                        <span class="class-status ${
                            active ? "active" : "inactive"
                        }">
                            ${active ? "Active" : "Inactive"}
                        </span>
                    </td>

                    <td>
                        <div class="class-actions">

                            <button
                                type="button"
                                class="class-action-button edit-class-button"
                                data-class-id="${classItem.id}">
                                Edit
                            </button>

                            <button
                                type="button"
                                class="class-action-button class-toggle-button"
                                data-class-id="${classItem.id}">
                                ${active ? "Deactivate" : "Activate"}
                            </button>

                        </div>
                    </td>

                </tr>
            `;

        }).join("");
}


/* ================================
   OPEN CLASS MODAL
================================ */

function openClassModal(classItem = null) {

    classForm.reset();

    document.getElementById("classId").value =
        classItem?.id || "";

    document.getElementById("className").value =
        classItem?.name || "";

    document.getElementById("classLevelGroup").value =
        classItem?.level_group || "";

    document.getElementById("classSortOrder").value =
        classItem?.sort_order ?? 0;

    document.getElementById("classStatus").value =
        String(classItem?.is_active ?? 1);


    const editing = Boolean(classItem);

    document.getElementById("classModalTitle").textContent =
        editing ? "Edit Class" : "Add Class";

    document.getElementById("classModalDescription").textContent =
        editing
            ? "Update the class information."
            : "Create a new academic class.";


    classModal.hidden = false;

    document.body.style.overflow = "hidden";

    document.getElementById("className").focus();
}


/* ================================
   CLOSE MODAL
================================ */

function closeClassModalWindow() {

    classModal.hidden = true;

    document.body.style.overflow = "";
}


document
    .getElementById("addClassButton")
    .addEventListener("click", () => {
        openClassModal();
    });


document
    .getElementById("closeClassModal")
    .addEventListener("click", closeClassModalWindow);


document
    .getElementById("cancelClassButton")
    .addEventListener("click", closeClassModalWindow);


classModal.addEventListener("click", event => {

    if (event.target === classModal) {
        closeClassModalWindow();
    }

});


document.addEventListener("keydown", event => {

    if (
        event.key === "Escape" &&
        !classModal.hidden
    ) {
        closeClassModalWindow();
    }

});


/* ================================
   SAVE CLASS
================================ */

classForm.addEventListener("submit", async event => {

    event.preventDefault();

    const saveButton =
        document.getElementById("saveClassButton");

    const id =
        document.getElementById("classId").value;

    const formData =
        new FormData(classForm);

    const endpoint =
        id
            ? "./api/classes/update.php"
            : "./api/classes/create.php";


    saveButton.disabled = true;

    saveButton.textContent =
        id ? "Saving..." : "Creating...";


    try {

        const response =
            await fetch(endpoint, {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            });


        const data =
            await response.json();


        if (!response.ok || !data.success) {
            throw new Error(
                data.message ||
                "Unable to save class."
            );
        }


        closeClassModalWindow();

        await loadClasses();


    } catch (error) {

        alert(error.message);


    } finally {

        saveButton.disabled = false;

        saveButton.textContent =
            id ? "Save Changes" : "Save Class";
    }

});


/* ================================
   TABLE ACTIONS
================================ */

classesTableBody.addEventListener("click", event => {

    const editButton =
        event.target.closest(".edit-class-button");

    const toggleButton =
        event.target.closest(".class-toggle-button");


    if (editButton) {

        const classItem =
            classRecords.find(item =>
                String(item.id) ===
                String(editButton.dataset.classId)
            );


        if (classItem) {
            openClassModal(classItem);
        }

        return;
    }


    if (toggleButton) {

        toggleClassStatus(
            toggleButton.dataset.classId
        );
    }

});


/* ================================
   TOGGLE STATUS
================================ */

async function toggleClassStatus(classId) {

    const classItem =
        classRecords.find(item =>
            String(item.id) ===
            String(classId)
        );


    if (!classItem) {
        return;
    }


    const active =
        Number(classItem.is_active) === 1;

    const action =
        active ? "deactivate" : "activate";


    const confirmed =
        confirm(
            `Are you sure you want to ${action} ${classItem.name}?`
        );


    if (!confirmed) {
        return;
    }


    const formData =
        new FormData();

    formData.append("id", classId);


    try {

        const response =
            await fetch(
                "./api/classes/toggle-status.php",
                {
                    method: "POST",
                    body: formData,
                    credentials: "same-origin"
                }
            );


        const data =
            await response.json();


        if (!response.ok || !data.success) {
            throw new Error(
                data.message ||
                "Unable to change class status."
            );
        }


        await loadClasses();


    } catch (error) {

        alert(error.message);
    }

}


/* ================================
   FILTERS
================================ */

classSearch.addEventListener(
    "input",
    renderClasses
);


classStatusFilter.addEventListener(
    "change",
    renderClasses
);


/* ================================
   HTML ESCAPE
================================ */

function escapeHtml(value) {

    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}


/* ================================
   START
================================ */

loadClasses();
