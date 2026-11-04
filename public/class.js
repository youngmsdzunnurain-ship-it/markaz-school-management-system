(() => {
    "use strict";

    const params = new URLSearchParams(window.location.search);
    const classId = params.get("id");

    const classTitle = document.getElementById("classTitle");
    const classDescription = document.getElementById("classDescription");
    const studentCount = document.getElementById("studentCount");
    const classStatus = document.getElementById("classStatus");
    const classStudentsContainer =
        document.getElementById("classStudentsContainer");

    const editClassButton =
        document.getElementById("editClassButton");

    const addStudentButton =
        document.getElementById("addStudentButton");

    const panelAddStudentButton =
        document.getElementById("panelAddStudentButton");

    const resultsPeriodText =
        document.getElementById("resultsPeriodText");

    const resultsStudentCount =
        document.getElementById("resultsStudentCount");

    const resultsSubjectCount =
        document.getElementById("resultsSubjectCount");

    const resultsCompletion =
        document.getElementById("resultsCompletion");

    const resultSubjectSelect =
        document.getElementById("resultSubjectSelect");

    const resultStudentSearch =
        document.getElementById("resultStudentSearch");

    const resultsTableContainer =
        document.getElementById("resultsTableContainer");

    const printClassResultsButton =
        document.getElementById("printClassResultsButton");

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

    function getFullName(student) {
        return [
            student.first_name,
            student.middle_name,
            student.last_name
        ].filter(Boolean).join(" ");
    }

    function showError(message) {
        classTitle.textContent = "Unable to load class";
        classDescription.textContent = message;
        studentCount.textContent = "—";
        classStatus.textContent = "—";

        classStudentsContainer.innerHTML =
            `<div class="empty-state">${escapeHtml(message)}</div>`;

        if (resultsTableContainer) {
            resultsTableContainer.innerHTML =
                `<div class="empty-state">${escapeHtml(message)}</div>`;
        }
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
            throw new Error(
                data.message || "Unable to load classes."
            );
        }

        selectedClass = (data.classes || []).find(
            item => String(item.id) === String(classId)
        );

        if (!selectedClass) {
            showError("The selected class could not be found.");
            return false;
        }

        const displayedClassName = String(selectedClass.name || "").trim();
        const classLabel = /^ss\s*1$/i.test(displayedClassName)
            ? "SS1 Science"
            : (displayedClassName || "Class");
        classTitle.textContent = classLabel;

        classDescription.textContent =
            selectedClass.level_group || "Academic class";

        classStatus.textContent =
            Number(selectedClass.is_active) === 1
                ? "Active"
                : "Inactive";

        document.title =
            `${classLabel} | MARKAZ`;

        return true;
    }

    async function loadStudents() {
        const response = await fetch(
            "./api/students/list.php",
            {
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "Unable to load students."
            );
        }

        classStudents = (data.students || []).filter(
            student =>
                String(student.class_id) ===
                String(selectedClass.id)
        );

        studentCount.textContent =
            classStudents.length;

        if (resultsStudentCount) {
            resultsStudentCount.textContent =
                classStudents.length;
        }

        renderStudents();
    }

    function renderStudents() {
        if (!classStudents.length) {
            classStudentsContainer.innerHTML =
                `<div class="empty-state">
                    No students have been added to this class yet.
                </div>`;

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
                            const active =
                                Number(student.is_active) === 1;

                            return `
                                <tr>
                                    <td>
                                        ${escapeHtml(
                                            student.admission_number
                                        )}
                                    </td>

                                    <td>
                                        <strong>
                                            ${escapeHtml(
                                                getFullName(student)
                                            )}
                                        </strong>
                                    </td>

                                    <td>
                                        ${escapeHtml(
                                            student.gender || "—"
                                        )}
                                    </td>

                                    <td>
                                        ${escapeHtml(
                                            student.arabic_class_name || "—"
                                        )}
                                    </td>

                                    <td>
                                        <span class="student-status ${
                                            active
                                                ? "active"
                                                : "inactive"
                                        }">
                                            ${
                                                active
                                                    ? "Active"
                                                    : "Inactive"
                                            }
                                        </span>
                                    </td>

                                    <td>
                                        <button
                                            type="button"
                                            class="class-student-button"
                                            data-student-id="${escapeHtml(
                                                student.id
                                            )}">
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

        if (resultsPeriodText && data.academic_period) {
            resultsPeriodText.textContent =
                `${data.academic_period.session_name} • ` +
                `${data.academic_period.term_name}`;
        }

        if (resultsSubjectCount) {
            resultsSubjectCount.textContent =
                resultSubjects.length;
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
                `<option value="">No subjects assigned</option>`;

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
            classStudents.length *
            resultSubjects.length;

        if (!total) {
            resultsCompletion.textContent = "0%";
            return;
        }

        const completed =
            classResults.length;

        const percentage =
            Math.min(
                100,
                Math.round((completed / total) * 100)
            );

        resultsCompletion.textContent =
            `${percentage}%`;
    }

    function getSelectedSubject() {
        return resultSubjects.find(
            subject =>
                String(subject.id) ===
                String(selectedSubjectId)
        );
    }

    function getStudentResult(studentId, subjectId) {
        return classResults.find(result =>
            String(result.student_id) === String(studentId) &&
            String(result.subject_id) === String(subjectId)
        );
    }

    function renderResultsTable() {
        if (!resultsTableContainer) {
            return;
        }

        const subject = getSelectedSubject();

        if (!subject) {
            resultsTableContainer.innerHTML = `
                <div class="empty-state">
                    Select a subject to begin entering results.
                </div>
            `;
            return;
        }

        const searchTerm =
            (resultStudentSearch?.value || "")
                .trim()
                .toLowerCase();

        const students = classStudents.filter(student => {
            const name =
                getFullName(student).toLowerCase();

            const admission =
                String(
                    student.admission_number || ""
                ).toLowerCase();

            return (
                !searchTerm ||
                name.includes(searchTerm) ||
                admission.includes(searchTerm)
            );
        });

        if (!students.length) {
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
                            <th>Total / 100</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        ${students.map(student => {
                            const result =
                                getStudentResult(
                                    student.id,
                                    subject.id
                                );

                            const ca1 =
                                result?.ca1 ?? "";

                            const ca2 =
                                result?.ca2 ?? "";

                            const exam =
                                result?.exam ?? "";

                            const total =
                                result?.total ?? "0";

                            return `
                                <tr
                                    data-student-result-row="${escapeHtml(
                                        student.id
                                    )}">

                                    <td>
                                        <strong>
                                            ${escapeHtml(
                                                getFullName(student)
                                            )}
                                        </strong>
                                    </td>

                                    <td>
                                        ${escapeHtml(
                                            student.admission_number
                                        )}
                                    </td>

                                    <td>
                                        <input
                                            type="number"
                                            class="result-score-input"
                                            data-field="ca1"
                                            min="0"
                                            max="${escapeHtml(
                                                subject.max_ca1
                                            )}"
                                            step="0.01"
                                            value="${escapeHtml(ca1)}">
                                    </td>

                                    <td>
                                        <input
                                            type="number"
                                            class="result-score-input"
                                            data-field="ca2"
                                            min="0"
                                            max="${escapeHtml(
                                                subject.max_ca2
                                            )}"
                                            step="0.01"
                                            value="${escapeHtml(ca2)}">
                                    </td>

                                    <td>
                                        <input
                                            type="number"
                                            class="result-score-input"
                                            data-field="exam"
                                            min="0"
                                            max="${escapeHtml(
                                                subject.max_exam
                                            )}"
                                            step="0.01"
                                            value="${escapeHtml(exam)}">
                                    </td>

                                    <td>
                                        <strong class="result-total">
                                            ${escapeHtml(total)}
                                        </strong>
                                    </td>

                                    <td>
                                        <button
                                            type="button"
                                            class="primary-button save-result-button"
                                            data-student-id="${escapeHtml(
                                                student.id
                                            )}">
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
            .querySelectorAll(
                "[data-student-result-row]"
            )
            .forEach(row => {

                const ca1 =
                    Number(
                        row.querySelector(
                            '[data-field="ca1"]'
                        )?.value
                    ) || 0;

                const ca2 =
                    Number(
                        row.querySelector(
                            '[data-field="ca2"]'
                        )?.value
                    ) || 0;

                const exam =
                    Number(
                        row.querySelector(
                            '[data-field="exam"]'
                        )?.value
                    ) || 0;

                const total =
                    ca1 + ca2 + exam;

                const totalElement =
                    row.querySelector(
                        ".result-total"
                    );

                if (totalElement) {
                    totalElement.textContent =
                        Number.isInteger(total)
                            ? String(total)
                            : total.toFixed(2);
                }
            });
    }

    function openStudent(studentId) {
        window.location.href =
            `./student-result.html?student_id=${
                encodeURIComponent(studentId)
            }`;
    }

    function openAddStudent() {
        if (!selectedClass) {
            return;
        }

        window.location.href =
            `./students.html?class_id=${
                encodeURIComponent(selectedClass.id)
            }`;
    }

    function openEditClass() {
        if (!selectedClass) {
            return;
        }

        window.location.href =
            `./classes.html?edit_id=${
                encodeURIComponent(selectedClass.id)
            }`;
    }

    async function initialize() {
        try {
            const classLoaded =
                await loadClass();

            if (!classLoaded) {
                return;
            }

            await loadStudents();
            await loadResults();

        } catch (error) {
            console.error(
                "Class workspace error:",
                error
            );

            showError(
                error.message ||
                "Something went wrong while loading this class."
            );
        }
    }

    editClassButton?.addEventListener(
        "click",
        openEditClass
    );

    addStudentButton?.addEventListener(
        "click",
        openAddStudent
    );

    panelAddStudentButton?.addEventListener(
        "click",
        openAddStudent
    );

    classStudentsContainer?.addEventListener(
        "click",
        event => {
            const button =
                event.target.closest(
                    ".class-student-button"
                );

            if (!button) {
                return;
            }

            openStudent(
                button.dataset.studentId
            );
        }
    );

    resultSubjectSelect?.addEventListener(
        "change",
        () => {
            selectedSubjectId =
                resultSubjectSelect.value || "";

            renderResultsTable();
        }
    );

    resultStudentSearch?.addEventListener(
        "input",
        () => {
            renderResultsTable();
        }
    );

    resultsTableContainer?.addEventListener(
        "input",
        event => {
            if (
                event.target.matches(
                    ".result-score-input"
                )
            ) {
                updateLiveTotals();
            }
        }
    );

    resultsTableContainer?.addEventListener(
        "click",
        async event => {
            const button =
                event.target.closest(
                    ".save-result-button"
                );

            if (!button) {
                return;
            }

            const row =
                button.closest(
                    "[data-student-result-row]"
                );

            const subject =
                getSelectedSubject();

            if (!row || !subject || !selectedClass) {
                return;
            }

            const studentId =
                button.dataset.studentId;

            const ca1 =
                Number(
                    row.querySelector(
                        '[data-field="ca1"]'
                    )?.value
                ) || 0;

            const ca2 =
                Number(
                    row.querySelector(
                        '[data-field="ca2"]'
                    )?.value
                ) || 0;

            const exam =
                Number(
                    row.querySelector(
                        '[data-field="exam"]'
                    )?.value
                ) || 0;

            const total =
                ca1 + ca2 + exam;

            if (
                ca1 > Number(subject.max_ca1) ||
                ca2 > Number(subject.max_ca2) ||
                exam > Number(subject.max_exam)
            ) {
                alert(
                    "One or more scores exceed the allowed maximum."
                );
                return;
            }

            button.disabled = true;
            button.textContent = "Saving...";

            try {
                const response =
                    await fetch(
                        "./api/results/save.php",
                        {
                            method: "POST",
                            credentials: "same-origin",
                            headers: {
                                "Content-Type":
                                    "application/json"
                            },
                            body: JSON.stringify({
                                class_type: "conventional",
                                class_id:
                                    Number(
                                        selectedClass.id
                                    ),
                                student_id:
                                    Number(studentId),
                                subject_id:
                                    Number(subject.id),
                                ca1,
                                ca2,
                                exam
                            })
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
                        "Unable to save result."
                    );
                }

                const savedResult = {
                    student_id:
                        Number(studentId),
                    subject_id:
                        Number(subject.id),
                    ca1,
                    ca2,
                    exam,
                    total:
                        data.result?.total ??
                        total
                };

                const existingIndex =
                    classResults.findIndex(
                        result =>
                            String(
                                result.student_id
                            ) ===
                            String(studentId) &&
                            String(
                                result.subject_id
                            ) ===
                            String(subject.id)
                    );

                if (existingIndex >= 0) {
                    classResults[
                        existingIndex
                    ] = {
                        ...classResults[
                            existingIndex
                        ],
                        ...savedResult
                    };
                } else {
                    classResults.push(
                        savedResult
                    );
                }

                calculateCompletion();

                const totalElement =
                    row.querySelector(
                        ".result-total"
                    );

                if (totalElement) {
                    totalElement.textContent =
                        String(
                            savedResult.total
                        );
                }

                button.textContent =
                    "Saved";

                setTimeout(() => {
                    button.textContent =
                        "Save";
                }, 1500);

            } catch (error) {
                console.error(
                    "Save result error:",
                    error
                );

                alert(
                    error.message ||
                    "Unable to save result."
                );

                button.textContent =
                    "Save";

            } finally {
                button.disabled = false;
            }
        }
    );

    printClassResultsButton?.addEventListener(
        "click",
        () => {
            window.print();
        }
    );

    initialize();
})();
