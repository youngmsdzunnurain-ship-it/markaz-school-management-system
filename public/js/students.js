const studentsTableBody = document.getElementById("studentsTableBody");
const studentCount = document.getElementById("studentCount");
const totalStudentCount = document.getElementById("totalStudentCount");
const activeStudentCount = document.getElementById("activeStudentCount");
const inactiveStudentCount = document.getElementById("inactiveStudentCount");
const studentSearch = document.getElementById("studentSearch");
const statusFilter = document.getElementById("statusFilter");
const classFilter = document.getElementById("classFilter");


const addStudentButton = document.getElementById("addStudentButton");
const addStudentModal = document.getElementById("addStudentModal");
const closeStudentModal = document.getElementById("closeStudentModal");
const cancelStudentButton = document.getElementById("cancelStudentButton");
const addStudentForm = document.getElementById("addStudentForm");

const studentPhoto = document.getElementById("studentPhoto");
const studentPhotoPreview = document.getElementById("studentPhotoPreview");

const studentClassSelect = document.getElementById("studentClass");

const viewStudentModal = document.getElementById("viewStudentModal");
const closeViewStudentModal = document.getElementById("closeViewStudentModal");

const editStudentModal = document.getElementById("editStudentModal");
const closeEditStudentModal = document.getElementById("closeEditStudentModal");
const cancelEditStudent = document.getElementById("cancelEditStudent");
const editStudentForm = document.getElementById("editStudentForm");
const editStudentPhoto = document.getElementById("editStudentPhoto");
const editStudentPhotoPreview = document.getElementById("editStudentPhotoPreview");
const removeStudentPhotoButton = document.getElementById("removeStudentPhotoButton");
const editStudentPhotoButton = document.getElementById("editStudentPhotoButton");

const saveStudentButton = document.getElementById("saveStudentButton");
const updateStudentButton = document.getElementById("updateStudentButton");

let removeStudentPhoto = false;

function setStudentSaveButtonState(button, state, defaultText) {
    if (!button) return;

    if (state === "saving") {
        button.disabled = true;
        button.textContent = "Saving...";
        return;
    }

    if (state === "saved") {
        button.disabled = true;
        button.textContent = "Saved ✓";

        setTimeout(() => {
            button.disabled = false;
            button.textContent = defaultText;
        }, 1200);

        return;
    }

    button.disabled = false;
    button.textContent = defaultText;
}

let students = [];
let classes = [];
let arabicClasses = [];

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

async function loadStudents() {
    try {
        const response = await fetch("./api/students/list.php", {
            credentials: "same-origin"
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "Unable to load students."
            );
        }

        students = data.students || [];

        updateStudentSummary();
        renderStudents();

    } catch (error) {
                setStudentSaveButtonState(
                    saveStudentButton,
                    "idle",
                    "Save Student"
                );

        console.error("Student loading error:", error);

        studentsTableBody.innerHTML = `
            <tr>
                <td colspan="11" class="empty-state">
                    ${escapeHtml(error.message)}
                </td>
            </tr>
        `;

        if (totalStudentCount) {
            totalStudentCount.textContent = "0";
        }

        if (activeStudentCount) {
            activeStudentCount.textContent = "0";
        }

        if (inactiveStudentCount) {
            inactiveStudentCount.textContent = "0";
        }

        studentCount.textContent = "0 Students";
    }
}

function updateStudentSummary() {
    const totalStudents = students.length;

    const activeStudents = students.filter(
        student => Number(student.is_active) === 1
    ).length;

    const inactiveStudents = totalStudents - activeStudents;

    if (totalStudentCount) {
        totalStudentCount.textContent = totalStudents;
    }

    if (activeStudentCount) {
        activeStudentCount.textContent = activeStudents;
    }

    if (inactiveStudentCount) {
        inactiveStudentCount.textContent = inactiveStudents;
    }
}

