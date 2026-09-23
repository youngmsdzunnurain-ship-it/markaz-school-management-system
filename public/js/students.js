const studentsTableBody = document.getElementById("studentsTableBody");
const studentCount = document.getElementById("studentCount");
const studentSearch = document.getElementById("studentSearch");
const statusFilter = document.getElementById("statusFilter");

let students = [];

async function loadStudents() {
    try {
        const response = await fetch("./api/students/list.php", {
            credentials: "same-origin"
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to load students.");
        }

        students = data.students || [];
        renderStudents();

    } catch (error) {
        studentsTableBody.innerHTML = `
            <tr>
                <td colspan="6" class="empty-state">
                    ${escapeHtml(error.message)}
                </td>
            </tr>
        `;

        studentCount.textContent = "0 Students";
    }
}

function renderStudents() {
    const searchTerm = studentSearch.value.trim().toLowerCase();
    const status = statusFilter.value;

    const filtered = students.filter(student => {

        const fullName = [
            student.first_name,
            student.middle_name,
            student.last_name
        ]
        .filter(Boolean)
        .join(" ")
        .toLowerCase();

        const matchesSearch =
            !searchTerm ||
            fullName.includes(searchTerm) ||
            String(student.admission_number)
                .toLowerCase()
                .includes(searchTerm);

        const matchesStatus =
            status === "" ||
            String(student.is_active) === status;

        return matchesSearch && matchesStatus;
    });

    studentCount.textContent =
        `${filtered.length} ${filtered.length === 1 ? "Student" : "Students"}`;

    if (!filtered.length) {
        studentsTableBody.innerHTML = `
            <tr>
                <td colspan="6" class="empty-state">
                    No students found.
                </td>
            </tr>
        `;
        return;
    }

    studentsTableBody.innerHTML = filtered.map(student => {

        const fullName = [
            student.first_name,
            student.middle_name,
            student.last_name
        ]
        .filter(Boolean)
        .join(" ");

        const statusLabel =
            Number(student.is_active) === 1 ? "Active" : "Inactive";

        return `
            <tr>
                <td>
                    <strong>${escapeHtml(fullName)}</strong>
                </td>

                <td>
                    ${escapeHtml(student.admission_number)}
                </td>

                <td>
                    ${escapeHtml(student.class_name || "—")}
                </td>

                <td>
                    ${escapeHtml(student.gender || "—")}
                </td>

                <td>
                    <span class="student-status ${statusLabel.toLowerCase()}">
                        ${statusLabel}
                    </span>
                </td>

                <td>
                    <button
                        type="button"
                        class="student-action-button"
                        data-student-id="${student.id}"
                    >
                        View
                    </button>
                </td>
            </tr>
        `;
    }).join("");
}

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

studentSearch.addEventListener("input", renderStudents);
statusFilter.addEventListener("change", renderStudents);

loadStudents();

const addStudentButton = document.getElementById("addStudentButton");
const addStudentModal = document.getElementById("addStudentModal");
const closeStudentModal = document.getElementById("closeStudentModal");
const cancelStudentButton = document.getElementById("cancelStudentButton");
const addStudentForm = document.getElementById("addStudentForm");
const studentPhoto = document.getElementById("studentPhoto");
const studentPhotoPreview = document.getElementById("studentPhotoPreview");

function openStudentModal() {
    addStudentModal.hidden = false;
    document.body.style.overflow = "hidden";
}

function closeStudentModalWindow() {
    addStudentModal.hidden = true;
    document.body.style.overflow = "";
}

addStudentButton.addEventListener("click", openStudentModal);

closeStudentModal.addEventListener("click", closeStudentModalWindow);

cancelStudentButton.addEventListener("click", closeStudentModalWindow);

addStudentModal.addEventListener("click", (event) => {
    if (event.target === addStudentModal) {
        closeStudentModalWindow();
    }
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !addStudentModal.hidden) {
        closeStudentModalWindow();
    }
});

studentPhoto.addEventListener("change", () => {

    const file = studentPhoto.files[0];

    if (!file) {
        studentPhotoPreview.style.backgroundImage = "";
        studentPhotoPreview.innerHTML = "<span>ST</span>";
        return;
    }

    if (file.size > 3 * 1024 * 1024) {
        alert("Student photo must not exceed 3MB.");
        studentPhoto.value = "";
        return;
    }

    const reader = new FileReader();

    reader.onload = (event) => {
        studentPhotoPreview.style.backgroundImage =
            `url("${event.target.result}")`;

        studentPhotoPreview.innerHTML = "";
    };

    reader.readAsDataURL(file);
});

