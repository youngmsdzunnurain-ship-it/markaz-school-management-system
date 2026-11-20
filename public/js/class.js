(() => {
    "use strict";

    const params = new URLSearchParams(window.location.search);
    const classId = params.get("id") || params.get("class_id");
    const classType = params.get("class_type") === "arabic" ? "arabic" : "conventional";

    const classTitle = document.getElementById("classTitle");
    const classDescription = document.getElementById("classDescription");
    const studentCount = document.getElementById("studentCount");
    const classStatus = document.getElementById("classStatus");
    const classStudentsContainer = document.getElementById("classStudentsContainer");

    const editClassButton = document.getElementById("editClassButton");
    const addStudentButton = document.getElementById("addStudentButton");
    const panelAddStudentButton = document.getElementById("panelAddStudentButton");
    const enterResultsButton = document.getElementById("enterResultsButton");

    const resultsPeriodText = document.getElementById("resultsPeriodText");
    const resultsStudentCount = document.getElementById("resultsStudentCount");
    const resultsSubjectCount = document.getElementById("resultsSubjectCount");
    const resultsCompletion = document.getElementById("resultsCompletion");
    const resultSubjectSelect = document.getElementById("resultSubjectSelect");
    const resultStudentSearch = document.getElementById("resultStudentSearch");
    const resultsTableContainer = document.getElementById("resultsTableContainer");
    const resultsMessage = document.getElementById("resultsMessage");

    let selectedClass = null;
    let currentRole = "";
    let classStudents = [];
    let classRoster = [];
    let classRosterLoaded = false;
    let resultSubjects = [];
    let classResults = [];
    let selectedSubjectId = "";
    let resultsLoadSequence = 0;

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

        const response = await fetch(
            `./api/results/class.php?class_id=${encodeURIComponent(classId)}&class_type=${encodeURIComponent(classType)}`,
            { credentials: "same-origin", cache: "no-store" }
        );
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to load the assigned class.");
        }

        selectedClass = {
            ...data.class,
            id: data.class?.id ?? Number(classId),
            name: data.class?.name || "Class",
            class_type: classType
        };

        const displayedClassName = String(selectedClass.name || "").trim();
        const classLabel = /^ss\s*1$/i.test(displayedClassName)
            ? "SS1 Science"
            : (displayedClassName || "Class");
        classTitle.textContent = classLabel;
        classDescription.textContent = classType === "arabic"
            ? "Arabic class"
            : (selectedClass.level_group || "Academic class");
        classStatus.textContent = Number(selectedClass.is_active) === 1
            ? "Active"
            : "Inactive";
        document.title = `${classLabel} | MARKAZ`;

        if (classType === "arabic") {
            [editClassButton, addStudentButton, panelAddStudentButton].forEach(button => {
                if (button) button.hidden = true;
            });
        }

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

        const studentClassField = classType === "arabic"
            ? "arabic_class_id"
            : "class_id";

        classStudents = (data.students || []).filter(
            student =>
                String(student[studentClassField]) === String(selectedClass.id)
        );

        studentCount.textContent = classStudents.length;

        renderStudents();
    }

    function renderStudents() {
        if (!classRoster.length) {
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
                        ${classRoster.map(student => {
                            const fullName = [
                                student.first_name,
                                student.last_name
                            ].filter(Boolean).join(" ");

                            const active =
                                Number(student.is_active) === 1;

                            return `
                                <tr>
                                    <td data-label="Admission No.">
                                        ${escapeHtml(student.admission_number)}
                                    </td>

                                    <td data-label="Student">
                                        <strong>
                                            ${escapeHtml(fullName)}
                                        </strong>
                                    </td>

                                    <td data-label="Gender">
                                        ${escapeHtml(student.gender || "—")}
                                    </td>

                                    <td data-label="Arabic Class">
                                        ${escapeHtml(
                                            student.arabic_class_name || "—"
                                        )}
                                    </td>

                                    <td data-label="Status">
                                        <span class="student-status ${
                                            active ? "active" : "inactive"
                                        }">
                                            ${active ? "Active" : "Inactive"}
                                        </span>
                                    </td>

                                    <td data-label="Action">
                                        <button
                                            type="button"
                                            class="class-student-button"
                                            data-student-id="${escapeHtml(student.id)}">
                                            View Results
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
        const requestSequence = ++resultsLoadSequence;
        const response = await fetch(
            `./api/results/class.php?class_id=${encodeURIComponent(classId)}&class_type=${encodeURIComponent(classType)}`,
            {
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        const data = await response.json();

        if (requestSequence !== resultsLoadSequence) {
            return;
        }

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "Unable to load class results."
            );
        }

        resultSubjects = data.subjects || [];
        classResults = data.results || [];

        if (Array.isArray(data.roster)) {
            const incomingRoster = data.roster;
            // Accept the first response even when the class is empty, then ignore
            // transient empty refreshes so the visible roster does not flash away.
            if (incomingRoster.length > 0 || !classRosterLoaded) {
                const rosterChanged =
                    !classRosterLoaded ||
                    JSON.stringify(incomingRoster) !== JSON.stringify(classRoster);
                classRoster = incomingRoster;
                classRosterLoaded = true;
                studentCount.textContent = classRoster.length;
                if (rosterChanged) {
                    renderStudents();
                }
            }
        }
        if (Array.isArray(data.students)) {
            classStudents = data.students;
            if (resultsStudentCount) {
                resultsStudentCount.textContent = classStudents.length;
            }
        }

        if (resultsSubjectCount) {
            resultsSubjectCount.textContent = resultSubjects.length;
        }

        if (resultsPeriodText && data.academic_period) {
            resultsPeriodText.textContent =
                `${data.academic_period.session_name} • ${data.academic_period.term_name}`;
        }

        populateSubjectSelect();
        await calculateCompletion();
        configureResultsScanner();
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

        const previousSubjectId = selectedSubjectId;
        resultSubjectSelect.innerHTML = `
            <option value="">Select a subject</option>
            ${resultSubjects.map(subject => `
                <option value="${escapeHtml(subject.id)}">
                    ${escapeHtml(subject.name)}
                </option>
            `).join("")}
        `;
        if (resultSubjects.some(subject => String(subject.id) === String(previousSubjectId))) {
            resultSubjectSelect.value = previousSubjectId;
        }
        selectedSubjectId = resultSubjectSelect.value;
    }

    async function calculateCompletion() {
        const bars = document.getElementById("studentCompletionChart");
        const donut = document.getElementById("classCompletionDonut");
        const donutValue = document.getElementById("classCompletionDonutValue");

        try {
            const response = await fetch(
                `./api/results/class-completion.php?class_id=${encodeURIComponent(classId)}&class_type=${encodeURIComponent(classType)}`,
                { credentials: "same-origin", cache: "no-store" }
            );
            const data = await response.json();
            if (!response.ok || !data.success || !Array.isArray(data.students)) {
                throw new Error(data.message || "Unable to load completion details.");
            }

            const rows = data.students;
            const required = rows.reduce(
                (sum, item) => sum + Number(item.required_fields || 0), 0
            );
            const completed = rows.reduce(
                (sum, item) => sum + Number(item.completed_fields || 0), 0
            );
            const percentage = required ? Math.round(completed / required * 100) : 0;

            if (resultsCompletion) resultsCompletion.textContent = `${percentage}%`;

            if (bars) {
                bars.innerHTML = rows.length ? rows.map(item => {
                    const name = [
                        item.first_name, item.middle_name, item.last_name
                    ].filter(Boolean).join(" ");
                    const pct = Number(item.percentage) || 0;
                    return `<div class="completion-bar-row">
                        <span class="completion-bar-name" title="${escapeHtml(name)}">${escapeHtml(name || "Student")}</span>
                        <div class="completion-bar-track"><span style="width:${pct}%"></span></div>
                        <strong>${pct}%</strong>
                    </div>`;
                }).join("") : '<p class="empty-state">No students to display yet.</p>';
            }

            if (donut) {
                donut.style.setProperty("--completion", `${percentage}%`);
                donut.setAttribute("aria-label", `Class result completion ${percentage}%`);
            }
            if (donutValue) donutValue.textContent = `${percentage}%`;

        } catch (error) {
            console.error("Class completion monitor error:", error);
            if (resultsCompletion) resultsCompletion.textContent = "—";
            if (bars) bars.innerHTML =
                '<p class="empty-state">Completion details could not be loaded. Refresh to try again.</p>';
            if (donut) donut.setAttribute("aria-label", "Class result completion unavailable");
            if (donutValue) donutValue.textContent = "—";
        }
    }

    function getSelectedSubject() {
        return resultSubjects.find(
            subject => String(subject.id) === String(selectedSubjectId)
        ) || null;
    }

    function configureResultsScanner() {
        if (!window.MarkazResultsScanner) return;
        window.MarkazResultsScanner.configure({
            classId,
            classType,
            role: currentRole,
            subject: getSelectedSubject(),
            students: classStudents,
            results: classResults,
            onSaved: async () => {
                await loadResults();
                renderResultsTable();
            }
        });
    }

    function getStudentResult(studentId, subjectId) {
        return classResults.find(
            result =>
                String(result.student_id) === String(studentId) &&
                String(result.subject_id) === String(subjectId)
        ) || null;
    }

    function renderStudentSuggestions() {
        const box = document.getElementById("resultStudentSuggestions");
        if (!box) return;

        if (!document.getElementById("resultStudentSuggestionStyles")) {
            const style = document.createElement("style");
            style.id = "resultStudentSuggestionStyles";
            style.textContent = `
                .result-search-field { position: relative; }
                .result-student-suggestions {
                    position: absolute; top: 100%; left: 0; right: 0;
                    z-index: 1000; max-height: 260px; overflow-y: auto;
                    background: #fff; color: #222; border: 1px solid #ccc;
                    border-radius: 8px; box-shadow: 0 6px 18px #0002;
                }
                .result-student-suggestions[hidden] { display: none; }
                .result-student-suggestion {
                    display: block; width: 100%; padding: 10px 12px;
                    border: 0; border-bottom: 1px solid #eee;
                    background: #fff; color: #222; text-align: left;
                    cursor: pointer;
                }
                .result-student-suggestion:hover,
                .result-student-suggestion:focus { background: #f0f5f0; }
                .result-student-suggestion small {
                    display: block; margin-top: 3px; color: #666;
                }
            `;
            document.head.appendChild(style);
        }

        const term = (resultStudentSearch?.value || "").trim().toLowerCase();
        if (!term) {
            box.hidden = true;
            box.innerHTML = "";
            resultStudentSearch?.setAttribute("aria-expanded", "false");
            return;
        }

        const matches = classStudents.filter(student => {
            const name = [
                student.first_name, student.middle_name, student.last_name
            ].filter(Boolean).join(" ").toLowerCase();
            const admission = String(student.admission_number || "").toLowerCase();
            return name.includes(term) || admission.includes(term);
        }).slice(0, 8);

        if (!matches.length) {
            box.innerHTML = '<div class="result-student-suggestion">No matching students found.</div>';
        } else {
            box.innerHTML = matches.map(student => {
                const name = [
                    student.first_name, student.middle_name, student.last_name
                ].filter(Boolean).join(" ");
                return `
                    <button type="button" class="result-student-suggestion"
                        data-student-id="${escapeHtml(student.id)}">
                        <strong>${escapeHtml(name)}</strong>
                        <small>Admission No.: ${escapeHtml(student.admission_number || "—")}</small>
                    </button>`;
            }).join("");
        }
        box.hidden = false;
        resultStudentSearch?.setAttribute("aria-expanded", "true");
    }

    function renderResultsTable() {
        renderStudentSuggestions();

        if (!resultsTableContainer) {
            return;
        }

        const subject = getSelectedSubject();

        if (!subject) {
            resultsTableContainer.innerHTML = `
                <div class="empty-state">
                    Select a subject and search for a student to enter results.
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

                            const ca1 = result?.ca1 ?? "";
                            const ca2 = result?.ca2 ?? "";
                            const exam = result?.exam ?? "";
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
            `./student-result.html?student_id=${encodeURIComponent(studentId)}&class_id=${encodeURIComponent(classId)}&class_type=${encodeURIComponent(classType)}&display_version=20261106-targeted-student-display-fix`;
    }

    classStudentsContainer.addEventListener("click", event => {
        const button = event.target.closest(".class-student-button");
        if (button && currentRole !== "teacher") {
            openStudent(button.dataset.studentId);
        }
    });

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
            const identityResponse = await fetch("./api/auth/me.php", {
                credentials: "same-origin",
                cache: "no-store"
            });
            const identity = await identityResponse.json();
            currentRole = identity?.admin?.role || "";

            if (currentRole === "teacher") {
                [editClassButton, addStudentButton, panelAddStudentButton].forEach(button => {
                    if (button) button.hidden = true;
                });
            }

            const classLoaded = await loadClass();

            if (!classLoaded) {
                return;
            }

            // The class-results endpoint already returns its scoped student roster.
            // Do not let a separate roster request failure prevent subject options loading.
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
        configureResultsScanner();
    });

    resultStudentSearch?.addEventListener("input", () => {
        renderResultsTable();
    });

    document.getElementById("resultStudentSuggestions")?.addEventListener("click", event => {
        const button = event.target.closest("[data-student-id]");
        if (button) openStudent(button.dataset.studentId);
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

        const readScore = field => {
            const value = row.querySelector(`[data-field="${field}"]`)?.value ?? "";
            return value.trim() === "" ? null : Number(value);
        };
        const ca1 = readScore("ca1");
        const ca2 = readScore("ca2");
        const exam = readScore("exam");
        const total = (ca1 ?? 0) + (ca2 ?? 0) + (exam ?? 0);

        if (ca1 !== null && ca1 > Number(subject.max_ca1)) {
            alert(`CA1 cannot be more than ${subject.max_ca1}.`);
            return;
        }

        if (ca2 !== null && ca2 > Number(subject.max_ca2)) {
            alert(`CA2 cannot be more than ${subject.max_ca2}.`);
            return;
        }

        if (exam !== null && exam > Number(subject.max_exam)) {
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
                    class_type: classType,
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

            // Wait for the server-backed completion summary to refresh before
            // reporting the save as fully reflected in the class workspace.
            await calculateCompletion();

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

    enterResultsButton?.addEventListener("click", () => {
        const resultsSection = document.querySelector(".results-entry-table")?.closest("section, .panel, .card");
        if (resultsSection) {
            resultsSection.scrollIntoView({ behavior: "smooth", block: "start" });
            resultSubjectSelect?.focus();
        } else {
            document.querySelector(".results-entry-table")?.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    });

    window.addEventListener("focus", async () => {
        if (document.visibilityState === "hidden" || !classId) return;
        try {
            await loadResults();
        } catch (error) {
            console.error("Class workspace refresh error:", error);
        }
    });

    initialize();
})();
