(() => {
    "use strict";

    const $ = (id) => document.getElementById(id);
    const state = {
        classes: [],
        students: [],
        role: "",
        classType: "conventional",
        classId: "",
        date: "",
        dirty: false,
        loading: false,
        saving: false,
    };

    const els = {
        date: $("attendanceDate"),
        type: $("attendanceClassType"),
        classSelect: $("attendanceClass"),
        load: $("loadRegisterButton"),
        body: $("attendanceTableBody"),
        search: $("studentSearch"),
        markAll: $("markAllPresentButton"),
        clear: $("clearMarksButton"),
        save: $("saveAttendanceButton"),
        print: $("printRegisterButton"),
        export: $("exportRegisterButton"),
        status: $("saveStatus"),
        subtitle: $("registerSubtitle"),
        help: $("classHelpText"),
        today: $("todayLabel"),
        progressBar: $("progressBar"),
        progressLabel: $("progressLabel"),
    };

    function localDateValue(date = new Date()) {
        const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
        return local.toISOString().slice(0, 10);
    }

    function formatDate(dateString) {
        const date = new Date(dateString + "T12:00:00");
        return Number.isNaN(date.getTime())
            ? dateString
            : new Intl.DateTimeFormat(undefined, { day: "numeric", month: "short", year: "numeric" }).format(date);
    }

    function escapeHtml(value) {
        return String(value ?? "").replace(/[&<>"']/g, (char) => ({
            "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
        })[char]);
    }

    function fullName(student) {
        return [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(" ").trim();
    }

    function initials(student) {
        return ((student.first_name || "").charAt(0) + (student.last_name || "").charAt(0)).toUpperCase() || "ST";
    }

    async function requestJson(url, options = {}) {
        const response = await fetch(url, {
            credentials: "same-origin",
            cache: "no-store",
            ...options,
            headers: {
                Accept: "application/json",
                ...(options.headers || {}),
            },
        });
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch {
            throw new Error("The server returned an unexpected response. Please sign in again or contact the administrator.");
        }
        if (!response.ok || !data.success) {
            if (response.status === 401) {
                throw new Error("Your session has expired. Return to the dashboard and sign in again.");
            }
            throw new Error(data.message || "The attendance request could not be completed.");
        }
        return data;
    }

    function setNotice(message, kind = "") {
        els.status.textContent = message;
        els.status.classList.toggle("is-success", kind === "success");
        els.status.classList.toggle("is-error", kind === "error");
    }

    function visibleStudents() {
        const term = els.search.value.trim().toLocaleLowerCase();
        if (!term) return state.students;
        return state.students.filter((student) => {
            const name = fullName(student).toLocaleLowerCase();
            const admission = String(student.admission_number || "").toLocaleLowerCase();
            return name.includes(term) || admission.includes(term);
        });
    }

    function updateSummary() {
        const summary = { total: state.students.length, present: 0, absent: 0, late: 0, excused: 0, unmarked: 0 };
        state.students.forEach((student) => {
            const status = student.attendance_status || "";
            if (Object.prototype.hasOwnProperty.call(summary, status) && status !== "total") summary[status]++;
            else summary.unmarked++;
        });
        $("totalCount").textContent = summary.total;
        $("presentCount").textContent = summary.present;
        $("absentCount").textContent = summary.absent;
        $("otherCount").textContent = summary.late + summary.excused;
        const marked = summary.total - summary.unmarked;
        const percent = summary.total ? Math.round(marked / summary.total * 100) : 0;
        els.progressBar.style.width = percent + "%";
        els.progressLabel.textContent = marked + " of " + summary.total + " marked";
        return summary;
    }

    function statusOptions(current) {
        const options = [
            ["", "Choose status"],
            ["present", "Present"],
            ["absent", "Absent"],
            ["late", "Late"],
            ["excused", "Excused"],
        ];
        return options.map(([value, label]) =>
            '<option value="' + value + '"' + (value === current ? " selected" : "") + '>' + label + "</option>"
        ).join("");
    }

    function renderRegister() {
        const students = visibleStudents();
        if (!state.classId || !state.date) {
            els.body.innerHTML = '<tr><td colspan="5" class="register-empty"><span class="empty-icon"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></span><strong>Your register is waiting</strong><span>Choose a class and date above, then load the register.</span></td></tr>';
            return;
        }
        if (!students.length) {
            els.body.innerHTML = '<tr><td colspan="5" class="register-empty"><span class="empty-icon"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span><strong>No students found</strong><span>Try another name or admission number.</span></td></tr>';
            return;
        }
        els.body.innerHTML = students.map((student, index) => {
            const name = fullName(student);
            const status = student.attendance_status || "";
            const statusClass = status ? " status-" + status : "";
            return '<tr data-student-row="' + student.id + '">' +
                '<td>' + (state.students.indexOf(student) + 1) + '</td>' +
                '<td><div class="student-name-cell"><span class="student-avatar" aria-hidden="true">' + escapeHtml(initials(student)) + '</span><span class="student-name-copy"><strong>' + escapeHtml(name) + '</strong><span>' + escapeHtml(student.gender || "Gender not recorded") + (student.student_type ? " · " + escapeHtml(student.student_type) : "") + '</span></span></div></td>' +
                '<td><span class="admission-number">' + escapeHtml(student.admission_number || "—") + '</span></td>' +
                '<td><label class="sr-only" for="status-' + student.id + '">Attendance status for ' + escapeHtml(name) + '</label><select class="attendance-status-select' + statusClass + '" id="status-' + student.id + '" data-status-id="' + student.id + '" ' + (state.saving ? "disabled" : "") + '>' + statusOptions(status) + '</select></td>' +
                '<td><label class="sr-only" for="remarks-' + student.id + '">Optional note for ' + escapeHtml(name) + '</label><input class="attendance-remarks" id="remarks-' + student.id + '" data-remarks-id="' + student.id + '" type="text" maxlength="500" placeholder="Add a short note…" value="' + escapeHtml(student.remarks || "") + '" ' + (state.saving ? "disabled" : "") + '></td>' +
                '</tr>';
        }).join("");
    }

    function refreshActions() {
        const hasRegister = Boolean(state.classId && state.date && state.students.length);
        const isBusy = state.loading || state.saving;
        els.search.disabled = !hasRegister || isBusy;
        els.markAll.disabled = !hasRegister || isBusy;
        els.clear.disabled = !hasRegister || isBusy;
        els.save.disabled = !hasRegister || isBusy;
        els.print.disabled = !hasRegister;
        els.export.disabled = !hasRegister;
        els.load.disabled = isBusy;
        els.type.disabled = isBusy;
        els.classSelect.disabled = isBusy || state.classes.filter((item) => item.class_type === els.type.value).length === 0;
        els.date.disabled = isBusy;
    }

    function populateClasses() {
        const type = els.type.value;
        const current = els.classSelect.value;
        const filtered = state.classes.filter((item) => item.class_type === type);
        els.classSelect.innerHTML = "";
        if (!filtered.length) {
            const option = document.createElement("option");
            option.value = "";
            option.textContent = type === "arabic" ? "No Arabic classes assigned" : "No conventional classes assigned";
            els.classSelect.appendChild(option);
            els.help.textContent = state.role === "teacher"
                ? "No classes of this category are assigned to your teacher account."
                : "Create or activate a class in Class Management before taking attendance.";
            state.classId = "";
            return;
        }
        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = "Select a class…";
        els.classSelect.appendChild(placeholder);
        filtered.forEach((item) => {
            const option = document.createElement("option");
            option.value = String(item.id);
            option.textContent = item.name + " · " + item.student_count + (item.student_count === 1 ? " student" : " students");
            els.classSelect.appendChild(option);
        });
        if (filtered.some((item) => String(item.id) === current)) {
            els.classSelect.value = current;
            state.classId = current;
        } else {
            els.classSelect.value = "";
            state.classId = "";
        }
        els.help.textContent = state.role === "teacher"
            ? "Only classes assigned to your teacher account are shown."
            : "Class options and student totals are loaded from the database.";
    }

    async function loadClasses() {
        setNotice("Loading available classes…");
        try {
            const data = await requestJson("./api/attendance/classes.php");
            state.classes = data.classes || [];
            state.role = data.role || "";
            populateClasses();
            if (!state.classes.length) {
                setNotice("No active classes are available to this account.", "error");
            } else {
                setNotice("Choose a class and date to load the register.");
            }
            refreshActions();
        } catch (error) {
            setNotice(error.message, "error");
            els.classSelect.innerHTML = '<option value="">Unable to load classes</option>';
            refreshActions();
        }
    }

    async function loadRegister() {
        if (state.loading || state.saving) return;
        const classId = els.classSelect.value;
        const date = els.date.value;
        const classType = els.type.value;
        if (!classId) {
            setNotice("Please choose a class first.", "error");
            els.classSelect.focus();
            return;
        }
        if (!date) {
            setNotice("Please choose an attendance date.", "error");
            els.date.focus();
            return;
        }
        if (date > localDateValue()) {
            setNotice("Attendance cannot be recorded for a future date.", "error");
            els.date.focus();
            return;
        }
        if (state.dirty && !window.confirm("You have unsaved attendance changes. Discard them and load another register?")) return;

        state.loading = true;
        state.classId = classId;
        state.classType = classType;
        state.date = date;
        refreshActions();
        els.body.innerHTML = '<tr><td colspan="5" class="register-empty"><span class="empty-icon"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i></span><strong>Loading register…</strong><span>Fetching the current class roster.</span></td></tr>';

        try {
            const query = new URLSearchParams({ class_id: classId, class_type: classType, date });
            const data = await requestJson("./api/attendance/register.php?" + query.toString());
            state.students = (data.students || []).map((student) => ({
                ...student,
                attendance_status: student.attendance_status || "",
                remarks: student.remarks || "",
            }));
            state.dirty = false;
            els.search.value = "";
            const typeLabel = classType === "arabic" ? "Arabic class" : "Conventional class";
            els.subtitle.textContent = data.class.name + " · " + typeLabel + " · " + formatDate(data.date);
            const summary = updateSummary();
            renderRegister();
            if (!state.students.length) {
                setNotice("This class has no active students to mark.", "error");
            } else if (summary.unmarked) {
                setNotice(summary.unmarked + " student" + (summary.unmarked === 1 ? " is" : "s are") + " still unmarked.");
            } else {
                setNotice("Register loaded. All " + summary.total + " students have an attendance status.", "success");
            }
        } catch (error) {
            state.students = [];
            state.dirty = false;
            updateSummary();
            renderRegister();
            setNotice(error.message, "error");
        } finally {
            state.loading = false;
            refreshActions();
        }
    }

    function handleRegisterChange(event) {
        const statusSelect = event.target.closest("[data-status-id]");
        const remarksInput = event.target.closest("[data-remarks-id]");
        if (!statusSelect && !remarksInput) return;
        const studentId = Number((statusSelect || remarksInput).dataset.statusId || (statusSelect || remarksInput).dataset.remarksId);
        const student = state.students.find((item) => Number(item.id) === studentId);
        if (!student) return;
        if (statusSelect) {
            student.attendance_status = statusSelect.value;
            statusSelect.className = "attendance-status-select" + (statusSelect.value ? " status-" + statusSelect.value : "");
        } else {
            student.remarks = remarksInput.value;
        }
        state.dirty = true;
        updateSummary();
        setNotice("Unsaved changes. Save attendance to keep your updates.");
    }

    function markAllPresent() {
        if (!state.students.length || state.saving) return;
        state.students.forEach((student) => { student.attendance_status = "present"; });
        state.dirty = true;
        renderRegister();
        updateSummary();
        setNotice("All " + state.students.length + " students marked present. Review the register before saving.");
    }

    function clearMarks() {
        if (!state.students.length || state.saving) return;
        if (!window.confirm("Clear attendance statuses and notes for every student in this register? This will not delete saved records until you press Save attendance.")) return;
        state.students.forEach((student) => {
            student.attendance_status = "";
            student.remarks = "";
        });
        state.dirty = true;
        renderRegister();
        updateSummary();
        setNotice("Marks cleared on screen. Save to apply the changes.");
    }

    async function saveAttendance() {
        if (state.saving || state.loading || !state.students.length) return;
        const unmarked = state.students.filter((student) => !student.attendance_status);
        if (unmarked.length) {
            setNotice("Mark an attendance status for all " + unmarked.length + " remaining student" + (unmarked.length === 1 ? "" : "s") + " before saving.", "error");
            const first = $("status-" + unmarked[0].id);
            if (first) first.focus();
            return;
        }
        if (!state.dirty) {
            setNotice("There are no unsaved changes.", "success");
            return;
        }

        state.saving = true;
        refreshActions();
        els.save.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i><span>Saving…</span>';
        setNotice("Saving attendance securely…");

        try {
            const records = state.students.map((student) => ({
                student_id: Number(student.id),
                status: student.attendance_status,
                remarks: student.remarks || "",
            }));
            const data = await requestJson("./api/attendance/register.php", {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-MARKAZ-Request": "attendance" },
                body: JSON.stringify({
                    date: state.date,
                    class_type: state.classType,
                    class_id: Number(state.classId),
                    records,
                }),
            });
            state.dirty = false;
            setNotice(data.message + " " + data.saved_count + " student records saved for " + formatDate(state.date) + ".", "success");
            updateSummary();
        } catch (error) {
            setNotice(error.message, "error");
        } finally {
            state.saving = false;
            els.save.innerHTML = '<i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><span>Save attendance</span>';
            renderRegister();
            refreshActions();
        }
    }

    function csvCell(value) {
        let text = String(value ?? "");
        if (/^[=+@\-\t\r]/.test(text)) text = "'" + text;
        return '"' + text.replace(/"/g, '""') + '"';
    }

    function exportCsv() {
        if (!state.students.length) return;
        const selectedClass = state.classes.find((item) => item.class_type === state.classType && String(item.id) === String(state.classId));
        const rows = [
            ["MARKAZ Daily Attendance"],
            ["Class", selectedClass ? selectedClass.name : ""],
            ["Class category", state.classType === "arabic" ? "Arabic" : "Conventional"],
            ["Date", state.date],
            [],
            ["S/N", "Student name", "Admission number", "Status", "Note"],
            ...state.students.map((student, index) => [
                index + 1, fullName(student), student.admission_number || "",
                student.attendance_status || "Unmarked", student.remarks || "",
            ]),
        ];
        const csv = "\uFEFF" + rows.map((row) => row.map(csvCell).join(",")).join("\r\n");
        const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = "MARKAZ-attendance-" + state.date + "-" + (selectedClass ? selectedClass.name : "class").replace(/[^a-z0-9_-]+/gi, "-") + ".csv";
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    function updateDateLabel() {
        const date = els.date.value || localDateValue();
        els.today.textContent = formatDate(date);
    }

    els.type.addEventListener("change", () => {
        if (state.dirty && !window.confirm("Discard unsaved changes and switch class category?")) {
            els.type.value = state.classType;
            return;
        }
        state.classType = els.type.value;
        state.classId = "";
        state.students = [];
        state.dirty = false;
        populateClasses();
        updateSummary();
        renderRegister();
        els.subtitle.textContent = "Select a class and date to begin.";
        setNotice("Choose a class and date to load the register.");
        refreshActions();
    });

    els.classSelect.addEventListener("change", () => {
        if (state.dirty && !window.confirm("Discard unsaved changes and choose another class?")) {
            els.classSelect.value = state.classId;
            return;
        }
        state.classId = els.classSelect.value;
        state.students = [];
        state.dirty = false;
        updateSummary();
        renderRegister();
        els.subtitle.textContent = "Select Load register to view students.";
        refreshActions();
    });

    els.date.addEventListener("change", () => {
        updateDateLabel();
        if (state.dirty && !window.confirm("Discard unsaved changes and change the date?")) {
            els.date.value = state.date;
            updateDateLabel();
            return;
        }
        state.date = els.date.value;
        state.students = [];
        state.dirty = false;
        updateSummary();
        renderRegister();
        refreshActions();
    });

    els.load.addEventListener("click", loadRegister);
    els.search.addEventListener("input", renderRegister);
    els.body.addEventListener("change", handleRegisterChange);
    els.body.addEventListener("input", handleRegisterChange);
    els.markAll.addEventListener("click", markAllPresent);
    els.clear.addEventListener("click", clearMarks);
    els.save.addEventListener("click", saveAttendance);
    els.print.addEventListener("click", () => window.print());
    els.export.addEventListener("click", exportCsv);

    window.addEventListener("beforeunload", (event) => {
        if (!state.dirty) return;
        event.preventDefault();
        event.returnValue = "";
    });

    els.date.max = localDateValue();
    els.date.value = localDateValue();
    state.date = els.date.value;
    updateDateLabel();
    updateSummary();
    refreshActions();
    loadClasses().then(() => {
        if (state.classes.some((item) => item.class_type === "conventional")) {
            els.classSelect.value = "";
            setNotice("Choose your class and load the daily register.");
        }
    });
})();
