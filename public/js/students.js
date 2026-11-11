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
                <td data-label="Student">
                    <div class="student-name-cell">
                        ${studentAvatar}
                        <strong>${escapeHtml(fullName)}</strong>
                    </div>
                </td>

                <td class="student-parent-name-cell" data-label="Parent / Guardian">
                    ${parentGuardianMarkup}
                </td>

                <td class="student-parent-phone-cell" data-label="Parent Phone">
                    ${parentGuardianPhoneMarkup}
                </td>

                <td data-label="Admission No.">
                    <strong class="student-admission-number">
                        ${escapeHtml(student.admission_number)}
                    </strong>
                </td>

                <td data-label="Class">
                    ${escapeHtml(student.class_name || "—")}
                </td>

                <td class="student-arabiyya-class" data-label="Arabiyya Class">
                    ${escapeHtml(student.arabic_class_name || "—")}
                </td>

                <td class="student-age" data-label="Age">
                    ${escapeHtml(String(age))}
                </td>

                <td data-label="Gender">
                    ${escapeHtml(student.gender || "—")}
                </td>
                  <td data-label="Student Type">
                      ${escapeHtml(student.student_type || "Day Student")}
                  </td>

                <td data-label="Status">
                    <span class="student-status ${statusLabel.toLowerCase()}">
                        ${statusLabel}
                    </span>
                </td>

                <td data-label="Actions">
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

/* Square student-photo cropper.
   The crop is stored in the existing profile_image file input, so both
   create/update endpoints and their current validation remain unchanged. */