addStudentForm.addEventListener("submit", (event) => {
    event.preventDefault();

    alert("Student form is ready. Database saving will be connected next.");
});


const studentClassSelect = document.getElementById("studentClass");

async function loadClasses() {
    try {
        const response = await fetch("./api/classes/list.php", {
            credentials: "same-origin"
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to load classes.");
        }

        studentClassSelect.innerHTML = `
            <option value="">Select class</option>
        `;

        (data.classes || []).forEach(classItem => {
            const option = document.createElement("option");

            option.value = classItem.id;
            option.textContent = classItem.name;

            studentClassSelect.appendChild(option);
        });

    } catch (error) {
        studentClassSelect.innerHTML = `
            <option value="">Unable to load classes</option>
        `;

        console.error("Class loading error:", error);
    }
}

loadClasses();


const viewStudentModal = document.getElementById("viewStudentModal");
const closeViewStudentModal = document.getElementById("closeViewStudentModal");

function openViewStudent(studentId) {

    const student = students.find(
        item => String(item.id) === String(studentId)
    );

    if (!student) {
        return;
    }

    const fullName = [
        student.first_name,
        student.middle_name,
        student.last_name
    ]
    .filter(Boolean)
    .join(" ");

    document.getElementById("viewStudentName").textContent = fullName;
    document.getElementById("viewStudentAdmission").textContent =
        student.admission_number;

    document.getElementById("viewStudentClass").textContent =
        student.class_name || "—";

    document.getElementById("viewStudentGender").textContent =
        student.gender || "—";

    document.getElementById("viewStudentDob").textContent =
        student.date_of_birth || "—";

    document.getElementById("viewStudentAdmissionDate").textContent =
        student.admission_date || "—";

    const statusElement = document.getElementById("viewStudentStatus");
    const photoElement = document.getElementById("viewStudentPhoto");

    const isActive = Number(student.is_active) === 1;

    statusElement.textContent = isActive ? "Active" : "Inactive";
    statusElement.className =
        `student-status ${isActive ? "active" : "inactive"}`;

    if (student.profile_image) {
        photoElement.style.backgroundImage =
            `url("./${student.profile_image}")`;
        photoElement.innerHTML = "";
    } else {
        photoElement.style.backgroundImage = "";
        photoElement.innerHTML = "<span>ST</span>";
    }

    viewStudentModal.hidden = false;
    document.body.style.overflow = "hidden";
}

function closeViewStudentWindow() {
    viewStudentModal.hidden = true;
    document.body.style.overflow = "";
}

studentsTableBody.addEventListener("click", (event) => {

    const button = event.target.closest(".student-action-button");

    if (!button) {
        return;
    }

    openViewStudent(button.dataset.studentId);
});

closeViewStudentModal.addEventListener(
    "click",
    closeViewStudentWindow
);

viewStudentModal.addEventListener("click", (event) => {

    if (event.target === viewStudentModal) {
        closeViewStudentWindow();
    }

});


const editStudentModal = document.getElementById("editStudentModal");
const closeEditStudentModal = document.getElementById("closeEditStudentModal");
const cancelEditStudent = document.getElementById("cancelEditStudent");
const editStudentForm = document.getElementById("editStudentForm");
const editStudentPhoto = document.getElementById("editStudentPhoto");
const editStudentPhotoPreview = document.getElementById("editStudentPhotoPreview");

function populateEditClasses(selectedClassId) {

    const editSelect = document.getElementById("editStudentClass");

    editSelect.innerHTML = `
        <option value="">Select class</option>
    `;

    document.querySelectorAll("#studentClass option").forEach(option => {

        if (!option.value) {
            return;
        }

        const newOption = option.cloneNode(true);

        if (String(option.value) === String(selectedClassId)) {
            newOption.selected = true;
        }

        editSelect.appendChild(newOption);
    });
}

function openEditStudent(studentId) {

    const student = students.find(
        item => String(item.id) === String(studentId)
    );

    if (!student) {
        return;
    }

    document.getElementById("editStudentId").value = student.id;
    document.getElementById("editAdmissionNumber").value =
        student.admission_number || "";

    document.getElementById("editFirstName").value =
        student.first_name || "";

    document.getElementById("editMiddleName").value =
        student.middle_name || "";

    document.getElementById("editLastName").value =
        student.last_name || "";

    document.getElementById("editGender").value =
        student.gender || "";

    document.getElementById("editDateOfBirth").value =
        student.date_of_birth || "";

    document.getElementById("editAdmissionDate").value =
        student.admission_date || "";

    document.getElementById("editStudentStatus").value =
        String(student.is_active);

    populateEditClasses(student.class_id);

    editStudentPhoto.value = "";

    if (student.profile_image) {
        editStudentPhotoPreview.style.backgroundImage =
            `url("./${student.profile_image}")`;

        editStudentPhotoPreview.innerHTML = "";
    } else {
        editStudentPhotoPreview.style.backgroundImage = "";
        editStudentPhotoPreview.innerHTML = "<span>ST</span>";
    }

    editStudentModal.hidden = false;
    document.body.style.overflow = "hidden";
}

function closeEditStudentWindow() {
    editStudentModal.hidden = true;
    document.body.style.overflow = "";
}

studentsTableBody.addEventListener("click", (event) => {

    const editButton = event.target.closest(".edit-student-button");

    if (!editButton) {
        return;
    }

    openEditStudent(editButton.dataset.studentId);
});

closeEditStudentModal.addEventListener(
    "click",
    closeEditStudentWindow
);

cancelEditStudent.addEventListener(
    "click",
    closeEditStudentWindow
);

editStudentModal.addEventListener("click", (event) => {

    if (event.target === editStudentModal) {
        closeEditStudentWindow();
    }

});

editStudentPhoto.addEventListener("change", () => {

    const file = editStudentPhoto.files[0];

    if (!file) {
        return;
    }

    if (file.size > 3 * 1024 * 1024) {
        alert("Student photo must not exceed 3MB.");
        editStudentPhoto.value = "";
        return;
    }

    const reader = new FileReader();

    reader.onload = (event) => {

        editStudentPhotoPreview.style.backgroundImage =
            `url("${event.target.result}")`;

        editStudentPhotoPreview.innerHTML = "";
    };

    reader.readAsDataURL(file);
});

editStudentForm.addEventListener("submit", async (event) => {

    event.preventDefault();

    const button = document.getElementById("updateStudentButton");
    const formData = new FormData(editStudentForm);

    button.disabled = true;
    button.textContent = "Saving...";

    try {

        const response = await fetch("./api/students/update.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "Unable to update student."
            );
        }

        alert(data.message);

        closeEditStudentWindow();

        await loadStudents();

    } catch (error) {

        alert(error.message);

    } finally {

        button.disabled = false;
        button.textContent = "Save Changes";

    }

});


