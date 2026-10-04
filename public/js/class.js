(() => {
    "use strict";

    const params = new URLSearchParams(window.location.search);
    const classId = params.get("id");

    const classTitle = document.getElementById("classTitle");
    const classDescription = document.getElementById("classDescription");
    const studentCount = document.getElementById("studentCount");
    const classStatus = document.getElementById("classStatus");
    const classStudentsContainer = document.getElementById("classStudentsContainer");

    const editClassButton = document.getElementById("editClassButton");
    const addStudentButton = document.getElementById("addStudentButton");
    const panelAddStudentButton = document.getElementById("panelAddStudentButton");
    const enterResultsButton = document.getElementById("enterResultsButton");
    const printClassResultsButton = document.getElementById("printClassResultsButton");

    const resultsPeriodText = document.getElementById("resultsPeriodText");
    const resultsStudentCount = document.getElementById("resultsStudentCount");
    const resultsSubjectCount = document.getElementById("resultsSubjectCount");
    const resultsCompletion = document.getElementById("resultsCompletion");
    const resultSubjectSelect = document.getElementById("resultSubjectSelect");
    const resultStudentSearch = document.getElementById("resultStudentSearch");
    const resultsTableContainer = document.getElementById("resultsTableContainer");
    const resultsMessage = document.getElementById("resultsMessage");

    let selectedClass = null;
    let classStudents = [];
    let resultSubjects = [];
    let classResults = [];
    let selectedSubjectId = "";

    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function setLoadingState() {
        classTitle.textContent = "Loading class...";
        classDescription.textContent = "Loading class information...";
        studentCount.textContent = "—";
        classStatus.textContent = "—";

        classStudentsContainer.innerHTML = `
            <div class="empty-state">
                Loading students...
            </div>
        `;
    }

    function showError(message) {
        classTitle.textContent = "Unable to load class";
        classDescription.textContent = message;
        studentCount.textContent = "—";
        classStatus.textContent = "—";

        classStudentsContainer.innerHTML = `
            <div class="empty-state">
                ${escapeHtml(message)}
            </div>
        `;
    }

    async function loadClass() {
        if (!classId) {
            showError("No class was selected.");
            return false;
        }

        const response = await fetch("./api/classes/list.php", {
            credentials: "same-origin",
            cache: "no-store"
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to load classes.");
        }

        selectedClass = (data.classes || []).find(
            item => String(item.id) === String(classId)
        );

        if (!selectedClass) {
            showError("The selected class could not be found.");
            return false;
        }

        classTitle.textContent = selectedClass.name || "Class";
        classDescription.textContent =
            selectedClass.level_group || "Academic class";

        classStatus.textContent =
            Number(selectedClass.is_active) === 1
                ? "Active"
                : "Inactive";

        document.title = `${selectedClass.name || "Class"} | MARKAZ`;

        return true;
    }

    async function loadStudents() {
        const response = await fetch("./api/students/list.php", {
            credentials: "same-origin",
            cache: "no-store"
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to load students.");
        }

        classStudents = (data.students || []).filter(
            student =>
                String(student.class_id) === String(selectedClass.id)
        );

        studentCount.textContent = classStudents.length;

        renderStudents();
    }

    function renderStudents() {
        if (!classStudents.length) {
            classStudentsContainer.innerHTML = `
                <div class="empty-state">
                    No students have been added to this class yet.
                </div>
            `;
            return;
        }

        classStudentsContainer.innerHTML = `
            <div class="class-students-table-wrap">
                <table class="class-students-table">
                    <thead>
                        <tr>
                            <th>Admission No.</th>
                            <th>Student</th>
                            <th>Gender</th>
                            <th>Arabic Class</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        ${classStudents.map(student => {
                            const fullName = [
                                student.first_name,
                                student.last_name
                            ].filter(Boolean).join(" ");

                            const active =
                                Number(student.is_active) === 1;

                            return `
                                <tr>
                                    <td>
                                        ${escapeHtml(student.admission_number)}
                                    </td>

                                    <td>
                                        <strong>
                                            ${escapeHtml(fullName)}
                                        </strong>
                                    </td>

                                    <td>
                                        ${escapeHtml(student.gender || "—")}
                                    </td>

                                    <td>
                                        ${escapeHtml(
                                            student.arabic_class_name || "—"
                                        )}
                                    </td>

                                    <td>
                                        <span class="student-status ${
                                            active ? "active" : "inactive"
                                        }">
                                            ${active ? "Active" : "Inactive"}
                                        </span>
                                    </td>

                                    <td>
                                        <button
                                            type="button"
                                            class="class-student-button"
                                            data-student-id="${escapeHtml(student.id)}">
                                            View Student
                                        </button>
                                    </td>
                                </tr>
                            `;
                        }).join("")}
                    </tbody>
                </table>
            </div>
        `;
    }

    async function loadResults() {
        const response = await fetch(
            `./api/results/class.php?class_id=${encodeURIComponent(classId)}`,
            {
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "Unable to load class results."
            );
        }

        resultSubjects = data.subjects || [];
        classResults = data.results || [];

        if (Array.isArray(data.students)) {
            classStudents = data.students;
            studentCount.textContent = classStudents.length;

            if (resultsStudentCount) {
                resultsStudentCount.textContent = classStudents.length;
            }

            renderStudents();
        }

        if (resultsSubjectCount) {
            resultsSubjectCount.textContent = resultSubjects.length;
        }

        if (resultsPeriodText && data.academic_period) {
            resultsPeriodText.textContent =
                `${data.academic_period.session_name} • ${data.academic_period.term_name}`;
        }

        populateSubjectSelect();
        calculateCompletion();
    }

    function populateSubjectSelect() {
        if (!resultSubjectSelect) {
            return;
        }

        if (!resultSubjects.length) {
            resultSubjectSelect.innerHTML =
                '<option value="">No subjects assigned</option>';
            return;
        }

        resultSubjectSelect.innerHTML = `
            <option value="">Select a subject</option>
            ${resultSubjects.map(subject => `
                <option value="${escapeHtml(subject.id)}">
                    ${escapeHtml(subject.name)}
                </option>
            `).join("")}
        `;
    }

    function calculateCompletion() {
        if (!resultsCompletion) {
            return;
        }

        const total =
            classStudents.length * resultSubjects.length;

        if (!total) {
            resultsCompletion.textContent = "0%";
            return;
        }

        const completed = classResults.length;

        resultsCompletion.textContent =
            `${Math.min(100, Math.round((completed / total) * 100))}%`;
    }

    function getSelectedSubject() {
        return resultSubjects.find(
            subject => String(subject.id) === String(selectedSubjectId)
        ) || null;
    }

    function getStudentResult(studentId, subjectId) {
        return classResults.find(
            result =>
                String(result.student_id) === String(studentId) &&
                String(result.subject_id) === String(subjectId)
        ) || null;
    }

    function renderResultsTable() {
        if (!resultsTableContainer) {
            return;
        }

        const subject = getSelectedSubject();

        if (!subject) {
            resultsTableContainer.innerHTML = `
                <div class="empty-state">
                    Select a subject to enter results.
                </div>
            `;
            return;
        }

        const searchTerm = (resultStudentSearch?.value || "")
            .trim()
            .toLowerCase();

        const filteredStudents = classStudents.filter(student => {
            const fullName = [
                student.first_name,
                student.middle_name,
                student.last_name
            ]
                .filter(Boolean)
                .join(" ")
                .toLowerCase();

            const admissionNumber =
                String(student.admission_number || "").toLowerCase();

            return (
                !searchTerm ||
                fullName.includes(searchTerm) ||
                admissionNumber.includes(searchTerm)
            );
        });

        if (!filteredStudents.length) {
            resultsTableContainer.innerHTML = `
                <div class="empty-state">
                    No students match your search.
                </div>
            `;
            return;
        }

        resultsTableContainer.innerHTML = `
            <div class="results-table-wrap">
                <table class="results-entry-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Admission No.</th>
                            <th>CA1 / ${escapeHtml(subject.max_ca1)}</th>
                            <th>CA2 / ${escapeHtml(subject.max_ca2)}</th>
                            <th>Exam / ${escapeHtml(subject.max_exam)}</th>
                            <th>Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        ${filteredStudents.map(student => {
                            const result = getStudentResult(
                                student.id,
                                subject.id
                            );

                            const ca1 = result?.ca1 ?? 0;
                            const ca2 = result?.ca2 ?? 0;
                            const exam = result?.exam ?? 0;
                            const total =
                                Number(ca1) +
                                Number(ca2) +
                                Number(exam);

                            return `
                                <tr data-student-id="${escapeHtml(student.id)}">
                                    <td>
                                        <strong>
                                            ${escapeHtml(
                                                [
                                                    student.first_name,
                                                    student.middle_name,
                                                    student.last_name
                                                ]
                                                    .filter(Boolean)
                                                    .join(" ")
                                            )}
                                        </strong>
                                    </td>

                                    <td>
                                        ${escapeHtml(
                                            student.admission_number || "—"
                                        )}
                                    </td>

                                    <td>
                                        <input
                                            type="number"
                                            class="result-score-input"
                                            data-field="ca1"
                                            min="0"
                                            max="${escapeHtml(subject.max_ca1)}"
                                            step="0.01"
                                            value="${escapeHtml(ca1)}"
                                        >
                                    </td>

                                    <td>
                                        <input
                                            type="number"
                                            class="result-score-input"
                                            data-field="ca2"
                                            min="0"
                                            max="${escapeHtml(subject.max_ca2)}"
                                            step="0.01"
                                            value="${escapeHtml(ca2)}"
                                        >
                                    </td>

                                    <td>
                                        <input
                                            type="number"
                                            class="result-score-input"
                                            data-field="exam"
                                            min="0"
                                            max="${escapeHtml(subject.max_exam)}"
                                            step="0.01"
                                            value="${escapeHtml(exam)}"
                                        >
                                    </td>

                                    <td>
                                        <strong
                                            class="result-live-total"
                                            data-total-student="${escapeHtml(student.id)}">
                                            ${total}
                                        </strong>
                                    </td>

                                    <td>
                                        <button
                                            type="button"
                                            class="save-result-button"
                                            data-student-id="${escapeHtml(student.id)}">
                                            Save
                                        </button>
                                    </td>
                                </tr>
                            `;
                        }).join("")}
                    </tbody>
                </table>
            </div>
        `;

        updateLiveTotals();
    }

    function updateLiveTotals() {
        document
            .querySelectorAll(".results-entry-table tbody tr")
            .forEach(row => {
                const ca1 =
                    Number(
                        row.querySelector('[data-field="ca1"]')?.value
                    ) || 0;

                const ca2 =
                    Number(
                        row.querySelector('[data-field="ca2"]')?.value
                    ) || 0;

                const exam =
                    Number(
                        row.querySelector('[data-field="exam"]')?.value
                    ) || 0;

                const totalElement =
                    row.querySelector(".result-live-total");

                if (totalElement) {
                    totalElement.textContent =
                        (ca1 + ca2 + exam).toFixed(2);
                }
            });
    }

    function openStudent(studentId) {
        window.location.href =
            `./student-result.html?student_id=${encodeURIComponent(studentId)}&class_id=${encodeURIComponent(classId)}`;
    }

    function openAddStudent() {
        window.location.href =
            `./students.html?class_id=${encodeURIComponent(selectedClass.id)}`;
    }

    function openEditClass() {
        window.location.href =
            `./classes.html?edit_id=${encodeURIComponent(selectedClass.id)}`;
    }

    async function initialize() {
        setLoadingState();

        try {
            const classLoaded = await loadClass();

            if (!classLoaded) {
                return;
            }

            await loadStudents();
            await loadResults();
        } catch (error) {
            console.error("Class workspace error:", error);

            showError(
                error.message || "Something went wrong while loading this class."
            );
        }
    }

    resultSubjectSelect?.addEventListener("change", () => {
        selectedSubjectId = resultSubjectSelect.value;
        renderResultsTable();
    });

    resultStudentSearch?.addEventListener("input", () => {
        renderResultsTable();
    });

    async function saveResult(studentId, button) {
        const subject = getSelectedSubject();

        if (!subject) {
            return;
        }

        const row = document.querySelector(
            `.results-entry-table tbody tr[data-student-id="${studentId}"]`
        );

        if (!row) {
            return;
        }

        const ca1 =
            Number(row.querySelector('[data-field="ca1"]')?.value) || 0;

        const ca2 =
            Number(row.querySelector('[data-field="ca2"]')?.value) || 0;

        const exam =
            Number(row.querySelector('[data-field="exam"]')?.value) || 0;

        const total = ca1 + ca2 + exam;

        if (ca1 > Number(subject.max_ca1)) {
            alert(`CA1 cannot be more than ${subject.max_ca1}.`);
            return;
        }

        if (ca2 > Number(subject.max_ca2)) {
            alert(`CA2 cannot be more than ${subject.max_ca2}.`);
            return;
        }

        if (exam > Number(subject.max_exam)) {
            alert(`Exam cannot be more than ${subject.max_exam}.`);
            return;
        }

        const originalText = button.textContent;

        button.disabled = true;
        button.textContent = "Saving...";

        try {
            const response = await fetch("./api/results/save.php", {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    class_id: Number(classId),
                    student_id: Number(studentId),
                    subject_id: Number(subject.id),
                    ca1,
                    ca2,
                    exam
                })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(
                    data.message || "Unable to save result."
                );
            }

            const existingIndex = classResults.findIndex(
                result =>
                    String(result.student_id) === String(studentId) &&
                    String(result.subject_id) === String(subject.id)
            );

            const savedResult = {
                student_id: Number(studentId),
                subject_id: Number(subject.id),
                ca1,
                ca2,
                exam,
                total
            };

            if (existingIndex >= 0) {
                classResults[existingIndex] = {
                    ...classResults[existingIndex],
                    ...savedResult
                };
            } else {
                classResults.push(savedResult);
            }

            calculateCompletion();

            if (resultsMessage) {
                resultsMessage.textContent =
                    "Result saved successfully.";
                resultsMessage.className =
                    "results-message success";
            }

            button.textContent = "Saved";

            setTimeout(() => {
                button.textContent = originalText;
                button.disabled = false;
            }, 1200);

        } catch (error) {
            console.error("Save result error:", error);

            if (resultsMessage) {
                resultsMessage.textContent =
                    error.message || "Unable to save result.";
                resultsMessage.className =
                    "results-message error";
            }

            button.textContent = "Retry";
            button.disabled = false;
        }
    }

    resultsTableContainer?.addEventListener("click", event => {
        const button = event.target.closest(".save-result-button");

        if (!button) {
            return;
        }

        const studentId = button.dataset.studentId;

        if (!studentId) {
            return;
        }

        saveResult(Number(studentId), button);
    });

    resultsTableContainer?.addEventListener("input", event => {
        if (!event.target.classList.contains("result-score-input")) {
            return;
        }

        updateLiveTotals();
    });

    editClassButton?.addEventListener("click", openEditClass);

    addStudentButton?.addEventListener("click", openAddStudent);

    panelAddStudentButton?.addEventListener("click", openAddStudent);

    classStudentsContainer?.addEventListener("click", event => {
        const button = event.target.closest(".class-student-button");

        if (!button) {
            return;
        }

        openStudent(button.dataset.studentId);
    });

    enterResultsButton?.addEventListener("click", () => {
        alert("Result entry will be connected here after the class workspace is completed.");
    });

    printClassResultsButton?.addEventListener("click", () => {
        alert("Class result printing will be connected here after the result module is completed.");
    });

    initialize();
})();