(() => {
    const modal = document.getElementById("studentPhotoCropModal");
    const canvas = document.getElementById("studentPhotoCropCanvas");
    const zoomInput = document.getElementById("studentPhotoCropZoom");
    const zoomValue = document.getElementById("studentPhotoCropZoomValue");
    const confirmButton = document.getElementById("confirmStudentPhotoCrop");
    const cancelButton = document.getElementById("cancelStudentPhotoCrop");
    const cancelBottomButton = document.getElementById("cancelStudentPhotoCropBottom");
    const errorMessage = document.getElementById("studentPhotoCropError");
    const context = canvas?.getContext("2d");

    if (!modal || !canvas || !context || !zoomInput || !confirmButton) return;

    const cropSize = canvas.width;
    let imageBitmap = null;
    let imageObjectUrl = null;
    let activeInput = null;
    let activePreview = null;
    let activePointerId = null;
    let dragStart = null;
    let offsetX = 0;
    let offsetY = 0;
    let baseScale = 1;
    let zoom = 1;
    let isPreparing = false;
    const confirmedFiles = new WeakMap();

    function showCropError(message) {
        if (!errorMessage) return;
        errorMessage.textContent = message;
        errorMessage.hidden = !message;
    }

    function releaseImage() {
        if (imageBitmap && typeof imageBitmap.close === "function") {
            imageBitmap.close();
        }
        imageBitmap = null;
        if (imageObjectUrl) URL.revokeObjectURL(imageObjectUrl);
        imageObjectUrl = null;
    }

    function clampOffsets() {
        if (!imageBitmap) return;
        const scale = baseScale * zoom;
        const maxX = Math.max(0, (imageBitmap.width * scale - cropSize) / 2);
        const maxY = Math.max(0, (imageBitmap.height * scale - cropSize) / 2);
        offsetX = Math.max(-maxX, Math.min(maxX, offsetX));
        offsetY = Math.max(-maxY, Math.min(maxY, offsetY));
    }

    function drawCrop() {
        if (!imageBitmap) return;
        clampOffsets();
        const scale = baseScale * zoom;
        const width = imageBitmap.width * scale;
        const height = imageBitmap.height * scale;
        const x = (cropSize - width) / 2 + offsetX;
        const y = (cropSize - height) / 2 + offsetY;

        context.clearRect(0, 0, cropSize, cropSize);
        context.fillStyle = "#e9eee8";
        context.fillRect(0, 0, cropSize, cropSize);
        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = "high";
        context.drawImage(imageBitmap, x, y, width, height);
    }

    function updateZoomLabel() {
        if (zoomValue) zoomValue.textContent = Math.round(zoom * 100) + "%";
    }

    function cancelCropper() {
        if (activeInput) {
            const previousFile = confirmedFiles.get(activeInput);
            if (previousFile) {
                try {
                    const transfer = new DataTransfer();
                    transfer.items.add(previousFile);
                    activeInput.files = transfer.files;
                    setPreview(activePreview, previousFile);
                } catch (error) {
                    activeInput.value = "";
                }
            } else {
                activeInput.value = "";
            }
        }
        closeCropper();
    }

    function closeCropper() {
        modal.hidden = true;
        activeInput = null;
        activePreview = null;
        activePointerId = null;
        dragStart = null;
        isPreparing = false;
        confirmButton.disabled = false;
        confirmButton.textContent = "Use This Photo";
        showCropError("");
        releaseImage();
        context.clearRect(0, 0, cropSize, cropSize);
    }

    function setPreview(preview, file) {
        if (!preview || !file) return;
        const previewUrl = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            preview.style.backgroundImage = "";
            preview.innerHTML = "";
            image.alt = "Cropped student profile photo";
            image.style.width = "100%";
            image.style.height = "100%";
            image.style.display = "block";
            image.style.objectFit = "cover";
            image.style.objectPosition = "center";
            preview.appendChild(image);
            URL.revokeObjectURL(previewUrl);
        };
        image.onerror = () => URL.revokeObjectURL(previewUrl);
        image.src = previewUrl;
    }

    async function openCropper(file, input, preview) {
        if (!file || !input) return;
        if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) {
            alert("Please choose a JPG, PNG or WEBP image.");
            input.value = "";
            return;
        }
        if (file.size > 3 * 1024 * 1024) {
            alert("Student photo must not exceed 3MB.");
            input.value = "";
            return;
        }

        isPreparing = true;
        activeInput = input;
        activePreview = preview;
        confirmButton.disabled = true;
        confirmButton.textContent = "Preparing...";
        modal.hidden = false;
        showCropError("");

        try {
            releaseImage();
            if ("createImageBitmap" in window) {
                try {
                    imageBitmap = await createImageBitmap(file, { imageOrientation: "from-image" });
                } catch (orientationError) {
                    imageBitmap = await createImageBitmap(file);
                }
            } else {
                imageObjectUrl = URL.createObjectURL(file);
                imageBitmap = await new Promise((resolve, reject) => {
                    const image = new Image();
                    image.onload = () => resolve(image);
                    image.onerror = () => reject(new Error("This image could not be opened."));
                    image.src = imageObjectUrl;
                });
            }

            baseScale = Math.max(cropSize / imageBitmap.width, cropSize / imageBitmap.height);
            zoom = 1;
            offsetX = 0;
            offsetY = 0;
            zoomInput.value = "1";
            updateZoomLabel();
            drawCrop();
            isPreparing = false;
            confirmButton.disabled = false;
            confirmButton.textContent = "Use This Photo";
            zoomInput.focus({ preventScroll: true });
        } catch (error) {
            console.error("Student photo crop preparation failed:", error);
            showCropError("This image could not be opened. Please choose another JPG, PNG or WEBP photo.");
            confirmButton.disabled = true;
            confirmButton.textContent = "Photo unavailable";
        }
    }

    function setSelectedFile(input, file) {
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
    }

    async function confirmCrop() {
        if (isPreparing || !imageBitmap || !activeInput) return;
        confirmButton.disabled = true;
        confirmButton.textContent = "Preparing photo...";

        try {
            const outputCanvas = document.createElement("canvas");
            outputCanvas.width = 600;
            outputCanvas.height = 600;
            const outputContext = outputCanvas.getContext("2d");
            if (!outputContext) throw new Error("Photo editor is unavailable.");

            const scale = baseScale * zoom * (600 / cropSize);
            const width = imageBitmap.width * scale;
            const height = imageBitmap.height * scale;
            const x = (600 - width) / 2 + offsetX * (600 / cropSize);
            const y = (600 - height) / 2 + offsetY * (600 / cropSize);
            outputContext.fillStyle = "#ffffff";
            outputContext.fillRect(0, 0, 600, 600);
            outputContext.imageSmoothingEnabled = true;
            outputContext.imageSmoothingQuality = "high";
            outputContext.drawImage(imageBitmap, x, y, width, height);

            const blob = await new Promise((resolve, reject) => {
                outputCanvas.toBlob(result => {
                    if (result) resolve(result);
                    else reject(new Error("The cropped image could not be created."));
                }, "image/jpeg", 0.9);
            });

            const originalName = (activeInput.files?.[0]?.name || "student-photo")
                .replace(/\.[^.]+$/, "")
                .replace(/[^a-z0-9_-]/gi, "-")
                .slice(0, 60) || "student-photo";
            const croppedFile = new File([blob], originalName + "-cropped.jpg", {
                type: "image/jpeg",
                lastModified: Date.now()
            });

            if (croppedFile.size > 3 * 1024 * 1024) {
                throw new Error("The cropped photo is still larger than 3MB. Please zoom in and try again.");
            }

            setSelectedFile(activeInput, croppedFile);
            confirmedFiles.set(activeInput, croppedFile);
            setPreview(activePreview, croppedFile);

            if (activeInput === editStudentPhoto) {
                removeStudentPhoto = false;
                if (removeStudentPhotoButton) removeStudentPhotoButton.dataset.remove = "0";
            }

            closeCropper();
        } catch (error) {
            console.error("Student photo crop failed:", error);
            showCropError(error.message || "Unable to crop this photo. Please try another image.");
            confirmButton.disabled = false;
            confirmButton.textContent = "Try Again";
        }
    }

    [cancelButton, cancelBottomButton].forEach(button => {
        button?.addEventListener("click", cancelCropper);
    });

    confirmButton.addEventListener("click", confirmCrop);

    modal.addEventListener("click", event => {
        if (event.target === modal) cancelCropper();
    });

    zoomInput.addEventListener("input", () => {
        zoom = Number(zoomInput.value) || 1;
        updateZoomLabel();
        drawCrop();
    });

    canvas.addEventListener("pointerdown", event => {
        if (!imageBitmap || isPreparing) return;
        activePointerId = event.pointerId;
        canvas.setPointerCapture(event.pointerId);
        dragStart = { x: event.clientX, y: event.clientY, offsetX, offsetY };
    });

    canvas.addEventListener("pointermove", event => {
        if (!dragStart || event.pointerId !== activePointerId) return;
        const rect = canvas.getBoundingClientRect();
        const factor = cropSize / rect.width;
        offsetX = dragStart.offsetX + (event.clientX - dragStart.x) * factor;
        offsetY = dragStart.offsetY + (event.clientY - dragStart.y) * factor;
        drawCrop();
    });

    function endDrag(event) {
        if (event.pointerId !== activePointerId) return;
        dragStart = null;
        activePointerId = null;
    }
    canvas.addEventListener("pointerup", endDrag);
    canvas.addEventListener("pointercancel", endDrag);
    canvas.addEventListener("lostpointercapture", () => {
        dragStart = null;
        activePointerId = null;
    });

    document.addEventListener("keydown", event => {
        if (event.key === "Escape" && !modal.hidden) cancelCropper();
    });

    function bindInput(input, preview) {
        input?.addEventListener("change", () => {
            const file = input.files?.[0];
            if (file) openCropper(file, input, preview);
        });
    }

    bindInput(studentPhoto, studentPhotoPreview);
    bindInput(editStudentPhoto, editStudentPhotoPreview);
})();

