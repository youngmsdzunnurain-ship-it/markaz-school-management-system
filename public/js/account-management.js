(() => {
    'use strict';

    const page = document.querySelector('.settings-page');
    if (!page || document.getElementById('markazAccountManagement')) return;

    const API = './api/settings/accounts.php';
    const escapeHTML = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[char]);

    const card = document.createElement('section');
    card.id = 'markazAccountManagement';
    card.className = 'settings-card markaz-account-management-card';
    card.innerHTML = `
        <div class="account-management-heading">
            <span class="account-management-eyebrow">SENIOR ADMIN</span>
            <h2>Account Management</h2>
            <p>Create and manage staff and student accounts, assignments, and individual permissions.</p>
        </div>

        <div id="accountManagementStatus" role="status" aria-live="polite"></div>

        <div class="account-management-layout">
            <div class="account-management-editor">
                <h3 id="accountFormHeading">Create an account</h3>

                <form id="accountManagementForm">
                    <input type="hidden" name="id" value="">

                    <label for="accountName">Full name</label>
                    <input id="accountName" name="name" type="text" required maxlength="120" autocomplete="name">

                    <label for="accountRole">Account role</label>
                    <select id="accountRole" name="role" required>
                        <option value="admin" hidden>Senior Admin</option>
                        <option value="exam_officer">Exam Officer</option>
                        <option value="teacher">Teacher</option>
                        <option value="student" hidden>Student (existing account)</option>
                        <option value="management">Management</option>
                    </select>

                    <div id="accountEmailField">

                        <label for="accountEmail">Email address</label>

                        <input id="accountEmail" name="email" type="email" maxlength="190" autocomplete="email">

                    </div>

                    <div id="accountUsernameField" hidden>

                        <label for="accountUsername">Teacher username</label>

                        <input id="accountUsername" name="username" type="text" maxlength="60" autocomplete="username" minlength="4" pattern="(?=.*[a-z])(?=.*[A-Z])[A-Za-z0-9._-]{4,60}" title="Use 4–60 characters with uppercase and lowercase letters; letters, numbers, dots, underscores and hyphens are allowed.">

                        <small>Use a unique username, for example TeacherA27.</small>

                    </div>

<label for="accountPassword">Password <span id="accountPasswordHint">(required for new accounts)</span></label>
                    <div class="account-password-row">
                        <input id="accountPassword" name="password" type="password" minlength="8" autocomplete="new-password">
                        <button type="button" id="toggleAccountPassword" aria-pressed="false">Show</button>
                    </div>
                    <div class="account-password-guidance">
                        <small>Password requirements:</small>
                        <ul>
                            <li data-password-rule="length">At least 8 characters</li>
                            <li data-password-rule="upper">One uppercase letter (A–Z)</li>
                            <li data-password-rule="lower">One lowercase letter (a–z)</li>
                            <li data-password-rule="number">One number (0–9)</li>
                            <li data-password-rule="symbol">One symbol (e.g. !@#)</li>
                        </ul>
                    </div>
                    <label for="accountPasswordConfirm">Confirm password</label>
                    <div class="account-password-row">
                        <input id="accountPasswordConfirm" type="password" minlength="8" autocomplete="new-password">
                        <button type="button" id="toggleAccountPasswordConfirm" aria-pressed="false">Show</button>
                    </div>
                    <small id="accountPasswordMatch" aria-live="polite">Enter the password again to confirm it.</small>
                    <small>When editing, leave both password fields blank to keep the current password.</small>

                    <div id="accountStudentLink" hidden>
                        <label for="accountStudentId">Linked student</label>
                        <select id="accountStudentId" name="student_id">
                            <option value="">Choose a student</option>
                        </select>
                        <small>A student account must be linked to the correct student record.</small>
                    </div>



                    <div id="accountTeacherAssignments" hidden>
                        <fieldset class="account-permission-group">
                            <legend>Teacher class and subject assignments</legend>
                            <p>Only assigned classes and subjects should be available to the teacher.</p>
                            <div id="accountAssignmentRows"></div>
                            <button type="button" id="addAccountAssignment">+ Add assignment</button>
                        </fieldset>
                    </div>

                    <div id="accountOnlineClassPermission" hidden>
                        <fieldset class="account-permission-group">
                            <legend>MARKAZ Online Class access</legend>
                            <label class="account-check"><input type="checkbox" id="accountCanAccessOnlineClass"> Allow this account to manage MARKAZ Online Class, including its students, classes, subjects, attendance, results and assigned settings</label>
                        </fieldset>
                    </div>

                    <div id="accountManagementPermissions" hidden>
                        <fieldset class="account-permission-group">
                            <legend>Management workspace assignments</legend>
                            <label class="account-check"><input type="checkbox" id="managementCanAttendance"> Attendance — dashboard charts, school-wide summaries, reports and attendance workspace</label>
                            <label class="account-check"><input type="checkbox" id="managementCanStudents"> Students — view, add, edit and manage student records</label>
                            <label class="account-check"><input type="checkbox" id="managementCanSchoolFees"> School Fees — reserved access for the school-fees module</label>
                            <small>Each Management account receives only the sections selected here. You can assign different access to each manager.</small>
                        </fieldset>
                    </div>

                    <div id="accountOnlineClassPermission" hidden>
                        <fieldset class="account-permission-group">
                            <legend>MARKAZ Online Class access</legend>
                            <label class="account-check"><input type="checkbox" id="accountCanOnlineClass"> Allow this account to access the complete Online Class workspace, including its settings, students, subjects, attendance and results</label>
                        </fieldset>
                    </div>

                    <div id="accountAttendancePermission" hidden>
                        <label class="account-check">
                            <input type="checkbox" id="accountCanTakeAttendance">
                            Teacher can record attendance for assigned classes
                        </label>
                        <small>Attendance access is limited to the teacher’s assigned classes.</small>
                    </div>

                    <div id="accountTeacherDocumentPermissions" hidden>
                        <fieldset class="account-permission-group">
                            <legend>Teacher result document permissions</legend>
                            <label class="account-check">
                                <input type="checkbox" id="accountCanPrintResults">
                                Allow this teacher to print results
                            </label>
                            <label class="account-check">
                                <input type="checkbox" id="accountCanExportResults">
                                Allow this teacher to export/download results
                            </label>
                            <small>Requires Full Class Access and the role-wide permission to be enabled.</small>
                        </fieldset>
                    </div>

                    <label class="account-check account-active-check">
                        <input type="checkbox" id="accountIsActive" checked>
                        Account is active
                    </label>

                    <div class="account-management-actions">
                        <button type="submit" id="saveManagedAccount">Create account</button>
                        <button type="button" id="resetManagedAccount">Clear form</button>
                    </div>
                </form>
            </div>

            <div class="account-management-list">
                <div class="account-list-heading">
                    <h3>Existing accounts</h3>
                    <button type="button" id="refreshManagedAccounts">Refresh</button>
                </div>
                <div id="managedAccountsList">Loading accounts…</div>
            </div>
        </div>
    `;

    const anchor = page.querySelector('.settings-header');
    if (anchor) anchor.insertAdjacentElement('afterend', card);
    else page.appendChild(card);

    const $ = selector => card.querySelector(selector);
    const form = $('#accountManagementForm');
    const status = $('#accountManagementStatus');
    const role = $('#accountRole');
    const emailField = $('#accountEmailField');
    const emailInput = $('#accountEmail');
    const usernameField = $('#accountUsernameField');
    const usernameInput = $('#accountUsername');
    const studentLink = $('#accountStudentLink');
    const studentSelect = $('#accountStudentId');
    const assignmentsWrap = $('#accountTeacherAssignments');
    const managementPermissionsWrap = $('#accountManagementPermissions');
    const onlineClassPermissionWrap = $('#accountOnlineClassPermission');
    const onlineClassPermission = $('#accountCanOnlineClass');
    const onlineClassPermissionWrap = $('#accountOnlineClassPermission');
    const onlineClassPermission = $('#accountCanAccessOnlineClass');
    const onlineClassPermissionWrap = $('#accountOnlineClassPermission');
    const onlineClassPermissionWrap = $('#accountOnlineClassPermission');
    const onlineClassPermission = $('#accountCanAccessOnlineClass');
    const managementAttendancePermission = $('#managementCanAttendance');
    const managementStudentsPermission = $('#managementCanStudents');
    const managementSchoolFeesPermission = $('#managementCanSchoolFees');
    const attendancePermissionWrap = $('#accountAttendancePermission');
    const attendancePermission = $('#accountCanTakeAttendance');
    const documentPermissionsWrap = $('#accountTeacherDocumentPermissions');
    const printPermission = $('#accountCanPrintResults');
    const exportPermission = $('#accountCanExportResults');
    const assignmentRows = $('#accountAssignmentRows');
    const saveButton = $('#saveManagedAccount');

    const passwordInput = $('#accountPassword');
    const passwordConfirm = $('#accountPasswordConfirm');
    const passwordMatch = $('#accountPasswordMatch');

    function updatePasswordGuidance() {
        const value = passwordInput.value;
        const checks = {
            length: value.length >= 8,
            upper: /[A-Z]/.test(value),
            lower: /[a-z]/.test(value),
            number: /[0-9]/.test(value),
            symbol: /[^A-Za-z0-9]/.test(value)
        };

        Object.entries(checks).forEach(([name, passed]) => {
            const item = card.querySelector(`[data-password-rule="${name}"]`);
            item.classList.toggle('is-valid', passed);
        });

        passwordConfirm.required = passwordInput.required || value.length > 0;

        if (!passwordInput.value && !passwordInput.required && !passwordConfirm.value) {
            passwordMatch.textContent = 'Leave both blank to keep the current password.';
            passwordMatch.classList.remove('is-valid', 'is-invalid');
        } else if (!passwordConfirm.value) {
            passwordMatch.textContent = 'Enter the password again to confirm it.';
            passwordMatch.classList.remove('is-valid', 'is-invalid');
        } else if (passwordInput.value === passwordConfirm.value) {
            passwordMatch.textContent = 'Passwords match.';
            passwordMatch.classList.add('is-valid');
            passwordMatch.classList.remove('is-invalid');
        } else {
            passwordMatch.textContent = 'Passwords do not match.';
            passwordMatch.classList.add('is-invalid');
            passwordMatch.classList.remove('is-valid');
        }
    }

    function wirePasswordToggle(buttonSelector, input) {
        $(buttonSelector).addEventListener('click', () => {
            const showing = input.type === 'password';
            input.type = showing ? 'text' : 'password';
            $(buttonSelector).textContent = showing ? 'Hide' : 'Show';
            $(buttonSelector).setAttribute('aria-pressed', String(showing));
        });
    }

    wirePasswordToggle('#toggleAccountPassword', passwordInput);
    wirePasswordToggle('#toggleAccountPasswordConfirm', passwordConfirm);
    passwordInput.addEventListener('input', updatePasswordGuidance);
    passwordConfirm.addEventListener('input', updatePasswordGuidance);


    let data = {
        accounts: [],
        students: [],
        classes: [],
        arabic_classes: [],
        subjects: []
    };

    function showStatus(message, isError = false) {
        status.textContent = message;
        status.style.color = isError ? '#b42318' : '#167044';
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            ...options,
            headers: {
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                ...(options.headers || {})
            }
        });

        let result;
        try {
            result = await response.json();
        } catch {
            throw new Error('The server returned an unreadable response.');
        }

        if (!response.ok || result.success === false) {
            throw new Error(result.message || `Request failed (${response.status}).`);
        }
        return result;
    }

    function populateStudents(selectedId = '') {
        studentSelect.innerHTML = '<option value="">Choose a student</option>';
        data.students.forEach(student => {
            const option = document.createElement('option');
            option.value = student.id;

            const fullName = [
                student.first_name,
                student.middle_name,
                student.last_name
            ].filter(Boolean).join(' ');

            option.textContent = [
                student.admission_number,
                fullName || `Student #${student.id}`,
                student.class_name
            ].filter(Boolean).join(' — ');

            option.selected = String(student.id) === String(selectedId);
            studentSelect.appendChild(option);
        });
    }

    function syncTeacherDocumentPermissions() {
        const hasFullClassAccess = role.value === 'teacher'
            && [...assignmentRows.querySelectorAll('.account-assignment-row')].some(row =>
                row.querySelector('.assignment-class')?.value
                && row.querySelector('.assignment-mode')?.value === 'full'
            );

        printPermission.disabled = !hasFullClassAccess;
        exportPermission.disabled = !hasFullClassAccess;
        if (!hasFullClassAccess) {
            printPermission.checked = false;
            exportPermission.checked = false;
        }
    }

    assignmentRows.addEventListener('change', syncTeacherDocumentPermissions);
    assignmentRows.addEventListener('click', syncTeacherDocumentPermissions);

    function updateRoleFields() {
        const isStudent = role.value === 'student';
        const isTeacher = role.value === 'teacher';
        studentLink.hidden = !isStudent;
        studentSelect.required = isStudent;
        const isManagement = role.value === 'management';
        onlineClassPermissionWrap.hidden = !['exam_officer','teacher','management'].includes(role.value);
        onlineClassPermissionWrap.hidden = !['exam_officer','teacher','management'].includes(role.value);
        onlineClassPermissionWrap.hidden = !['exam_officer', 'teacher', 'management'].includes(role.value);
        managementPermissionsWrap.hidden = !isManagement;
        assignmentsWrap.hidden = !isTeacher;
        attendancePermissionWrap.hidden = !isTeacher;
        documentPermissionsWrap.hidden = !isTeacher;
        emailField.hidden = isTeacher;
        emailInput.required = !isTeacher;
        emailInput.disabled = isTeacher;
        usernameField.hidden = !isTeacher;
        usernameInput.required = isTeacher;
        usernameInput.disabled = !isTeacher;
        syncTeacherDocumentPermissions();
    }

    function assignmentClassOptions(type, selectedId) {
        const classes = type === 'arabic' ? data.arabic_classes : data.classes;
        return classes.map(item =>
            `<option value="${escapeHTML(item.id)}" ${String(item.id) === String(selectedId) ? 'selected' : ''}>${escapeHTML(item.name)}</option>`
        ).join('');
    }

    function assignmentSubjectOptions(type, classId, selectedIds = []) {
        const selected = new Set((Array.isArray(selectedIds) ? selectedIds : [selectedIds])
            .filter(value => value !== null && value !== undefined && value !== '')
            .map(String));

        const allowedIds = type === 'arabic'
            ? (data.arabic_class_subjects || [])
                .filter(item => String(item.arabic_class_id) === String(classId))
                .map(item => String(item.subject_id))
            : (data.class_subjects || [])
                .filter(item => String(item.class_id) === String(classId))
                .map(item => String(item.subject_id));

        const subjects = data.subjects.filter(subject =>
            allowedIds.includes(String(subject.id))
        );

        const allSelected = selected.size === 0;
        return `<option value="" ${allSelected ? 'selected' : ''}>All subjects in this class</option>` +
            subjects.map(subject =>
                `<option value="${escapeHTML(subject.id)}" ${selected.has(String(subject.id)) ? 'selected' : ''}>${escapeHTML(subject.name)}</option>`
            ).join('');
    }

    function mappedSubjectsForAssignment(type, classId) {
        const mappings = type === 'arabic'
            ? (data.arabic_class_subjects || [])
            : (data.class_subjects || []);
        const key = type === 'arabic' ? 'arabic_class_id' : 'class_id';
        const allowed = mappings
            .filter(item => String(item[key]) === String(classId))
            .map(item => String(item.subject_id));

        return (data.subjects || []).filter(subject =>
            allowed.includes(String(subject.id))
        );
    }

    function addAssignmentRow(assignment = {}) {
        const row = document.createElement('div');
        row.className = 'account-assignment-row';

        const type = assignment.class_type === 'arabic' ? 'arabic' : 'conventional';
        const classId = assignment.class_id ?? '';
        const subjectIds = Array.isArray(assignment.subject_ids)
            ? assignment.subject_ids.map(String)
            : (assignment.subject_id == null || assignment.subject_id === ''
                ? []
                : [String(assignment.subject_id)]);
        const mode = assignment.mode ||
            (assignment.subject_id == null || assignment.subject_id === ''
                ? 'full' : 'selected');

        row.innerHTML = `
            <label>Class type
                <select class="assignment-type">
                    <option value="conventional" ${type === 'conventional' ? 'selected' : ''}>Conventional</option>
                    <option value="arabic" ${type === 'arabic' ? 'selected' : ''}>Arabic</option>
                </select>
            </label>
            <label>Class
                <select class="assignment-class" required>
                    <option value="">Choose class</option>
                    ${assignmentClassOptions(type, classId)}
                </select>
            </label>
            <label>Assignment access
                <select class="assignment-mode">
                    <option value="selected" ${mode === 'selected' ? 'selected' : ''}>Selected subjects</option>
                    <option value="all" ${mode === 'all' ? 'selected' : ''}>All subjects</option>
                    <option value="full" ${mode === 'full' ? 'selected' : ''}>Full class access</option>
                </select>
            </label>
            <fieldset class="assignment-subject-wrap">
                <legend>Choose subjects</legend>
                <div class="assignment-subject-checkboxes"></div>
                <small>Tick each subject required. All subjects includes every subject mapped to this class. Full class access includes class-level responsibilities.</small>
            </fieldset>
            <button type="button" class="remove-account-assignment" aria-label="Remove assignment">Remove</button>
        `;

        const typeSelect = row.querySelector('.assignment-type');
        const classSelect = row.querySelector('.assignment-class');
        const modeSelect = row.querySelector('.assignment-mode');
        const subjectWrap = row.querySelector('.assignment-subject-wrap');
        const subjectBox = row.querySelector('.assignment-subject-checkboxes');

        function renderSubjects(selectedIds = []) {
            subjectBox.replaceChildren();

            if (!classSelect.value) {
                subjectBox.textContent = 'Choose a class first.';
                return;
            }

            const subjects = mappedSubjectsForAssignment(typeSelect.value, classSelect.value);
            if (!subjects.length) {
                subjectBox.textContent = 'No subjects are mapped to this class.';
                return;
            }

            const selected = new Set(selectedIds.map(String));
            subjects.forEach(subject => {
                const label = document.createElement('label');
                label.className = 'assignment-subject-option';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'assignment-subject-checkbox';
                checkbox.value = String(subject.id);
                checkbox.checked = selected.has(String(subject.id));

                const name = document.createElement('span');
                name.textContent = subject.name;
                label.append(checkbox, name);
                subjectBox.appendChild(label);
            });
        }

        function updateMode() {
            subjectWrap.hidden = modeSelect.value !== 'selected';
        }

        typeSelect.addEventListener('change', () => {
            classSelect.innerHTML =
                '<option value="">Choose class</option>' +
                assignmentClassOptions(typeSelect.value, '');
            classSelect.value = '';
            modeSelect.value = 'selected';
            renderSubjects([]);
            updateMode();
        });

        classSelect.addEventListener('change', () => {
            modeSelect.value = 'selected';
            renderSubjects([]);
            updateMode();
        });

        modeSelect.addEventListener('change', updateMode);

        renderSubjects(subjectIds);
        updateMode();

        row.querySelector('.remove-account-assignment')
            .addEventListener('click', () => row.remove());
        assignmentRows.appendChild(row);
        syncTeacherDocumentPermissions();
    }

    function readAssignments() {
        const assignments = [];

        [...assignmentRows.querySelectorAll('.account-assignment-row')].forEach(row => {
            const type = row.querySelector('.assignment-type').value;
            const classId = Number(row.querySelector('.assignment-class').value);
            const mode = row.querySelector('.assignment-mode').value;

            if (!classId) {
                throw new Error('Choose a class for every teacher assignment.');
            }

            if (mode === 'full') {
                assignments.push({
                    class_type: type,
                    class_id: classId,
                    subject_id: null
                });
                return;
            }

            const mapped = mappedSubjectsForAssignment(type, classId);

            if (mode === 'all') {
                if (!mapped.length) {
                    throw new Error('No subjects are mapped to this class.');
                }
                mapped.forEach(subject => assignments.push({
                    class_type: type,
                    class_id: classId,
                    subject_id: Number(subject.id)
                }));
                return;
            }

            const selected = [...row.querySelectorAll('.assignment-subject-checkbox:checked')]
                .map(input => input.value);

            if (!selected.length) {
                throw new Error('Tick at least one subject, or choose All subjects or Full class access.');
            }

            selected.forEach(subjectId => assignments.push({
                class_type: type,
                class_id: classId,
                subject_id: Number(subjectId)
            }));
        });

        return assignments;
    }

    function clearForm() {
        form.reset();
        const studentRoleOption = role.querySelector('option[value="student"]');
        if (studentRoleOption) studentRoleOption.hidden = true;
        form.elements.id.value = '';
        role.disabled = false;
        role.querySelector('option[value="admin"]').hidden = true;
        $('#accountIsActive').disabled = false;
        $('#accountIsActive').checked = true;
        attendancePermission.checked = false;
        managementAttendancePermission.checked = false;
        managementStudentsPermission.checked = false;
        managementSchoolFeesPermission.checked = false;
        onlineClassPermission.checked = false;
        onlineClassPermission.checked = false;
        onlineClassPermission.checked = false;
        printPermission.checked = false;
        exportPermission.checked = false;
        assignmentRows.innerHTML = '';
        $('#accountFormHeading').textContent = 'Create an account';
        saveButton.textContent = 'Create account';
        $('#accountPasswordHint').textContent = '(required for new accounts)';
        $('#accountPassword').required = true;
        populateStudents();
        updateRoleFields();
        updatePasswordGuidance();
        showStatus('');
    }

    function editAccount(accountId) {
        const account = data.accounts.find(item => String(item.id) === String(accountId));
        if (!account) return;

        clearForm();
        form.elements.id.value = account.id;
        const studentRoleOption = role.querySelector('option[value="student"]');
        if (studentRoleOption) studentRoleOption.hidden = account.role !== 'student';
        $('#accountName').value = account.name || '';
        $('#accountEmail').value = account.email || '';
        usernameInput.value = account.username || '';
        role.value = account.role || 'teacher';
        const isSeniorAdmin = account.role === 'admin';
        role.querySelector('option[value="admin"]').hidden = !isSeniorAdmin;
        role.disabled = isSeniorAdmin;
        $('#accountPassword').value = '';
        $('#accountPassword').required = false;
        passwordConfirm.value = '';
        updatePasswordGuidance();
        $('#accountPasswordHint').textContent = '(leave blank to keep current password)';
        $('#accountIsActive').checked = isSeniorAdmin
            || Number(account.is_active) === 1
            || account.is_active === true;
        $('#accountIsActive').disabled = isSeniorAdmin;
populateStudents(account.student_id || '');
        assignmentRows.innerHTML = '';
        attendancePermission.checked = Boolean(account.permissions && account.permissions.attendance_records);
        managementAttendancePermission.checked = Boolean(account.permissions && account.permissions.management_attendance);
        managementStudentsPermission.checked = Boolean(account.permissions && account.permissions.management_students);
        managementSchoolFeesPermission.checked = Boolean(account.permissions && account.permissions.management_school_fees);
        onlineClassPermission.checked = Boolean(account.permissions && account.permissions.online_class_access);
        onlineClassPermission.checked = Boolean(account.permissions && account.permissions.online_class_access);
        onlineClassPermission.checked = Boolean(account.permissions && account.permissions.online_class_access);
        onlineClassPermission.checked = Boolean(account.permissions && account.permissions.online_class_access);
        const groupedAssignments = new Map();
        (account.assignments || []).forEach(assignment => {
            const key = `${assignment.class_type}:${assignment.class_id}`;
            if (!groupedAssignments.has(key)) {
                groupedAssignments.set(key, {
                    class_type: assignment.class_type,
                    class_id: assignment.class_id,
                    subject_ids: [],
                    mode: 'selected'
                });
            }

            const group = groupedAssignments.get(key);
            if (assignment.subject_id == null || assignment.subject_id === '') {
                group.subject_ids = [];
                group.mode = 'full';
            } else if (group.mode !== 'full' &&
                !group.subject_ids.some(id => String(id) === String(assignment.subject_id))) {
                group.subject_ids.push(String(assignment.subject_id));
            }
        });

        groupedAssignments.forEach(group => {
            if (group.mode !== 'full') {
                const allIds = mappedSubjectsForAssignment(group.class_type, group.class_id)
                    .map(subject => String(subject.id));
                const selected = new Set(group.subject_ids.map(String));
                if (allIds.length &&
                    allIds.length === selected.size &&
                    allIds.every(id => selected.has(id))) {
                    group.mode = 'all';
                }
            }
            addAssignmentRow(group);
        });

        printPermission.checked = Boolean(account.permissions && account.permissions.print_results);
        exportPermission.checked = Boolean(account.permissions && account.permissions.export_results);
        syncTeacherDocumentPermissions();

        $('#accountFormHeading').textContent = `Edit account: ${account.name}`;
        saveButton.textContent = 'Save account changes';
        updateRoleFields();
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        showStatus(`Editing ${account.name}.`);
    }

    function renderAccounts() {
        const target = $('#managedAccountsList');

        if (!data.accounts.length) {
            target.textContent = 'No accounts were returned.';
            return;
        }

        target.innerHTML = `
            <div class="account-management-table-wrap">
                <table class="account-management-table">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.accounts.map(account => `
                            <tr>
                                <td>
                                    <strong>${escapeHTML(account.name)}</strong>
                                    <small>${escapeHTML(account.email)}</small>
                                    ${account.role === 'student' && account.student_id
                                        ? `<small>Student ID: ${escapeHTML(account.student_id)}</small>` : ''}
                                </td>
                                <td>${escapeHTML(String(account.role || '').replaceAll('_', ' '))}</td>
                                <td>${Number(account.is_active) === 1 || account.is_active === true ? 'Active' : 'Inactive'}</td>
                                <td><button type="button" class="edit-managed-account" data-account-id="${escapeHTML(account.id)}">Edit</button></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;

        target.querySelectorAll('.edit-managed-account').forEach(button => {
            button.addEventListener('click', () => editAccount(button.dataset.accountId));
        });
    }

    async function loadAccounts() {
        $('#managedAccountsList').textContent = 'Loading accounts…';
        try {
            const result = await request(API);
            data = {
                accounts: result.accounts || [],
                students: result.students || [],
                classes: result.classes || [],
                arabic_classes: result.arabic_classes || [],
                arabic_class_subjects: result.arabic_class_subjects || [],
                class_subjects: result.class_subjects || [],
                subjects: result.subjects || []
            };
            populateStudents();
            renderAccounts();
        } catch (error) {
            $('#managedAccountsList').textContent = error.message;
            showStatus(error.message, true);
        }
    }

    role.addEventListener('change', updateRoleFields);
    $('#addAccountAssignment').addEventListener('click', () => addAssignmentRow());
    $('#resetManagedAccount').addEventListener('click', clearForm);
    $('#refreshManagedAccounts').addEventListener('click', loadAccounts);

    form.addEventListener('submit', async event => {
        event.preventDefault();

        const id = form.elements.id.value;

        if (passwordInput.value !== passwordConfirm.value) {
            showStatus('The password and confirmation must match.', true);
            passwordConfirm.focus();
            return;
        }

        const payload = {
            action: id ? 'update' : 'create',
            name: $('#accountName').value.trim(),
            email: role.value === 'teacher' ? '' : emailInput.value.trim(),
            username: role.value === 'teacher' ? usernameInput.value.trim() : '',
            role: role.value,
            password: $('#accountPassword').value,
            is_active: $('#accountIsActive').checked,
            student_id: role.value === 'student' && studentSelect.value
                ? Number(studentSelect.value) : null,
            attendance_records: role.value === 'teacher' && attendancePermission.checked,
            print_results: role.value === 'teacher' && printPermission.checked,
            export_results: role.value === 'teacher' && exportPermission.checked,
            online_class_access: ['exam_officer', 'teacher', 'management'].includes(role.value) && onlineClassPermission.checked,
            online_class_access: ['exam_officer', 'teacher', 'management'].includes(role.value) && onlineClassPermission.checked,
            online_class_access: ['exam_officer','teacher','management'].includes(role.value) && onlineClassPermission.checked,
            management_permissions: role.value === 'management' ? {
                attendance: managementAttendancePermission.checked,
                students: managementStudentsPermission.checked,
                school_fees: managementSchoolFeesPermission.checked
            } : {},
        };

    if (id) {
        payload.id = Number(id);
    }
        if (payload.role === 'teacher') {
            try {
                payload.assignments = readAssignments();
            } catch (error) {
                showStatus(error.message, true);
                return;
            }
        }

        if (payload.role === 'student' && !payload.student_id) {
            showStatus('Choose the student record linked to this account.', true);
            return;
        }

        saveButton.disabled = true;
        showStatus(id ? 'Saving account changes…' : 'Creating account…');

        try {
            const result = await request(API, {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            showStatus(result.message || (id ? 'Account updated.' : 'Account created.'));
            clearForm();
            await loadAccounts();
        } catch (error) {
            showStatus(error.message, true);
        } finally {
            saveButton.disabled = false;
        }
    });

    populateStudents();
    updateRoleFields();
    loadAccounts();
})();