function renderStudents() {
    const searchTerm = studentSearch
        ? studentSearch.value.trim().toLowerCase()
        : "";

    const status = statusFilter
        ? statusFilter.value
        : "";

    const selectedClassFilter = classFilter
        ? classFilter.value
        : "";

    const filtered = students.filter(student => {
        const fullName = [
            student.first_name,
            student.middle_name,
            student.last_name
        ]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        const admissionNumber = String(
            student.admission_number || ""
        ).toLowerCase();

        const matchesSearch =
            !searchTerm ||
            fullName.includes(searchTerm) ||
            admissionNumber.includes(searchTerm);

        const matchesStatus =
            status === "" ||
            String(student.is_active) === status;

        let matchesClass = true;

        if (selectedClassFilter.startsWith("class:")) {
            const classId = selectedClassFilter.slice(6);
            matchesClass =
                String(student.class_id) === classId;
        } else if (selectedClassFilter.startsWith("arabiyya:")) {
            const arabiyyaClassId = selectedClassFilter.slice(9);
            matchesClass =
                String(student.arabic_class_id || "") === arabiyyaClassId;
        }

        return (
            matchesSearch &&
            matchesClass &&
            matchesStatus
        );
    });

    studentCount.textContent =
        `${filtered.length} ${
            filtered.length === 1 ? "Student" : "Students"
        }`;

    if (!filtered.length) {
        studentsTableBody.innerHTML = `
            <tr>
                <td colspan="11" class="empty-state">
                    No students found.
                </td>
            </tr>
        `;
        return;
    }

    filtered.sort((a, b) => {
        const nameA = [
            a.first_name,
            a.middle_name,
            a.last_name
        ]
            .filter(Boolean)
            .join(" ")
            .trim();

        const nameB = [
            b.first_name,
            b.middle_name,
            b.last_name
        ]
            .filter(Boolean)
            .join(" ")
            .trim();

        return nameA.localeCompare(
            nameB,
            undefined,
            {
                sensitivity: "base",
                numeric: false
            }
        );
    });

    studentsTableBody.innerHTML = filtered.map(student => {
        const fullName = [
            student.first_name,
            student.middle_name,
            student.last_name
        ]
            .filter(Boolean)
            .join(" ");

        const parentGuardianName =
            String(
                student.parent_guardian_name || ""
            ).trim();

        const parentGuardianPhone =
            String(
                student.parent_guardian_phone || ""
            ).trim();

        const parentGuardianMarkup =
            parentGuardianName
                ? `<strong class="student-parent-name">${escapeHtml(parentGuardianName)}</strong>`
                : `<span class="student-parent-name is-empty">—</span>`;

        const parentGuardianPhoneMarkup =
            parentGuardianPhone
                ? `<a
                        class="student-parent-phone"
                        href="tel:${escapeHtml(parentGuardianPhone)}"
                    >${escapeHtml(parentGuardianPhone)}</a>`
                : `<span class="student-parent-phone is-empty">No phone number</span>`;

        const statusLabel =
            Number(student.is_active) === 1
                ? "Active"
                : "Inactive";

        let age = "—";

        if (student.date_of_birth) {
            const birthDate = new Date(
                `${student.date_of_birth}T00:00:00`
            );
            const today = new Date();

            if (!Number.isNaN(birthDate.getTime())) {
                age =
                    today.getFullYear() -
                    birthDate.getFullYear();

                const birthdayThisYear = new Date(
                    today.getFullYear(),
                    birthDate.getMonth(),
                    birthDate.getDate()
                );

                if (today < birthdayThisYear) {
                    age--;
                }

                if (age < 0) {
                    age = "—";
                } else {
                    age = `${age} yrs`;
                }
            }
        }

        const initials =
            (
                (student.first_name || "").charAt(0) +
                (student.last_name || "").charAt(0)
            ).toUpperCase() || "ST";

        const studentAvatar = student.profile_image
            ? `<span class="student-avatar has-photo">
                    <img
                        src="./${escapeHtml(student.profile_image)}"
                        alt="${escapeHtml(fullName)}"
                    >
                </span>`
            : `<span class="student-avatar">
                    ${escapeHtml(initials)}
                </span>`;

        return `
            <tr>
                <td>
                    <div class="student-name-cell">
                        ${studentAvatar}
                        <strong>${escapeHtml(fullName)}</strong>
                    </div>
                </td>

                <td class="student-parent-name-cell">
                    ${parentGuardianMarkup}
                </td>

                <td class="student-parent-phone-cell">
                    ${parentGuardianPhoneMarkup}
                </td>

                <td>
                    <strong class="student-admission-number">
                        ${escapeHtml(student.admission_number)}
                    </strong>
                </td>

                <td>
                    ${escapeHtml(student.class_name || "—")}
                </td>

                <td class="student-arabiyya-class">
                    ${escapeHtml(student.arabic_class_name || "—")}
                </td>

                <td class="student-age">
                    ${escapeHtml(String(age))}
                </td>

                <td>
                    ${escapeHtml(student.gender || "—")}
                </td>
                  <td>
                      ${escapeHtml(student.student_type || "Day Student")}
                  </td>

                <td>
                    <span class="student-status ${statusLabel.toLowerCase()}">
                        ${statusLabel}
                    </span>
                </td>

                <td>
                    <div class="student-actions">
                        <button
                            type="button"
                            class="student-action-button view-student-button"
                            data-student-id="${student.id}"
                        >
                            View
                        </button>

                        <button
                            type="button"
                            class="student-action-button edit-student-button"
                            data-student-id="${student.id}"
                        >
                            Edit
                        </button>

                        <button
                            type="button"
                            class="student-action-button toggle-student-button"
                            data-student-id="${student.id}"
                        >
                            ${
                                Number(student.is_active) === 1
                                    ? "Deactivate"
                                    : "Activate"
                            }
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join("");
}

if (studentSearch) {
    studentSearch.addEventListener("input", renderStudents);
}

if (statusFilter) {
    statusFilter.addEventListener("change", renderStudents);
}

if (classFilter) {
    classFilter.addEventListener("change", renderStudents);
}



function populateClassFilter() {
    if (!classFilter) return;

    classFilter.innerHTML =
        '<option value="">All Classes</option>';

    classes.forEach(classItem => {
        if (Number(classItem.is_active) !== 1) return;

        const option = document.createElement("option");
        option.value = `class:${classItem.id}`;
        option.textContent = classItem.name;
        classFilter.appendChild(option);
    });

    arabicClasses.forEach(arabicClass => {
        if (Number(arabicClass.is_active) !== 1) return;

        const option = document.createElement("option");
        option.value = `arabiyya:${arabicClass.id}`;
        option.textContent = arabicClass.name;
        classFilter.appendChild(option);
    });
}

async function loadClasses() {
    if (!studentClassSelect && !classFilter) return;

    try {
        const response = await fetch("./api/classes/list.php", {
            credentials: "same-origin"
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to load classes.");
        }

        classes = data.classes || [];

        populateClassFilter();

        if (studentClassSelect) {
            studentClassSelect.innerHTML =
                '<option value="">Select class</option>';

            classes.forEach(classItem => {
                const option = document.createElement("option");
                option.value = classItem.id;
                option.textContent = classItem.name;

                if (Number(classItem.is_active) !== 1) {
                    option.disabled = true;
                    option.textContent += " (Inactive)";
                }

                studentClassSelect.appendChild(option);
            });
        }

        const urlParams = new URLSearchParams(window.location.search);
        const classIdFromUrl = urlParams.get("class_id");

        if (classIdFromUrl) {
            const matchingClass = classes.find(
                classItem =>
                    String(classItem.id) === String(classIdFromUrl)
            );

            if (matchingClass) {
                studentClassSelect.value = String(matchingClass.id);
            }
        }

    } catch (error) {
                setStudentSaveButtonState(
                    updateStudentButton,
                    "idle",
                    "Save Changes"
                );

        console.error("Class loading error:", error);

        studentClassSelect.innerHTML =
            '<option value="">Unable to load classes</option>';
    }
}

function ensureArabicClassField() {
    let select = document.getElementById("studentArabicClass");

    if (select) return select;

    const wrapper = document.createElement("div");
    wrapper.className = "form-field";

    wrapper.innerHTML = `
        <label for="studentArabicClass">Arabic Class</label>
        <select id="studentArabicClass" name="arabic_class_id">
            <option value="">Select Arabic class</option>
        </select>
    `;

    const classField =
        studentClassSelect?.closest(".form-field");

    if (classField) {
        classField.insertAdjacentElement("afterend", wrapper);
    } else if (addStudentForm) {
        addStudentForm.appendChild(wrapper);
    }

    return document.getElementById("studentArabicClass");
}

function ensureEditArabicClassField() {
    let select =
        document.getElementById("editStudentArabicClass");

    if (select) return select;

    const editClassSelect =
        document.getElementById("editStudentClass");

    const wrapper = document.createElement("div");
    wrapper.className = "form-field";

    wrapper.innerHTML = `
        <label for="editStudentArabicClass">
            Arabic Class
        </label>
        <select
            id="editStudentArabicClass"
            name="arabic_class_id"
        >
            <option value="">Select Arabic class</option>
        </select>
    `;

    const classField =
        editClassSelect?.closest(".form-field");

    if (classField) {
        classField.insertAdjacentElement("afterend", wrapper);
    } else if (editStudentForm) {
        editStudentForm.appendChild(wrapper);
    }

    return document.getElementById(
        "editStudentArabicClass"
    );
}

async function loadArabicClasses() {
    try {
        const response = await fetch(
            "./api/arabic-classes/list.php",
            {
                credentials: "same-origin"
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message ||
                "Unable to load Arabic classes."
            );
        }

        arabicClasses = data.arabic_classes || [];

        populateClassFilter();

        const addSelect =
            ensureArabicClassField();

        const editSelect =
            ensureEditArabicClassField();

        [addSelect, editSelect].forEach(select => {
            if (!select) return;

            select.innerHTML =
                '<option value="">Select Arabic class</option>';

            arabicClasses.forEach(arabicClass => {
                const option =
                    document.createElement("option");

                option.value = arabicClass.id;
                option.textContent =
                    arabicClass.name;

                if (
                    Number(arabicClass.is_active) !== 1
                ) {
                    option.disabled = true;
                    option.textContent +=
                        " (Inactive)";
                }

                select.appendChild(option);
            });
        });

    } catch (error) {
        console.error(
            "Arabic class loading error:",
            error
        );
    }
}

function openStudentModal() {
    if (!addStudentModal) return;

    addStudentModal.hidden = false;
    document.body.style.overflow = "hidden";
}

function closeStudentModalWindow() {
    if (!addStudentModal) return;

    addStudentModal.hidden = true;
    document.body.style.overflow = "";
}

if (addStudentButton) {
    addStudentButton.addEventListener(
        "click",
        openStudentModal
    );
}

if (closeStudentModal) {
    closeStudentModal.addEventListener(
        "click",
        closeStudentModalWindow
    );
}

if (cancelStudentButton) {
    cancelStudentButton.addEventListener(
        "click",
        closeStudentModalWindow
    );
}

if (addStudentModal) {
    addStudentModal.addEventListener("click", event => {
        if (event.target === addStudentModal) {
            closeStudentModalWindow();
        }
    });
}

if (studentPhoto) {
    studentPhoto.addEventListener("change", () => {
        const file = studentPhoto.files?.[0];

        if (!file) {
            studentPhotoPreview.style.backgroundImage = "";
            studentPhotoPreview.innerHTML =
                "<span>ST</span>";
            return;
        }

        if (file.size > 3 * 1024 * 1024) {
            alert(
                "Student photo must not exceed 3MB."
            );
            studentPhoto.value = "";
            return;
        }

        const reader = new FileReader();

        reader.onload = event => {
            studentPhotoPreview.style.backgroundImage =
                `url("${event.target.result}")`;

            studentPhotoPreview.innerHTML = "";
        };

        reader.readAsDataURL(file);
    });
}

if (addStudentForm) {
    addStudentForm.addEventListener(
        "submit",
        async event => {
            event.preventDefault();

            const formData =
                new FormData(addStudentForm);

            const uploadedPhoto =
                formData.get("profile_image");

            console.log(
                "Student profile photo:",
                uploadedPhoto instanceof File
                    ? `${uploadedPhoto.name} (${uploadedPhoto.size} bytes)`
                    : "No photo selected"
            );

            const arabicClass =
                document.getElementById(
                    "studentArabicClass"
                );

            if (arabicClass) {
                formData.set(
                    "arabic_class_id",
                    arabicClass.value || ""
                );
            }

            if (removeStudentPhoto) {
                formData.set("remove_profile_image", "1");
            }

            setStudentSaveButtonState(
                saveStudentButton,
                "saving",
                "Save Student"
            );

            try {
                const response = await fetch(
                    "./api/students/create.php",
                    {
                        method: "POST",
                        body: formData,
                        credentials: "same-origin"
                    }
                );

                const data =
                    await response.json();

                if (
                    !response.ok ||
                    !data.success
                ) {
                    throw new Error(
                        data.message ||
                        "Unable to save student."
                    );
                }

                setStudentSaveButtonState(
                    saveStudentButton,
                    "saved",
                    "Save Student"
                );

                alert(
                    "Student added successfully."
                );

                closeStudentModalWindow();

                addStudentForm.reset();

                if (studentPhotoPreview) {
                    studentPhotoPreview.style.backgroundImage =
                        "";

                    studentPhotoPreview.innerHTML =
                        "<span>ST</span>";
                }

                await loadStudents();

            } catch (error) {
                console.error(
                    "Student creation error:",
                    error
                );

                alert(error.message);
            }
        }
    );
}

function openViewStudent(studentId) {
    const student = students.find(
        item =>
            String(item.id) ===
            String(studentId)
    );

    if (!student || !viewStudentModal) return;

    const fullName = [
        student.first_name,
        student.middle_name,
        student.last_name
    ]
        .filter(Boolean)
        .join(" ");

    const content =
        document.getElementById("viewStudentContent");

    if (!content) return;

    const isActive =
        Number(student.is_active) === 1;

    const studentType =
        String(student.student_type || "")
            .toLowerCase() === "boarding"
            ? "Boarding Student"
            : "Day Student";

    const photoMarkup = student.profile_image
        ? `<div
                class="view-student-photo"
                style="background-image:url("./${student.profile_image}")"
                aria-label="${escapeHtml(fullName)}"
            ></div>`
        : `<div class="view-student-photo view-student-photo-placeholder">
                <span>${escapeHtml(
                    (student.first_name || "S").charAt(0) +
                    (student.last_name || "").charAt(0)
                )}</span>
            </div>`;

    content.innerHTML = `
        <div class="view-student-profile">

            <div class="view-student-profile-header">
                ${photoMarkup}

                <div class="view-student-profile-heading">
                    <span class="eyebrow">STUDENT PROFILE</span>
                    <h3>${escapeHtml(fullName || "Unnamed Student")}</h3>
                    <p>
                        ${escapeHtml(
                            student.admission_number || "No admission number"
                        )}
                    </p>
                </div>

                <span class="student-status ${
                    isActive ? "active" : "inactive"
                }">
                    ${isActive ? "Active" : "Inactive"}
                </span>
            </div>

            <div class="view-student-details">

                <div class="view-student-detail">
                    <span>Admission Number</span>
                    <strong>${escapeHtml(
                        student.admission_number || "—"
                    )}</strong>
                </div>

                <div class="view-student-detail">
                    <span>Class</span>
                    <strong>${escapeHtml(
                        student.class_name || "—"
                    )}</strong>
                </div>

                <div class="view-student-detail">
                    <span>Arabiyya Class</span>
                    <strong>${escapeHtml(
                        student.arabic_class_name || "—"
                    )}</strong>
                </div>

                <div class="view-student-detail">
                    <span>Gender</span>
                    <strong>${escapeHtml(
                        student.gender || "—"
                    )}</strong>
                </div>

                <div class="view-student-detail">
                    <span>Student Type</span>
                    <strong>${escapeHtml(studentType)}</strong>
                </div>

                <div class="view-student-detail">
                    <span>Date of Birth</span>
                    <strong>${escapeHtml(
                        student.date_of_birth || "—"
                    )}</strong>
                </div>

                <div class="view-student-detail">
                    <span>Admission Date</span>
                    <strong>${escapeHtml(
                        student.admission_date || "—"
                    )}</strong>
                </div>

            </div>

            <div class="view-student-actions">
                <button
                    type="button"
                    class="student-action-button edit-student-button"
                    data-student-id="${student.id}"
                >
                    Edit Student
                </button>

                <button
                    type="button"
                    class="student-action-button view-student-close-button"
                    type="button"
                >
                    Close
                </button>
            </div>

        </div>
    `;

    viewStudentModal.hidden = false;
    document.body.style.overflow = "hidden";
}

function closeViewStudentWindow() {
    if (!viewStudentModal) return;

    viewStudentModal.hidden = true;
    document.body.style.overflow = "";
}

if (closeViewStudentModal) {
    closeViewStudentModal.addEventListener(
        "click",
        closeViewStudentWindow
    );
}

if (viewStudentModal) {
    viewStudentModal.addEventListener(
        "click",
        event => {
            if (
                event.target ===
                viewStudentModal
            ) {
                closeViewStudentWindow();
            }
        }
    );
}

studentsTableBody.addEventListener(
    "click",
    event => {
        const viewButton =
            event.target.closest(
                ".view-student-button"
            );

        if (viewButton) {
            openViewStudent(
                viewButton.dataset.studentId
            );
        }
    }
);

function populateEditClasses(
    selectedClassId
) {
    const editSelect =
        document.getElementById(
            "editStudentClass"
        );

    if (!editSelect) return;

    editSelect.innerHTML =
        '<option value="">Select class</option>';

    classes.forEach(classItem => {
        const option =
            document.createElement("option");

        option.value = classItem.id;
        option.textContent =
            classItem.name;

        if (
            Number(classItem.is_active) !== 1
        ) {
            option.disabled = true;
            option.textContent +=
                " (Inactive)";
        }

        if (
            String(classItem.id) ===
            String(selectedClassId)
        ) {
            option.selected = true;
        }

        editSelect.appendChild(option);
    });
}

function populateEditArabicClass(
    selectedArabicClassId
) {
    const editSelect =
        ensureEditArabicClassField();

    if (!editSelect) return;

    editSelect.value =
        selectedArabicClassId
            ? String(selectedArabicClassId)
            : "";
}

function openEditStudent(studentId) {
    const student = students.find(
        item =>
            String(item.id) ===
            String(studentId)
    );

    if (!student || !editStudentModal) {
        return;
    }

    document.getElementById(
        "editStudentId"
    ).value = student.id;

    document.getElementById(
        "editAdmissionNumber"
    ).value =
        student.admission_number || "";

    document.getElementById(
        "editFirstName"
    ).value =
        student.first_name || "";

    document.getElementById(
        "editLastName"
    ).value =
        student.last_name || "";

    document.getElementById(
        "editGender"
    ).value =
        student.gender || "";

    document.getElementById(
        "editStudentType"
    ).value =
        student.student_type || "Day Student";

    const editParentGuardianName =
        document.getElementById(
            "editParentGuardianName"
        );

    if (editParentGuardianName) {
        editParentGuardianName.value =
            student.parent_guardian_name || "";
    }

    const editParentGuardianPhone =
        document.getElementById(
            "editParentGuardianPhone"
        );

    if (editParentGuardianPhone) {
        editParentGuardianPhone.value =
            String(student.parent_guardian_phone || "")
                .replace(/^\+234/, "")
                .replace(/^0/, "")
                .replace(/\D/g, "")
                .slice(0, 10);
    }

    document.getElementById(
        "editDateOfBirth"
    ).value =
        student.date_of_birth || "";

    document.getElementById(
        "editStudentStatus"
    ).value =
        String(student.is_active);

    populateEditClasses(
        student.class_id
    );

    populateEditArabicClass(
        student.arabic_class_id
    );

    editStudentPhoto.value = "";
    removeStudentPhoto = false;

    if (removeStudentPhotoButton) {
        removeStudentPhotoButton.dataset.remove = "0";
    }

    if (student.profile_image) {
        editStudentPhotoPreview.style.backgroundImage = "";
        editStudentPhotoPreview.innerHTML =
            `<img
                src="./${student.profile_image}"
                alt="${escapeHtml(
                    `${student.first_name || ""} ${student.last_name || ""}`.trim()
                )}"
            >`;
    } else {
        editStudentPhotoPreview.style.backgroundImage = "";
        editStudentPhotoPreview.innerHTML =
            "<span>ST</span>";
    }

    editStudentModal.hidden = false;
    document.body.style.overflow =
        "hidden";
}

function closeEditStudentWindow() {
    if (!editStudentModal) return;

    editStudentModal.hidden = true;
    document.body.style.overflow = "";
}

if (closeEditStudentModal) {
    closeEditStudentModal.addEventListener(
        "click",
        closeEditStudentWindow
    );
}

if (cancelEditStudent) {
    cancelEditStudent.addEventListener(
        "click",
        closeEditStudentWindow
    );
}

if (removeStudentPhotoButton) {
    removeStudentPhotoButton.addEventListener("click", () => {
        removeStudentPhoto = true;

        if (editStudentPhoto) {
            editStudentPhoto.value = "";
        }

        if (editStudentPhotoPreview) {
            editStudentPhotoPreview.style.backgroundImage = "";
            editStudentPhotoPreview.innerHTML = "<span>ST</span>";
        }
    });
}

if (editStudentPhoto) {
    editStudentPhoto.addEventListener(
        "change",
        () => {
            const file =
                editStudentPhoto.files?.[0];

            if (!file) return;

            removeStudentPhoto = false;

            if (removeStudentPhotoButton) {
                removeStudentPhotoButton.dataset.remove = "0";
            }

            if (
                file.size >
                3 * 1024 * 1024
            ) {
                alert(
                    "Student photo must not exceed 3MB."
                );

                editStudentPhoto.value =
                    "";

                return;
            }

            const reader =
                new FileReader();

            reader.onload =
                event => {
                    editStudentPhotoPreview.style.backgroundImage = "";
                    editStudentPhotoPreview.innerHTML =
                        `<img
                            src="${event.target.result}"
                            alt="Selected student photo"
                        >`;
                };

            reader.readAsDataURL(file);
        }
    );
}

if (editStudentForm) {
    editStudentForm.addEventListener(
        "submit",
        async event => {
            event.preventDefault();

            const formData =
                new FormData(editStudentForm);

            const arabicClass =
                document.getElementById(
                    "editStudentArabicClass"
                );

            if (arabicClass) {
                formData.set(
                    "arabic_class_id",
                    arabicClass.value || ""
                );
            }

            if (removeStudentPhoto) {
                formData.set("remove_profile_image", "1");
            }

            setStudentSaveButtonState(
                updateStudentButton,
                "saving",
                "Save Changes"
            );

            try {
                const response = await fetch(
                    "./api/students/update.php",
                    {
                        method: "POST",
                        body: formData,
                        credentials: "same-origin"
                    }
                );

                const data =
                    await response.json();

                if (
                    !response.ok ||
                    !data.success
                ) {
                    throw new Error(
                        data.message ||
                        "Unable to update student."
                    );
                }

                setStudentSaveButtonState(
                    updateStudentButton,
                    "saved",
                    "Save Changes"
                );

                alert(
                    "Student updated successfully."
                );

                closeEditStudentWindow();

                removeStudentPhoto = false;

                if (removeStudentPhotoButton) {
                    removeStudentPhotoButton.dataset.remove = "0";
                }


                await loadStudents();

            } catch (error) {
                console.error(
                    "Student update error:",
                    error
                );

                alert(error.message);
            }
        }
    );
}

async function toggleStudentStatus(
    studentId
) {
    const student = students.find(
        item =>
            String(item.id) ===
            String(studentId)
    );

    if (!student) return;

    const nextStatus =
        Number(student.is_active) === 1
            ? 0
            : 1;

    const action =
        nextStatus === 1
            ? "activate"
            : "deactivate";

    if (
        !confirm(
            `Are you sure you want to ${action} this student?`
        )
    ) {
        return;
    }

    try {
        const response = await fetch(
            "./api/students/toggle-status.php",
            {
                method: "POST",
                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded"
                },
                credentials: "same-origin",
                body:
                    `id=${encodeURIComponent(studentId)}&is_active=${encodeURIComponent(nextStatus)}`
            }
        );

        const data =
            await response.json();

        if (
            !response.ok ||
            !data.success
        ) {
            throw new Error(
                data.message ||
                "Unable to change student status."
            );
        }

        await loadStudents();

    } catch (error) {
        console.error(
            "Student status error:",
            error
        );

        alert(error.message);
    }
}

studentsTableBody.addEventListener(
    "click",
    event => {
        const editButton =
            event.target.closest(
                ".edit-student-button"
            );

        const toggleButton =
            event.target.closest(
                ".toggle-student-button"
            );

        if (editButton) {
            openEditStudent(
                editButton.dataset.studentId
            );
            return;
        }

        if (toggleButton) {
            toggleStudentStatus(
                toggleButton.dataset.studentId
            );
        }
    }
);

document.addEventListener(
    "keydown",
    event => {
        if (event.key !== "Escape") {
            return;
        }

        if (
            addStudentModal &&
            !addStudentModal.hidden
        ) {
            closeStudentModalWindow();
        }

        if (
            viewStudentModal &&
            !viewStudentModal.hidden
        ) {
            closeViewStudentWindow();
        }

        if (
            editStudentModal &&
            !editStudentModal.hidden
        ) {
            closeEditStudentWindow();
        }
    }
);

async function initializeStudentsPage() {
    await loadClasses();
    await loadArabicClasses();
    await loadStudents();
}

initializeStudentsPage();

/* View Student modal — dynamic button actions */
const viewStudentContent = document.getElementById("viewStudentContent");

if (viewStudentContent) {
    viewStudentContent.addEventListener("click", event => {
        const closeButton = event.target.closest(
            ".view-student-close-button"
        );

        if (closeButton) {
            closeViewStudentWindow();
            return;
        }

        const editButton = event.target.closest(
            ".view-student-actions .edit-student-button"
        );

        if (editButton) {
            const studentId = editButton.dataset.studentId;

            closeViewStudentWindow();

            if (studentId) {
                openEditStudent(studentId);
            }
        }
    });
}

function setupParentGuardianPhoneValidation(inputId) {
    const input = document.getElementById(inputId);

    if (!input) return;

    const updatePhoneState = () => {
        const digits = String(input.value || "")
            .replace(/\D/g, "")
            .slice(0, 10);

        input.value = digits;

        const field = input.closest(".form-field");

        if (!field) return;

        let message = field.querySelector(
            ".phone-validation-message"
        );

        if (!message) {
            message = document.createElement("small");
            message.className =
                "phone-validation-message";
            field.appendChild(message);
        }

        if (!digits) {
            message.textContent = "";
            message.hidden = true;
            input.removeAttribute("aria-invalid");
            input.classList.remove("phone-invalid");
            return;
        }

        if (digits.length < 10) {
            message.textContent =
                `Phone number is incomplete — ${digits.length}/10 digits entered.`;
            message.hidden = false;
            input.setAttribute("aria-invalid", "true");
            input.classList.add("phone-invalid");
            return;
        }

        if (!/^[789]\d{9}$/.test(digits)) {
            message.textContent =
                "Enter a valid Nigerian mobile number.";
            message.hidden = false;
            input.setAttribute("aria-invalid", "true");
            input.classList.add("phone-invalid");
            return;
        }

        message.textContent =
            "✓ Complete Nigerian mobile number.";
        message.hidden = false;
        input.removeAttribute("aria-invalid");
        input.classList.remove("phone-invalid");
        input.classList.add("phone-valid");
    };

    input.addEventListener("input", updatePhoneState);
    input.addEventListener("blur", updatePhoneState);
}

setupParentGuardianPhoneValidation(
    "parentGuardianPhone"
);

setupParentGuardianPhoneValidation(
    "editParentGuardianPhone"
);

function validateParentGuardianPhoneBeforeSubmit(inputId) {
    const input = document.getElementById(inputId);

    if (!input) return true;

    const digits = String(input.value || "")
        .replace(/\D/g, "");

    if (!digits) {
        return true;
    }

    if (
        digits.length !== 10 ||
        !/^[789]\d{9}$/.test(digits)
    ) {
        input.focus();
        input.setAttribute("aria-invalid", "true");
        input.classList.add("phone-invalid");

        const field = input.closest(".form-field");

        if (field) {
            const message = field.querySelector(
                ".phone-validation-message"
            );

            if (message) {
                message.textContent =
                    digits.length < 10
                        ? `Phone number is incomplete — ${digits.length}/10 digits entered.`
                        : "Enter a valid Nigerian mobile number.";

                message.hidden = false;
            }
        }

        return false;
    }

    return true;
}

if (addStudentForm) {
    addStudentForm.addEventListener(
        "submit",
        event => {
            if (
                !validateParentGuardianPhoneBeforeSubmit(
                    "parentGuardianPhone"
                )
            ) {
                event.preventDefault();
            }
        },
        true
    );
}

if (editStudentForm) {
    editStudentForm.addEventListener(
        "submit",
        event => {
            if (
                !validateParentGuardianPhoneBeforeSubmit(
                    "editParentGuardianPhone"
                )
            ) {
                event.preventDefault();
            }
        },
        true
    );
}

/* MARKAZ-AUTO-STUDENT-CAMERA-JS */
/* MARKAZ-CAMERA-AI-ENGINE */
const cameraAI={ready:false,initializing:false,faceLandmarker:null,poseLandmarker:null,animationFrame:null,lastDetectionTime:0,detectionInterval:120,lastVideoTime:-1,running:false,alignedSince:0,countdownActive:false,countdownTimer:null,lastCenter:null};

/* MARKAZ-CAMERA-FINAL-GATE-V1 */
const cameraFinalGate={
    lastValidAt:0,
    maxAge:350
};


const cameraStatus=()=>document.getElementById("studentCameraStatus");
const cameraVideo=()=>document.getElementById("studentCameraVideo");

const cameraVoice={
    enabled:true,
    lastMessage:"",
    lastSpokenAt:0,
    cooldown:1500
};

const speakCameraStatus=message=>{
    if(!cameraVoice.enabled)return;
    if(!("speechSynthesis" in window))return;
    if(!message)return;

    const text=String(message)
        .replace("…","")
        .replace("✓","")
        .trim();

    if(!text)return;

    const immediateVoice=
        text==="3"||
        text==="2"||
        text==="1"||
        text.toLowerCase().includes("capturing photo");

    const now=Date.now();

    if(
        !immediateVoice &&
        text===cameraVoice.lastMessage
    )return;

    if(
        !immediateVoice &&
        now-cameraVoice.lastSpokenAt<cameraVoice.cooldown
    )return;

    cameraVoice.lastMessage=text;
    cameraVoice.lastSpokenAt=now;

    window.speechSynthesis.cancel();

    const utterance=new SpeechSynthesisUtterance(text);
    utterance.lang="en-NG";
    utterance.rate=0.95;
    utterance.pitch=1;

    window.speechSynthesis.speak(utterance);
};

const setCameraStatus=message=>{
    const el=cameraStatus();
    if(el)el.textContent=message;
    speakCameraStatus(message);
};

const setCameraCheck=(id,ok,label)=>{
    const el=document.getElementById(id);
    if(el) el.textContent=`${ok?"✓":"○"} ${label}`;
};

const initializeCameraAI=async()=>{
    if(cameraAI.ready)return true;
    if(cameraAI.initializing)return false;

    const ai=window.MarkazCameraAI;

    if(!ai||typeof ai.initialize!=="function"){
        setCameraStatus("AI engine is still loading…");
        return false;
    }

    cameraAI.initializing=true;

    try{
        const result=await ai.initialize();

        cameraAI.faceLandmarker=result.faceLandmarker;
        cameraAI.poseLandmarker=result.poseLandmarker;
        cameraAI.ready=Boolean(
            cameraAI.faceLandmarker&&cameraAI.poseLandmarker
        );

        if(cameraAI.ready)
            setCameraStatus("AI ready — checking student position…");

        return cameraAI.ready;
    }catch(error){
        console.error("MARKAZ Camera AI initialization failed:",error);
        setCameraStatus(
            `AI error: ${error?.message||"Unknown AI initialization error."}`
        );
        return false;
    }finally{
        cameraAI.initializing=false;
    }
};

const cancelCameraCountdown=()=>{
    cameraAI.countdownActive=false;
    if(cameraAI.countdownTimer){
        clearTimeout(cameraAI.countdownTimer);
        cameraAI.countdownTimer=null;
    }
};

const stopCameraAI=()=>{
    cameraAI.running=false;
    cameraAI.alignedSince=0;
    cameraAI.lastVideoTime=-1;
    cameraAI.lastCenter=null;
    cancelCameraCountdown();

    if(cameraAI.animationFrame){
        cancelAnimationFrame(cameraAI.animationFrame);
        cameraAI.animationFrame=null;
    }
};

const bounds=points=>{
    if(!points?.length)return null;
    const xs=points.map(p=>p.x),ys=points.map(p=>p.y);
    const left=Math.min(...xs),right=Math.max(...xs);
    const top=Math.min(...ys),bottom=Math.max(...ys);
    return{
        left,right,top,bottom,
        width:right-left,height:bottom-top,
        centerX:(left+right)/2,
        centerY:(top+bottom)/2
    };
};

const visible=p=>p&&(
    typeof p.visibility!=="number"||p.visibility>=.55
);

const checkStudentPosition=(faceResult,poseResult)=>{
    const face=faceResult?.faceLandmarks?.[0];
    const pose=poseResult?.landmarks?.[0];

    setCameraCheck("cameraCheckFace",!!face,"Full face visible");

    if(!face){
        setCameraCheck("cameraCheckPosition",false,"Head centered");
        setCameraCheck("cameraCheckEars",false,"Shoulders visible");
        setCameraCheck("cameraCheckClarity",false,"Head-to-chest framing");
        return{ok:false,msg:"Show the student's full face."};
    }

    const b=bounds(face);

    if(!b){
        setCameraCheck("cameraCheckPosition",false,"Head centered");
        return{ok:false,msg:"Move the student's face into view."};
    }

    const required=[
        face[10],
        face[33],
        face[133],
        face[263],
        face[362],
        face[454],
        face[234],
        face[1],
        face[152]
    ];

    const landmarksReady=required.every(point=>
        point&&
        Number.isFinite(point.x)&&
        Number.isFinite(point.y)
    );

    if(!landmarksReady){
        setCameraCheck("cameraCheckFace",false,"Full face visible");
        return{ok:false,msg:"Show the student's full face."};
    }

    const centered=
        Math.abs(b.centerX-.5)<=.075&&
        b.centerY>=.23&&
        b.centerY<=.57;

    const size=
        b.width>=.25&&
        b.width<=.52&&
        b.height>=.24&&
        b.height<=.58;

    const leftEyeOuter=face[33];
    const rightEyeOuter=face[263];
    const nose=face[1];
    const chin=face[152];
    const leftCheek=face[234];
    const rightCheek=face[454];

    const eyeDistance=Math.abs(
        rightEyeOuter.x-leftEyeOuter.x
    );

    const faceWidth=Math.abs(
        rightCheek.x-leftCheek.x
    );

    const faceCenterX=
        (leftCheek.x+rightCheek.x)/2;

    const noseOffset=
        faceWidth>0
            ?Math.abs(nose.x-faceCenterX)/faceWidth
            :1;

    const eyeLineAngle=
        Math.atan2(
            rightEyeOuter.y-leftEyeOuter.y,
            rightEyeOuter.x-leftEyeOuter.x
        )*180/Math.PI;

    const eyeCenterY=
        (leftEyeOuter.y+rightEyeOuter.y)/2;

    const faceHeight=Math.abs(chin.y-face[10].y);

    const pitchRatio=
        faceHeight>0
            ?(nose.y-eyeCenterY)/faceHeight
            :1;

    const faceSymmetry=
        eyeDistance>=.08&&
        faceWidth>=.16&&
        noseOffset<=.075;

    const headUpright=
        Math.abs(eyeLineAngle)<=7;

    const headLevel=
        pitchRatio>=.20&&
        pitchRatio<=.42;

    const leftShoulder=pose?.[11];
    const rightShoulder=pose?.[12];

    const shoulders=
        visible(leftShoulder)&&
        visible(rightShoulder);

    const shoulderWidth=shoulders
        ?Math.abs(leftShoulder.x-rightShoulder.x)
        :0;

    const chest=
        shoulders&&
        shoulderWidth>=.30&&
        leftShoulder.y>b.bottom&&
        rightShoulder.y>b.bottom;

    const fullFace=
        centered&&
        size&&
        faceSymmetry&&
        headUpright&&
        headLevel;

    setCameraCheck(
        "cameraCheckPosition",
        fullFace,
        "Face centered and upright"
    );

    setCameraCheck(
        "cameraCheckEars",
        shoulders,
        "Shoulders visible"
    );

    setCameraCheck(
        "cameraCheckClarity",
        fullFace&&chest,
        "Head-to-chest framing"
    );

    if(!centered)
        return{
            ok:false,
            msg:"Center the student's full face."
        };

    if(!size)
        return b.width<.25
            ?{
                ok:false,
                msg:"Move the student closer."
            }
            :{
                ok:false,
                msg:"Move the student slightly back."
            };

    if(!faceSymmetry)
        return{
            ok:false,
            msg:"Show the full face and face the camera."
        };

    if(!headUpright)
        return{
            ok:false,
            msg:"Straighten the student's head."
        };

    if(!headLevel)
        return{
            ok:false,
            msg:"Keep the student's head level."
        };

    if(!shoulders)
        return{
            ok:false,
            msg:"Show both shoulders."
        };

    if(!chest)
        return{
            ok:false,
            msg:"Show the student from head to chest."
        };

    return{
        ok:true,
        center:{
            x:b.centerX,
            y:b.centerY
        }
    };
};

const updateCameraProgress = percent => {
    const ring = document.getElementById("studentCameraProgress");
    const value = document.getElementById("studentCameraProgressValue");
    const check = document.getElementById("studentCameraProgressCheck");

    if (!ring || !value) {
        return;
    }

    const safePercent = Math.max(0, Math.min(100, percent));
    const circumference = 282.74;

    ring.classList.add("is-active");

    value.style.strokeDashoffset =
        String(circumference - (circumference * safePercent / 100));

    if (safePercent >= 100) {
        ring.classList.add("is-complete");

        if (check) {
            check.textContent = "✓";
        }
    } else {
        ring.classList.remove("is-complete");

        if (check) {
            check.textContent = "";
        }
    }
};

const resetCameraProgress = () => {
    const ring = document.getElementById("studentCameraProgress");
    const value = document.getElementById("studentCameraProgressValue");
    const check = document.getElementById("studentCameraProgressCheck");

    if (ring) {
        ring.classList.remove("is-active", "is-complete");
    }

    if (value) {
        value.style.strokeDashoffset = "282.74";
    }

    if (check) {
        check.textContent = "";
    }
};

const startCameraAI=async()=>{
    if(cameraAI.running)return;

    if(!(await initializeCameraAI()))return;

    const video=cameraVideo();

    if(!video){
        setCameraStatus("Camera video is not available.");
        return;
    }

    cameraAI.running=true;
    cameraAI.alignedSince=0;
    cameraAI.lastCenter=null;

    setCameraStatus("Position the student from head to chest.");

    const loop=timestamp=>{
        if(!cameraAI.running)return;

        cameraAI.animationFrame=requestAnimationFrame(loop);

        if(
            video.readyState<2||
            !video.videoWidth||
            !video.videoHeight||
            timestamp-cameraAI.lastDetectionTime<cameraAI.detectionInterval||
            video.currentTime===cameraAI.lastVideoTime
        )return;

        cameraAI.lastDetectionTime=timestamp;
        cameraAI.lastVideoTime=video.currentTime;

        try{
            const t=Math.max(0,Math.round(timestamp));

            const face=cameraAI.faceLandmarker.detectForVideo(video,t);
            const pose=cameraAI.poseLandmarker.detectForVideo(video,t);

            const result=checkStudentPosition(face,pose);

            if(!result.ok){
                cameraAI.alignedSince=0;
                cameraAI.lastCenter=null;
                cancelCameraCountdown();
                resetCameraProgress();
                setCameraStatus(result.msg);
                return;
            }

            const c=result.center;
            let stable=true;

            if(cameraAI.lastCenter){
                const movement=Math.hypot(
                    c.x-cameraAI.lastCenter.x,
                    c.y-cameraAI.lastCenter.y
                );
                stable=movement<=.018;
            }

            cameraAI.lastCenter=c;

            if(!stable){
                cameraAI.alignedSince=0;
                cancelCameraCountdown();
                resetCameraProgress();
                setCameraStatus("Hold still — keep the student centered.");
                return;
            }

            /* MARKAZ-CAMERA-FINAL-GATE-VALID */
            cameraFinalGate.lastValidAt=Date.now();

            if(!cameraAI.alignedSince)
                cameraAI.alignedSince=timestamp;

            const readinessDuration=2500;
            const stableFor=timestamp-cameraAI.alignedSince;
            const readinessPercent=Math.min(
                100,
                (stableFor/readinessDuration)*100
            );

            updateCameraProgress(readinessPercent);

            if(stableFor<readinessDuration){
                setCameraStatus("Perfect — hold still.");
                return;
            }

            updateCameraProgress(100);

            if(cameraAI.countdownActive)return;

            cameraAI.countdownActive=true;

            const countdown=["3","2","1"];
            let i=0;

            const countdownEl=document.getElementById("studentCameraCountdown");
            const progressCheck=document.getElementById("studentCameraProgressCheck");

            const showCountdownNumber=number=>{
                if(countdownEl){
                    countdownEl.textContent=number;
                    countdownEl.classList.remove("is-visible");

                    void countdownEl.offsetWidth;

                    countdownEl.classList.add("is-visible");
                }

                if(progressCheck){
                    progressCheck.style.opacity="0";
                }
            };

            const clearCountdownDisplay=()=>{
                if(countdownEl){
                    countdownEl.textContent="";
                    countdownEl.classList.remove("is-visible");
                }

                if(progressCheck){
                    progressCheck.style.opacity="";
                }
            };

            const next=()=>{
                if(!cameraAI.running){
                    clearCountdownDisplay();
                    cancelCameraCountdown();
                    return;
                }

                if(i<countdown.length){
                    showCountdownNumber(countdown[i]);
                    setCameraStatus(countdown[i]);
                    i++;
                    cameraAI.countdownTimer=setTimeout(next,800);
                    return;
                }

                clearCountdownDisplay();
                cameraAI.countdownActive=false;
                cameraAI.countdownTimer=null;
                setCameraStatus("Capturing photo…");
                stopCameraAI();

                if(typeof window.captureMarkazStudentPhoto==="function")
                    window.captureMarkazStudentPhoto();
            };

            next();

        }catch(error){
            console.error("MARKAZ Camera AI detection error:",error);
            setCameraStatus(
                `AI detection error: ${error?.message||"Unknown detection error."}`
            );
        }
    };

    cameraAI.animationFrame=requestAnimationFrame(loop);
};

(() => {
    const initStudentCamera = () => {
        const cameraModal = document.getElementById("studentCameraModal");
        const cameraVideo = document.getElementById("studentCameraVideo");
        const cameraCanvas = document.getElementById("studentCameraCanvas");
        const cameraStatus = document.getElementById("studentCameraStatus");
        const closeButton = document.getElementById("closeStudentCamera");
        const cancelButton = document.getElementById("cancelStudentCamera");
        const retakeButton = document.getElementById("retakeStudentCamera");
        const usePhotoButton = document.getElementById("useStudentCameraPhoto");
        const switchCameraButton =
            document.getElementById("switchStudentCamera");

        const addButton = document.getElementById("captureStudentPhotoButton");
        const addInput = document.getElementById("studentPhoto");
        const addPreview = document.getElementById("studentPhotoPreview");

        const editButton = document.getElementById("captureEditStudentPhotoButton");
        const editInput = document.getElementById("editStudentPhoto");
        const editPreview = document.getElementById("editStudentPhotoPreview");

        if (!cameraModal || !cameraVideo || !cameraCanvas) {
            console.error("MARKAZ camera: required camera elements not found.");
            return;
        }

        let stream = null;
        let targetInput = null;
        let targetPreview = null;
        let captured = false;
        let cameraFacingMode = "user";

        const stopCamera = () => {
            stopCameraAI();

            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }

            cameraVideo.srcObject = null;
        };

        const closeCamera = () => {
    if ("speechSynthesis" in window) {
        window.speechSynthesis.cancel();
    }

            stopCamera();
            resetCameraProgress();

            cameraModal.hidden = true;
            targetInput = null;
            targetPreview = null;
            captured = false;
        };

        const showPreview = dataUrl => {
            if (!targetPreview) return;

            targetPreview.style.backgroundImage = "";
            targetPreview.innerHTML =
                `<img src="${dataUrl}" alt="Captured student photo">`;
        };

        const capturePhoto = () => {
            if (
                captured ||
                cameraVideo.readyState < 2 ||
                !cameraVideo.videoWidth ||
                !cameraVideo.videoHeight
            ) {
                return;
            }

            /* MARKAZ-CAMERA-FINAL-GATE-CAPTURE */
            const finalGateAge=Date.now()-cameraFinalGate.lastValidAt;

            if(
                !cameraFinalGate.lastValidAt ||
                finalGateAge>cameraFinalGate.maxAge
            ){
                captured=false;
                cameraFinalGate.lastValidAt=0;
                resetCameraProgress();
                setCameraStatus("Position changed — please hold still and try again.");
                startCameraAI();
                return;
            }

            captured = true;

            const width = cameraVideo.videoWidth;
            const height = cameraVideo.videoHeight;

            cameraCanvas.width = width;
            cameraCanvas.height = height;

            const context = cameraCanvas.getContext("2d", {
                alpha: false
            });

            context.save();
            context.translate(width, 0);
            context.scale(-1, 1);
            context.drawImage(cameraVideo, 0, 0, width, height);
            context.restore();

            const dataUrl = cameraCanvas.toDataURL(
                "image/jpeg",
                0.88
            );

            cameraCanvas.toBlob(blob => {
                if (!blob) {
                    captured = false;
                    return;
                }

                const file = new File(
                    [blob],
                    `student-photo-${Date.now()}.jpg`,
                    { type: "image/jpeg" }
                );

                if (file.size > 3 * 1024 * 1024) {
                    captured = false;

                    setCameraStatus("Photo is too large. Please try again.");

                    return;
                }

                if (targetInput) {
                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    targetInput.files = transfer.files;

                    targetInput.dispatchEvent(
                        new Event("change", { bubbles: true })
                    );
                }

                showPreview(dataUrl);

                setCameraStatus("Photo captured ✓");

                if (retakeButton) {
                    retakeButton.hidden = false;
                }

                stopCamera();
            }, "image/jpeg", 0.88);
        };

        const openCamera = async (input, preview) => {
            if (!input || !preview) return;

            if (
                !navigator.mediaDevices ||
                !navigator.mediaDevices.getUserMedia
            ) {
                setCameraStatus("Camera is not supported in this browser.");

                cameraModal.hidden = false;
                return;
            }

            stopCamera();
            resetCameraProgress();

            targetInput = input;
            targetPreview = preview;
            captured = false;

            if (retakeButton) {
                retakeButton.hidden = true;
            }

            setCameraStatus("Starting camera…");
            cameraModal.hidden = false;

            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: cameraFacingMode },
                        width: { ideal: 720 },
                        height: { ideal: 900 }
                    },
                    audio: false
                });

                cameraVideo.srcObject = stream;
                await cameraVideo.play();

                setCameraStatus(
                    "Camera ready — position student inside the guide."
                );

                startCameraAI();
            } catch (error) {
                console.error("MARKAZ camera error:", error);

                setCameraStatus(
                    "Camera access was not available."
                );
            }
        };

        window.openMarkazStudentCamera = openCamera;
        window.captureMarkazStudentPhoto = capturePhoto;

        if (switchCameraButton) {
            switchCameraButton.addEventListener("click", async () => {
                cameraFacingMode =
                    cameraFacingMode === "user" ? "environment" : "user";

                if (targetInput && targetPreview) {
                    await openCamera(targetInput, targetPreview);
                }
            });
        }

        if (addButton) {
            addButton.addEventListener("click", () => {
                openCamera(addInput, addPreview);
            });
        }

        if (editButton) {
            editButton.addEventListener("click", () => {
                openCamera(editInput, editPreview);
            });
        }

        closeButton?.addEventListener("click", closeCamera);
        cancelButton?.addEventListener("click", closeCamera);

        retakeButton?.addEventListener("click", () => {
            if (targetInput && targetPreview) {
                openCamera(targetInput, targetPreview);
            }
        });

        cameraModal.addEventListener("click", event => {
            if (event.target === cameraModal) {
                closeCamera();
            }
        });

        console.log("MARKAZ student camera initialized.");
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initStudentCamera, {
            once: true
        });
    } else {
        initStudentCamera();
    }
})();