/* Photo previews are updated only after the crop is confirmed. */

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
        ? `<div class="view-student-photo">
                <img src="./${escapeHtml(String(student.profile_image).replace(/^\/+/, ""))}"
                     alt="${escapeHtml(fullName)}" loading="lazy">
            </div>`
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

/* Edit photo preview is likewise updated only after crop confirmation. */

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

/* Student photo selection:
   - The regular file inputs open the phone's gallery/files picker.
   - Separate capture inputs request the device camera for a new selfie.
   - Camera selections are copied into the existing named form input so the
     current preview, validation, and multipart upload flow remain unchanged. */
(() => {
    const addButton = document.getElementById("chooseStudentPhotoButton");
    const addInput = document.getElementById("studentPhoto");
    const addCameraButton = document.getElementById("captureStudentPhotoButton");
    const addCameraInput = document.getElementById("studentPhotoCamera");

    const editCameraButton = document.getElementById("captureEditStudentPhotoButton");
    const editInput = document.getElementById("editStudentPhoto");
    const editCameraInput = document.getElementById("editStudentPhotoCamera");

    addButton?.addEventListener("click", () => addInput?.click());
    addCameraButton?.addEventListener("click", () => addCameraInput?.click());
    editCameraButton?.addEventListener("click", () => editCameraInput?.click());

    const transferCameraPhoto = (cameraInput, uploadInput) => {
        cameraInput?.addEventListener("change", () => {
            const photo = cameraInput.files?.[0];
            if (!photo || !uploadInput) return;

            try {
                const transfer = new DataTransfer();
                transfer.items.add(photo);
                uploadInput.files = transfer.files;
                uploadInput.dispatchEvent(new Event("change", { bubbles: true }));
            } catch (error) {
                console.error("Unable to attach camera photo to student form:", error);
                alert("Unable to use the selected camera photo. Please choose the photo using Upload from device.");
            } finally {
                cameraInput.value = "";
            }
        });
    };

    transferCameraPhoto(addCameraInput, addInput);
    transferCameraPhoto(editCameraInput, editInput);
})();