async function toggleStudentStatus(studentId) {

    const student = students.find(
        item => String(item.id) === String(studentId)
    );

    if (!student) {
        return;
    }

    const currentlyActive = Number(student.is_active) === 1;

    const action = currentlyActive
        ? "deactivate"
        : "activate";

    const confirmed = confirm(
        `Are you sure you want to ${action} ${[
            student.first_name,
            student.middle_name,
            student.last_name
        ].filter(Boolean).join(" ")}?`
    );

    if (!confirmed) {
        return;
    }

    const formData = new FormData();

    formData.append("id", studentId);

    try {

        const response = await fetch(
            "./api/students/toggle-status.php",
            {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "Unable to change student status."
            );
        }

        await loadStudents();

    } catch (error) {

        alert(error.message);

    }
}

studentsTableBody.addEventListener("click", (event) => {

    const button = event.target.closest(".toggle-status-button");

    if (!button) {
        return;
    }

    toggleStudentStatus(button.dataset.studentId);

});


async function loadStudentClassFilter() {

    const classFilter = document.getElementById("classFilter");

    try {

        const response = await fetch("./api/classes/list.php", {
            credentials: "same-origin"
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to load classes.");
        }

        classFilter.innerHTML = `
            <option value="">All Classes</option>
        `;

        (data.classes || []).forEach(classItem => {

            const option = document.createElement("option");

            option.value = classItem.id;
            option.textContent = classItem.name;

            classFilter.appendChild(option);

        });

    } catch (error) {

        console.error("Class filter error:", error);

    }
}

loadStudentClassFilter();


/* ================================
   STUDENT SUMMARY COUNTS
================================ */

function updateStudentSummary() {

    const totalStudentCount =
        document.getElementById("totalStudentCount");

    const activeStudentCount =
        document.getElementById("activeStudentCount");

    const inactiveStudentCount =
        document.getElementById("inactiveStudentCount");

    if (!totalStudentCount ||
        !activeStudentCount ||
        !inactiveStudentCount) {
        return;
    }

    const total = students.length;

    const active = students.filter(
        student => Number(student.is_active) === 1
    ).length;

    const inactive = students.filter(
        student => Number(student.is_active) === 0
    ).length;

    totalStudentCount.textContent = total;
    activeStudentCount.textContent = active;
    inactiveStudentCount.textContent = inactive;
}

