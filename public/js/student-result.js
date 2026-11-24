const params = new URLSearchParams(window.location.search);
const studentId = params.get("student_id");
const classId = params.get("class_id");
const classType = params.get("class_type") || "conventional";
const isPrintMode = params.get("print") === "1";
const isStudentPortalView = params.get("student_portal") === "1";
if (isStudentPortalView) {
    document.body.classList.add("student-portal-result-view");
}
if (isStudentPortalView) {
    const portalBackButton = document.getElementById("backToClassButton");
    if (portalBackButton) portalBackButton.textContent = "← Student Portal";
}

const $ = (id) => document.getElementById(id);

let studentData = null;
let resultFooterSettingsPromise = Promise.resolve();
let isEditMode = false;
let originalEditState = null;
let currentUserRole = "";
let currentUserRolePromise = null;
let currentUserPermissions = {
    can_print_results: false,
};
let studentDocumentPermissionsResolved = !isStudentPortalView || !isPrintMode;
let lastFocusedScoreInput = null;

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function buildStudentResultDocumentTitle(data) {
    const student = data?.student || {};
    const period = data?.academic_period || {};

    const cleanPart = value => String(value ?? "")
        .replace(/[<>:"/\\|?*\u0000-\u001F]/g, "")
        .replace(/\s+/g, " ")
        .trim()
        .replace(/[. ]+$/g, "");

    const studentName = cleanPart([
        student.first_name,
        student.middle_name,
        student.last_name
    ].filter(Boolean).join(" ")) || "Student";

    const filenameParts = [
        studentName,
        cleanPart(student.class_name),
        cleanPart(period.term_name),
        cleanPart(period.session_name)
    ].filter(Boolean);

    return `${filenameParts.join(" - ")} - MARKAZ Result`;
}

function setStudentResultDocumentTitle() {
    if (studentData) {
        document.title = buildStudentResultDocumentTitle(studentData);
    }
}

async function loadCurrentUserPermissions() {
    const response = await fetch("./api/auth/me.php", {
        credentials: "same-origin",
        cache: "no-store"
    });
    const data = await response.json();

    if (!response.ok || !data.success || !data.admin?.role) {
        throw new Error(data.message || "Unable to verify your account permissions.");
    }

    const role = String(data.admin.role).toLowerCase();
    if (!["admin", "exam_officer", "teacher", "management"].includes(role)) {
        throw new Error("Your account cannot access student results.");
    }

    if (currentUserRole && currentUserRole !== role) {
        throw new Error("Your signed-in role changed. Refresh the page and try again.");
    }

    currentUserRole = role;
    currentUserPermissions = {
        can_print_results: data.admin.can_print_results === true,
    };
    return role;
}

async function getCurrentUserRole() {
    if (currentUserRole) {
        document.body.classList.toggle("teacher-result-view", currentUserRole === "teacher");
        return currentUserRole;
    }

    const payloadRole = String(studentData?.current_user_role || "").toLowerCase();
    if (["admin", "exam_officer", "teacher", "management"].includes(payloadRole)) {
        currentUserRole = payloadRole;
        document.body.classList.toggle("teacher-result-view", currentUserRole === "teacher");
        return currentUserRole;
    }

    if (!currentUserRolePromise) {
        currentUserRolePromise = (async () => {
            const response = await fetch("./api/auth/me.php", {
                credentials: "same-origin",
                cache: "no-store"
            });
            const data = await response.json();

            if (!response.ok || !data.success || !data.admin?.role) {
                throw new Error(data.message || "Unable to verify your account permissions.");
            }

            const role = String(data.admin.role).toLowerCase();
            if (!["admin", "exam_officer", "teacher", "management"].includes(role)) {
                throw new Error("Your account cannot edit student results.");
            }

            currentUserRole = role;
            document.body.classList.toggle("teacher-result-view", currentUserRole === "teacher");
            currentUserPermissions = {
                can_print_results: data.admin.can_print_results === true,
            };
            return role;
        })();
    }

    try {
        return await currentUserRolePromise;
    } catch (error) {
        currentUserRolePromise = null;
        throw error;
    }
}

function initials(name) {
    return String(name || "Student")
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map(part => part.charAt(0))
        .join("")
        .toUpperCase();
}

function showStudentPhotoInitials(photoElement, photoBox, student) {
    if (!photoElement || !photoBox) return;
    photoBox.classList.remove("has-profile-image");
    photoElement.replaceChildren(
        document.createTextNode(initials([
            student?.first_name,
            student?.middle_name,
            student?.last_name
        ].filter(Boolean).join(" ")))
    );
}

async function waitForStudentPhotoImage() {
    const image = document.querySelector(".student-photo img");
    if (!image || image.complete) return;
    if (typeof image.decode === "function") {
        await image.decode().catch(() => undefined);
        return;
    }
    await new Promise(resolve => {
        image.addEventListener("load", resolve, { once: true });
        image.addEventListener("error", resolve, { once: true });
    });
}

function formatNumber(value) {
    const number = Number(value);
    return Number.isFinite(number) ? number.toFixed(1) : "—";
}

function calculateAge(dateOfBirth) {
    if (!dateOfBirth) return "—";

    const dob = new Date(dateOfBirth);
    if (Number.isNaN(dob.getTime())) return "—";

    const today = new Date();

    let age =
        today.getFullYear() -
        dob.getFullYear();

    const monthDifference =
        today.getMonth() -
        dob.getMonth();

    if (
        monthDifference < 0 ||
        (
            monthDifference === 0 &&
            today.getDate() < dob.getDate()
        )
    ) {
        age--;
    }

    return age >= 0 ? String(age) : "—";
}

function getGrade(total) {
    const score = Number(total);

    if (!Number.isFinite(score)) return "—";
    if (score >= 80) return "A";
    if (score >= 70) return "B";
    if (score >= 50) return "C";
    if (score >= 30) return "D";
    return "E";
}

function getAcademicDescriptor(score) {
    const result = getGrade(score);

    const descriptors = {
        A: "Awesome",
        B: "Phenomenal",
        C: "Impressive",
        D: "Promising",
        E: "Poor"
    };

    return descriptors[result] || "Awaiting results";
}

function getTeacherOverallRemark(score) {
    const result = getGrade(score);

    const remarks = {
        A: "Awesome performance. The student has demonstrated outstanding understanding, effort and consistency.",
        B: "Phenomenal performance. The student has shown exceptional understanding and effort.",
        C: "Impressive performance. The student has shown good understanding and encouraging progress.",
        D: "Promising performance. The student should keep practising and work towards stronger results.",
        E: "Poor performance. The student needs additional support, regular practice and focused effort."
    };

    return remarks[result] || "Awaiting final assessment.";
}

function setReportRemarks(score) {
    const descriptor = getAcademicDescriptor(score);
    const teacherRemark = getTeacherOverallRemark(score);

    if ($("overallStatus")) {
        $("overallStatus").textContent = descriptor;
    }

    if ($("teacherOverallRemark")) {
        $("teacherOverallRemark").textContent = teacherRemark;
    }

    if ($("directorRemark")) {
        $("directorRemark").textContent = "Mashallah, Barakallahu fi";
    }
}

async function fetchStudentResultPayload(enforcePrintEligibility) {
    const query = new URLSearchParams({
        student_id: studentId,
        class_type: classType
    });

    if (classId) query.set("class_id", classId);
    if (isStudentPortalView) query.set("student_portal", "1");
    if (enforcePrintEligibility && isPrintMode) {
        query.set("print", "1");
    }

    const response = await fetch(
        `./api/results/student.php?${query.toString()}`,
        {
            credentials: "same-origin",
            cache: "no-store"
        }
    );

    const data = await response.json();

    if (!response.ok || !data.success) {
        const error = new Error(data.message || "Unable to load student result.");
        error.code = data.code || "";
        error.completion = data.completion || null;
        error.status = response.status;
        throw error;
    }

    return data;
}

let resultPrintBlocked = null;

function showResultPrintBlockedNotice(completion) {
    const notice = $("resultPrintBlockedNotice");
    if (!notice) return;

    const percentage = Number(completion?.percentage);
    const threshold = Number(completion?.threshold);
    const hasPercentage = Number.isFinite(percentage);
    const hasThreshold = Number.isFinite(threshold);
    const missingFields = Array.isArray(completion?.missing_fields)
        ? completion.missing_fields
        : [];

    let message = completion?.message
        || "This result is available for review, but printing is disabled until the required result entries are complete.";
    if (hasPercentage && hasThreshold) {
        message = `This result is ${percentage}% complete. Printing requires at least ${threshold}% completion.`;
    }
    if (missingFields.length) {
        message += " Missing: " + missingFields.join(", ") + ".";
    }

    notice.textContent = message;
    notice.hidden = false;
    document.body.classList.add("result-print-blocked");

    $("printStudentResultButton")?.setAttribute("disabled", "disabled");
}

async function loadStudentResult() {
    if (!studentId) {
        throw new Error("Student ID is missing.");
    }

    // Render the read-only report immediately. Eligibility and permission checks
    // must never hold student details or assigned subjects in the Loading state.
    const data = await fetchStudentResultPayload(false);
    studentData = data;
    renderStudentResult(data);

    if (isPrintMode) {
        try {
            if (params.get("student_portal") === "1") {
                currentUserPermissions = {
                    can_print_results: studentData?.student_document_permissions?.can_print_results === true,
                };
            } else {
                await loadCurrentUserPermissions();
            }

            if (currentUserPermissions.can_print_results) {
                await fetchStudentResultPayload(true);
            } else {
                resultPrintBlocked = {
                    message: "Your account does not have permission to print results."
                };
            }
        } catch (error) {
            // A failed permission or completion check keeps printing unavailable.
            resultPrintBlocked = error.completion || {
                message: error.message || "Result document permission could not be verified."
            };
        }
    }

    if (isStudentPortalView && isPrintMode) studentDocumentPermissionsResolved = true;

    if (resultPrintBlocked) {
        showResultPrintBlockedNotice(resultPrintBlocked);
    }

    await loadBehaviouralReport();

    setEditMode(false);
    captureOriginalEditState();
    document.body.dataset.resultReady = "true";
    if (params.get("auto_print") === "1" && isPrintMode && !resultPrintBlocked) {
        await authorizeAndPrint();
    }
}
function renderStudentResult(data) {
    const student = data.student || {};
    const period = data.academic_period || {};
    const subjects = data.subjects || [];

    $("studentName").textContent =
        [student.first_name, student.middle_name, student.last_name]
            .filter(Boolean)
            .join(" ") || "—";

    if ($("studentAdmissionNumber")) {
        $("studentAdmissionNumber").textContent =
            student.admission_number || "—";
    }

    const displayedClassName = String(student.class_name || "").trim();
    $("studentClass").textContent =
        /^ss\s*1$/i.test(displayedClassName) ? "SS1 Science" : (displayedClassName || "—");

    $("studentArabicClass").textContent =
        student.arabic_class || "—";

    if ($("studentArabicClassCount")) {
        $("studentArabicClassCount").textContent =
            student.arabic_class_student_count ?? "—";
    }

    $("studentGender").textContent =
        student.gender || "—";

    if ($("studentType")) {
        $("studentType").textContent =
            student.student_type || "—";
    }

    if ($("studentDateOfBirth")) {
        $("studentDateOfBirth").textContent =
            student.date_of_birth || "—";
    }

    if ($("studentAge")) {
        $("studentAge").textContent =
            calculateAge(student.date_of_birth);
    }

    if ($("studentMemorizationPortion")) {
        const memorizationPortion = student.memorization_portion || "–";
        $("studentMemorizationPortion").textContent = memorizationPortion;
        $("studentMemorizationPortion").title = memorizationPortion;
    }

    $("studentMemorizationInput") &&
        ($("studentMemorizationInput").value =
            student.memorization_portion_override || "");

    $("studentStatus").textContent =
        Number(student.is_active) === 1 ? "Active" : "Inactive";

    const studentPhoto = $("studentPhotoInitials");
    const studentPhotoBox = document.querySelector(".student-photo");

    if (studentPhoto && studentPhotoBox) {
        const profileImage = String(student.profile_image || "").trim();

        if (profileImage) {
            studentPhoto.replaceChildren();
            studentPhotoBox.classList.add("has-profile-image");

            const image = document.createElement("img");
            image.alt = "";
            image.loading = "eager";
            image.decoding = "async";
            image.addEventListener("error", () => {
                showStudentPhotoInitials(studentPhoto, studentPhotoBox, student);
            }, { once: true });

            studentPhoto.appendChild(image);
            image.src = `./${profileImage.replace(/^\\/+/, "")}`;
        } else {
            showStudentPhotoInitials(studentPhoto, studentPhotoBox, student);
        }
    }

    $("sessionName").textContent =
        period.session_name || "—";

    $("termName").textContent =
        period.term_name || "—";

    $("resumptionDate").textContent =
        period.resumption_date || "—";

    setStudentResultDocumentTitle();

    renderResults(subjects, data.results || {});

    const performance = data.performance || {};

    $("studentAverage").textContent =
        performance.student_average !== null &&
        performance.student_average !== undefined
            ? formatNumber(performance.student_average)
            : "—";

    $("highestClassAverage").textContent =
        performance.highest_class_average !== null &&
        performance.highest_class_average !== undefined
            ? formatNumber(performance.highest_class_average)
            : "—";

    $("lowestClassAverage").textContent =
        performance.lowest_class_average !== null &&
        performance.lowest_class_average !== undefined
            ? formatNumber(performance.lowest_class_average)
            : "—";
}


function getBehaviourAssessment(average) {
    if (!Number.isFinite(average)) return "Awaiting assessment";
    if (average >= 4.5) return "Excellent";
    if (average >= 3.5) return "Very Good";
    if (average >= 2.5) return "Good";
    if (average >= 1.5) return "Fair";
    return "Poor";
}

function getBehaviourComment() {
    const commentElement = $("behaviourComment");
    if (!commentElement) return;

    const ratings = Array.from(
        document.querySelectorAll(".behaviour-rating")
    )
        .map(select => Number(select.value))
        .filter(value => Number.isFinite(value) && value >= 1 && value <= 5);

    if (!ratings.length) {
        commentElement.textContent = "Awaiting behavioural assessment.";
        return;
    }

    const average =
        ratings.reduce((sum, value) => sum + value, 0) / ratings.length;

    let comment;

    if (average >= 4.5) {
        comment =
            "The student has demonstrated excellent conduct, attentiveness and personal responsibility. This positive standard should be maintained.";
    } else if (average >= 3.5) {
        comment =
            "The student has demonstrated very good conduct and personal development. Continued consistency will help maintain this positive standard.";
    } else if (average >= 2.5) {
        comment =
            "The student has shown good conduct and behaviour. Continued attention to the assessed areas will support further improvement.";
    } else if (average >= 1.5) {
        comment =
            "The student is making progress in behaviour and personal development. More consistency and attention to the assessed areas are encouraged.";
    } else {
        comment =
            "The student needs further guidance and consistency in the assessed areas. With continued support and effort, positive improvement can be achieved.";
    }

    commentElement.textContent = comment;
}

function getBehaviourComment() {
    const commentElement = $("behaviourComment");
    if (!commentElement) return;

    const behaviourFields = [
        { id: "behaviourAttendance", name: "punctuality" },
        { id: "behaviourNeatness", name: "neatness" },
        { id: "behaviourContentment", name: "contentment" },
        { id: "behaviourObedience", name: "obedience" },
        { id: "behaviourResponsiveness", name: "responsiveness" },
        { id: "behaviourCalmness", name: "calmness" },
        { id: "behaviourTolerance", name: "tolerance" },
        { id: "behaviourAttentiveness", name: "attentiveness" },
        { id: "behaviourTruthfulness", name: "truthfulness" }
    ];

    const ratingWords = {
        1: "needs significant improvement",
        2: "needs improvement",
        3: "is satisfactory",
        4: "is very good",
        5: "is excellent"
    };

    const areaComments = {
        punctuality: {
            1: "Greater attention to punctuality is strongly encouraged.",
            2: "The student should make greater effort to improve punctuality.",
            3: "Punctuality is satisfactory, with room for greater consistency.",
            4: "The student demonstrates very good punctuality.",
            5: "The student demonstrates excellent punctuality."
        },
        neatness: {
            1: "Greater attention to personal neatness is strongly encouraged.",
            2: "The student should pay more attention to personal neatness.",
            3: "Neatness is satisfactory, with room for greater consistency.",
            4: "The student demonstrates very good neatness.",
            5: "The student demonstrates excellent neatness."
        },
        contentment: {
            1: "The student is encouraged to develop greater contentment and a positive attitude.",
            2: "Greater consistency in contentment and attitude is encouraged.",
            3: "Contentment is satisfactory, with room for further development.",
            4: "The student demonstrates very good contentment and attitude.",
            5: "The student demonstrates excellent contentment and attitude."
        },
        obedience: {
            1: "Greater attention to obedience and following instructions is strongly encouraged.",
            2: "The student should make greater effort to follow instructions consistently.",
            3: "Obedience is satisfactory, with room for greater consistency.",
            4: "The student demonstrates very good obedience.",
            5: "The student demonstrates excellent obedience."
        },
        responsiveness: {
            1: "Greater responsiveness to instructions and activities is strongly encouraged.",
            2: "The student should make greater effort to respond promptly and appropriately.",
            3: "Responsiveness is satisfactory, with room for greater consistency.",
            4: "The student demonstrates very good responsiveness.",
            5: "The student demonstrates excellent responsiveness."
        },
        calmness: {
            1: "Greater effort is needed to maintain calmness and self-control.",
            2: "The student should work on maintaining greater calmness and self-control.",
            3: "Calmness is satisfactory, with room for greater consistency.",
            4: "The student demonstrates very good calmness and self-control.",
            5: "The student demonstrates excellent calmness and self-control."
        },
        tolerance: {
            1: "Greater attention to tolerance and patience with others is strongly encouraged.",
            2: "The student should work on showing greater tolerance and patience.",
            3: "Tolerance is satisfactory, with room for further development.",
            4: "The student demonstrates very good tolerance and patience.",
            5: "The student demonstrates excellent tolerance and patience."
        },
        attentiveness: {
            1: "Greater attention and concentration during learning activities are strongly encouraged.",
            2: "The student should make greater effort to remain attentive during learning activities.",
            3: "Attentiveness is satisfactory, with room for greater consistency.",
            4: "The student demonstrates very good attentiveness.",
            5: "The student demonstrates excellent attentiveness."
        },
        truthfulness: {
            1: "Greater emphasis on truthfulness and honesty is strongly encouraged.",
            2: "The student should make greater effort to demonstrate consistent truthfulness.",
            3: "Truthfulness is satisfactory, with room for greater consistency.",
            4: "The student demonstrates very good truthfulness.",
            5: "The student demonstrates excellent truthfulness."
        }
    };

    const ratings = behaviourFields
        .map(field => {
            const element = $(field.id);
            const value = element ? Number(element.value) : NaN;
            return Number.isFinite(value) && value >= 1 && value <= 5
                ? { ...field, value }
                : null;
        })
        .filter(Boolean);

    if (!ratings.length) {
        commentElement.textContent = "Awaiting behavioural assessment.";
        return;
    }

    const average =
        ratings.reduce((sum, item) => sum + item.value, 0) / ratings.length;

    const improvementAreas = ratings.filter(item => item.value <= 2);
    const satisfactoryAreas = ratings.filter(item => item.value === 3);
    const strongAreas = ratings.filter(item => item.value >= 4);

    let opening;

    if (average >= 4.5) {
        opening = "The student demonstrates excellent overall behaviour and personal development.";
    } else if (average >= 3.5) {
        opening = "The student demonstrates very good overall behaviour and personal development.";
    } else if (average >= 2.5) {
        opening = "The student demonstrates good overall behaviour, with some areas requiring continued attention.";
    } else if (average >= 1.5) {
        opening = "The student is making progress in behaviour and personal development, with several areas requiring further attention.";
    } else {
        opening = "The student requires further guidance and consistency in behavioural development.";
    }

    if (improvementAreas.length === 0 && satisfactoryAreas.length === 0) {
        commentElement.textContent =
            `${opening} The student has maintained a consistently positive standard across all assessed areas. This commendable behaviour should be maintained.`;
        return;
    }

    const improvementNames = improvementAreas.map(item => item.name);

    if (improvementAreas.length === 1) {
        const item = improvementAreas[0];
        commentElement.textContent =
            `${opening} ${areaComments[item.name][item.value]} ${strongAreas.length ? "Continued consistency in the other assessed areas is also encouraged." : ""}`.trim();
        return;
    }

    if (improvementAreas.length === 2) {
        const first = improvementNames[0];
        const second = improvementNames[1];

        commentElement.textContent =
            `${opening} Greater attention is encouraged in ${first} and ${second}. With continued guidance and consistency, further improvement can be achieved.`;
        return;
    }

    const names = improvementNames.slice(0, -1).join(", ");
    const lastName = improvementNames[improvementNames.length - 1];

    commentElement.textContent =
        `${opening} Greater attention is encouraged in ${names}, and ${lastName}. With continued guidance and consistent effort, positive improvement can be achieved.`;
}

function calculateBehaviourPerformance() {
    const ratings = Array.from(
        document.querySelectorAll(".behaviour-rating")
    )
        .map(select => Number(select.value))
        .filter(value => Number.isFinite(value) && value >= 1 && value <= 5);

    const average = ratings.length
        ? ratings.reduce((sum, value) => sum + value, 0) / ratings.length
        : null;

    const averageElement = $("behaviourAverage");
    const assessmentElement = $("behaviourAssessment");
    const labelElement = $("behaviourSummaryLabel");

    if (!averageElement || !assessmentElement || !labelElement) {
        return;
    }

    const statusClasses = [
        "behaviour-excellent",
        "behaviour-very-good",
        "behaviour-good",
        "behaviour-fair",
        "behaviour-poor"
    ];

    assessmentElement.classList.remove(...statusClasses);
    labelElement.classList.remove(...statusClasses);

    if (average === null) {
        averageElement.textContent = "— / 5";
        assessmentElement.textContent = "Awaiting assessment";
        labelElement.textContent = "—";
        getBehaviourComment();
        return;
    }

    const roundedAverage = average.toFixed(1);
    const assessment = getBehaviourAssessment(average);

    const statusClassMap = {
        "Excellent": "behaviour-excellent",
        "Very Good": "behaviour-very-good",
        "Good": "behaviour-good",
        "Fair": "behaviour-fair",
        "Poor": "behaviour-poor"
    };

    const statusClass = statusClassMap[assessment];

    averageElement.textContent = `${roundedAverage} / 5`;
    assessmentElement.textContent = assessment;
    labelElement.textContent = assessment;

    if (statusClass) {
        assessmentElement.classList.add(statusClass);
        labelElement.classList.add(statusClass);
    }

    getBehaviourComment();
}

function setupBehaviourCalculation() {
    document.querySelectorAll(".behaviour-rating").forEach(select => {
        select.addEventListener("change", calculateBehaviourPerformance);
    });

    calculateBehaviourPerformance();
}

function getGradeBadgeStyle(grade) {
    const styles = {
        A: "background:#16a34a;color:#ffffff;border:1px solid #15803d;",
        B: "background:#eab308;color:#1f2937;border:1px solid #ca8a04;",
        C: "background:#f97316;color:#ffffff;border:1px solid #ea580c;",
        D: "background:#f59e0b;color:#ffffff;border:1px solid #d97706;",
        E: "background:#dc2626;color:#ffffff;border:1px solid #b91c1c;"
    };

    return styles[grade] || "";
}

function getGradeRemark(score) {
    if (!Number.isFinite(score)) return { grade: "—", remark: "—" };
    if (score >= 80) return { grade: "A", remark: "Awesome" };
    if (score >= 70) return { grade: "B", remark: "Phenomenal" };
    if (score >= 50) return { grade: "C", remark: "Impressive" };
    if (score >= 30) return { grade: "D", remark: "Promising" };
    return { grade: "E", remark: "Poor" };
}

function getEditedSubjectValues(subjectId) {
    const values = {};

    document
        .querySelectorAll(
            `.result-score-input[data-subject-id="${subjectId}"]`
        )
        .forEach(input => {
            values[input.dataset.scoreField] =
                input.value === ""
                    ? null
                    : Number(input.value);
        });

    return values;
}

function updateEditedResultRow(row) {
    if (!row) return;

    const subjectId =
        row.querySelector(".result-score-input")
            ?.dataset.subjectId;

    if (!subjectId) return;

    const subject =
        (studentData?.subjects || [])
            .find(item => String(item.id) === String(subjectId));

    if (!subject) return;

    const values =
        getEditedSubjectValues(subjectId);

    const ca1 =
        Number.isFinite(values.ca1)
            ? values.ca1
            : 0;

    const ca2 =
        Number.isFinite(values.ca2)
            ? values.ca2
            : 0;

    const exam =
        Number.isFinite(values.exam)
            ? values.exam
            : 0;

    const total = ca1 + ca2 + exam;

    const hasAnyValue =
        values.ca1 !== null ||
        values.ca2 !== null ||
        values.exam !== null;

    const result =
        getGradeRemark(total);

    const totalCell =
        row.querySelector(".result-total");

    const gradeCell =
        row.querySelector(".result-grade");

    const remarkCell =
        row.querySelector(".result-remark");

    if (totalCell) {
        totalCell.textContent =
            hasAnyValue
                ? formatNumber(total)
                : "—";
    }

    if (gradeCell) {
        gradeCell.textContent =
            hasAnyValue
                ? result.grade
                : "—";

        const gradeStyle = hasAnyValue
            ? getGradeBadgeStyle(result.grade)
            : "";

        gradeCell.setAttribute("style", gradeStyle);
        gradeCell.classList.remove(
            "grade-a",
            "grade-b",
            "grade-c",
            "grade-d",
            "grade-e"
        );

        if (hasAnyValue) {
            gradeCell.classList.add(
                `grade-${String(result.grade).toLowerCase()}`
            );
        }
    }

    if (remarkCell) {
        remarkCell.textContent =
            hasAnyValue
                ? result.remark
                : "—";
    }
}

function refreshEditedResultSummary() {
    if (!studentData) return;

    const subjects =
        studentData.subjects || [];

    let totalScore = 0;
    let completed = 0;

    subjects.forEach(subject => {
        const values =
            getEditedSubjectValues(subject.id);

        const hasAnyValue =
            values.ca1 !== null ||
            values.ca2 !== null ||
            values.exam !== null;

        if (!hasAnyValue) return;

        const ca1 =
            Number.isFinite(values.ca1)
                ? values.ca1
                : 0;

        const ca2 =
            Number.isFinite(values.ca2)
                ? values.ca2
                : 0;

        const exam =
            Number.isFinite(values.exam)
                ? values.exam
                : 0;

        totalScore += ca1 + ca2 + exam;
        completed++;
    });

    const average =
        completed
            ? totalScore / completed
            : null;

    if ($("totalSubjects")) {
        $("totalSubjects").textContent =
            subjects.length;
    }

    if ($("totalScore")) {
        $("totalScore").textContent =
            completed
                ? formatNumber(totalScore)
                : "—";
    }

    if ($("summaryAverage")) {
        $("summaryAverage").textContent =
            average !== null
                ? formatNumber(average)
                : "—";
    }

    if ($("studentAverage")) {
        $("studentAverage").textContent =
            average !== null
                ? formatNumber(average)
                : "—";
    }

    if ($("overallGrade")) {
        $("overallGrade").textContent =
            average !== null
                ? getGradeRemark(average).grade
                : "—";
    }

    if (completed && average !== null) {
        setReportRemarks(average);
    } else {
        if ($("overallStatus")) {
            $("overallStatus").textContent = "Awaiting results";
        }

        if ($("teacherOverallRemark")) {
            $("teacherOverallRemark").textContent =
                "Awaiting final assessment.";
        }

        if ($("directorRemark")) {
            $("directorRemark").textContent =
                "Mashallah, Barakallahu fi";
        }
    }

    if ($("summaryStatus")) {
        $("summaryStatus").textContent =
            completed
                ? "Available"
                : "Awaiting results";
    }
}

function renderResults(subjects, results) {
    const body = $("studentResultsBody");

    if (!body) return;

    if (!subjects.length) {
        body.innerHTML =
            '<tr><td colspan="8">No subjects assigned to this class.</td></tr>';
        return;
    }

    let totalScore = 0;
    let completed = 0;

    body.innerHTML = subjects.map((subject, index) => {
        const result =
            results[String(subject.id)] || null;

        const hasResult =
            Boolean(result);

        const ca1 =
            hasResult
                ? Number(result.ca1)
                : 0;

        const ca2 =
            hasResult
                ? Number(result.ca2)
                : 0;

        const exam =
            hasResult
                ? Number(result.exam)
                : 0;

        const total =
            hasResult
                ? ca1 + ca2 + exam
                : null;

        if (hasResult) {
            completed++;
            totalScore += total;
        }

        const gradeInfo =
            hasResult
                ? getGradeRemark(total)
                : {
                    grade: "—",
                    remark: "—"
                };

        const ca1Cell = isEditMode
            ? `<input
                    type="number"
                    class="result-score-input"
                    data-subject-id="${subject.id}"
                    data-score-field="ca1"
                    min="0"
                    max="${Number(subject.max_ca1 ?? 20)}"
                    step="0.01"
                    value="${hasResult ? ca1 : ""}"
                    aria-label="${escapeHtml(subject.name)} 1st CA"
               >`
            : (hasResult
                ? formatNumber(ca1)
                : "—");

        const ca2Cell = isEditMode
            ? `<input
                    type="number"
                    class="result-score-input"
                    data-subject-id="${subject.id}"
                    data-score-field="ca2"
                    min="0"
                    max="${Number(subject.max_ca2 ?? 20)}"
                    step="0.01"
                    value="${hasResult ? ca2 : ""}"
                    aria-label="${escapeHtml(subject.name)} 2nd CA"
               >`
            : (hasResult
                ? formatNumber(ca2)
                : "—");

        const examCell = isEditMode
            ? `<input
                    type="number"
                    class="result-score-input"
                    data-subject-id="${subject.id}"
                    data-score-field="exam"
                    min="0"
                    max="${Number(subject.max_exam ?? 60)}"
                    step="0.01"
                    value="${hasResult ? exam : ""}"
                    aria-label="${escapeHtml(subject.name)} Exam"
               >`
            : (hasResult
                ? formatNumber(exam)
                : "—");

        return `
            <tr>
                <td>${index + 1}</td>

                <td>
                    ${escapeHtml(subject.name)}
                </td>

                <td>${ca1Cell}</td>

                <td>${ca2Cell}</td>

                <td>${examCell}</td>

                <td class="result-total">
                    ${hasResult
                        ? formatNumber(total)
                        : "—"}
                </td>

                <td>
                    <span class="result-grade grade-${String(gradeInfo.grade).toLowerCase()}" style="${getGradeBadgeStyle(gradeInfo.grade)}">
                        ${gradeInfo.grade}
                    </span>
                </td>

                <td class="result-remark">
                    ${escapeHtml(gradeInfo.remark)}
                </td>
            </tr>
        `;
    }).join("");

    const average =
        completed
            ? totalScore / completed
            : 0;

    if ($("totalSubjects")) {
        $("totalSubjects").textContent =
            subjects.length;
    }

    if ($("totalScore")) {
        $("totalScore").textContent =
            completed
                ? formatNumber(totalScore)
                : "—";
    }

    const performance =
        studentData?.performance || {};

    const displayedAverage =
        isEditMode
            ? average
            : performance.student_average;

    if ($("summaryAverage")) {
        $("summaryAverage").textContent =
            displayedAverage !== null &&
            displayedAverage !== undefined
                ? formatNumber(displayedAverage)
                : "—";
    }

    if ($("studentAverage")) {
        $("studentAverage").textContent =
            displayedAverage !== null &&
            displayedAverage !== undefined
                ? formatNumber(displayedAverage)
                : "—";
    }

    if ($("overallGrade")) {
        $("overallGrade").textContent =
            displayedAverage !== null &&
            displayedAverage !== undefined
                ? getGradeRemark(displayedAverage).grade
                : "—";
    }

    if (completed && displayedAverage !== null && displayedAverage !== undefined) {
        setReportRemarks(displayedAverage);
    } else {
        if ($("overallStatus")) {
            $("overallStatus").textContent = "Awaiting results";
        }

        if ($("teacherOverallRemark")) {
            $("teacherOverallRemark").textContent =
                "Awaiting final assessment.";
        }

        if ($("directorRemark")) {
            $("directorRemark").textContent =
                "Mashallah, Barakallahu fi";
        }
    }

    if ($("summaryStatus")) {
        $("summaryStatus").textContent =
            completed
                ? "Available"
                : "Awaiting results";
    }
}


function validateScoreInput(input) {
    if (!input) return true;

    const value =
        input.value === ""
            ? null
            : Number(input.value);

    const max =
        Number(input.max);

    const invalid =
        value !== null &&
        (
            !Number.isFinite(value) ||
            value < 0 ||
            value > max
        );

    input.classList.toggle("score-invalid", invalid);
    input.classList.toggle("score-valid", !invalid && value !== null);
    input.setAttribute("aria-invalid", invalid ? "true" : "false");
    input.setCustomValidity(
        invalid ? `Enter a score from 0 to ${max}.` : ""
    );

    return !invalid;
}

function validateAllScoreInputs() {
    let valid = true;

    document
        .querySelectorAll(".result-score-input")
        .forEach(input => {
            if (!validateScoreInput(input)) {
                valid = false;
            }
        });

    return valid;
}

document.addEventListener("focusin", event => {
    if (!isEditMode) return;

    const input = event.target.closest(".result-score-input");
    if (!input) return;

    // Do not let focus enter another score box while the previous score is red.
    if (
        lastFocusedScoreInput &&
        lastFocusedScoreInput !== input &&
        !validateScoreInput(lastFocusedScoreInput)
    ) {
        const invalidInput = lastFocusedScoreInput;
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        invalidInput.focus({ preventScroll: true });
        invalidInput.select();
        return;
    }

    lastFocusedScoreInput = input;

    // Select the existing score so typing replaces it instead of appending to it.
    requestAnimationFrame(() => {
        if (document.activeElement === input) input.select();
    });
}, true);

// Mobile keyboards can move focus with Next/Go without dispatching Enter.
// Restore focus if the browser attempts to leave an invalid score field.
document.addEventListener("focusout", event => {
    if (!isEditMode) return;
    const input = event.target.closest(".result-score-input");
    if (!input || validateScoreInput(input)) return;

    event.stopPropagation();
    event.stopImmediatePropagation();
    requestAnimationFrame(() => {
        if (isEditMode && input.isConnected && !validateScoreInput(input)) {
            input.focus({ preventScroll: true });
            input.select();
        }
    });
}, true);

document.addEventListener("keydown", event => {
    if (!isEditMode) return;

    const input = event.target.closest(".result-score-input");
    if (!input) return;

    const isTab = event.key === "Tab" || event.keyCode === 9;
    const isNextAction =
        event.key === "Enter" ||
        event.key === "Next" ||
        event.key === "Go" ||
        event.keyCode === 13;

    // Red/invalid scores cannot advance with Tab, Enter or a mobile Next key.
    if (!validateScoreInput(input)) {
        if (isTab || isNextAction) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            input.focus({ preventScroll: true });
            input.select();
        }
        return;
    }

    if (!isNextAction) return;

    event.preventDefault();

    const inputs = Array.from(
        document.querySelectorAll(".result-score-input")
    );

    const currentIndex = inputs.indexOf(input);
    const nextInput = inputs[currentIndex + 1];

    if (nextInput) {
        nextInput.focus();
        nextInput.select();
    } else {
        input.blur();
    }
}, true);

document.addEventListener("input", event => {
    if (!isEditMode) return;

    const input = event.target.closest(".result-score-input");

    if (!input) return;

    const rawValue = input.value.trim();

    // Keep an over-limit score in this field until the user corrects it.
    if (!validateScoreInput(input)) {
        input.focus({ preventScroll: true });
        return;
    }

    const row = input.closest("tr");
    updateEditedResultRow(row);
    refreshEditedResultSummary();

    // Automatically move to the next score field when a valid
    // two-digit score has been entered.
    if (
        rawValue.length === 2 &&
        /^\d{2}$/.test(rawValue) &&
        validateScoreInput(input)
    ) {
        const inputs = Array.from(
            document.querySelectorAll(".result-score-input")
        );

        const currentIndex = inputs.indexOf(input);
        const nextInput = inputs[currentIndex + 1];

        if (nextInput) {
            nextInput.focus();
            nextInput.select();
        }
    }
});


function leaveStudentResultPage() {
    if (isStudentPortalView) {
        window.location.replace("./student-login.html");
        return;
    }
    if (isPrintMode) {
        window.location.href = "./print-results.html";
        return;
    }
    const fallback = classId
        ? `./class.html?id=${encodeURIComponent(classId)}`
        : "./dashboard.html";

    if (typeof window.markazGoBack === "function") {
        window.markazGoBack(fallback);
    } else {
        window.location.href = fallback;
    }
}

function showUnsavedChangesModal() {
    const modal = $("unsavedChangesModal");
    if (!modal) return;

    modal.hidden = false;

    const stayButton = $("stayOnResultButton");
    if (stayButton) {
        setTimeout(() => stayButton.focus(), 0);
    }
}

function hideUnsavedChangesModal() {
    const modal = $("unsavedChangesModal");
    if (!modal) return;

    modal.hidden = true;
}

$("backToClassButton")?.addEventListener("click", () => {
    if (isEditMode) {
        showUnsavedChangesModal();
        return;
    }

    leaveStudentResultPage();
});

$("mobileBackButton")?.addEventListener("click", () => {
    if (isEditMode) {
        showUnsavedChangesModal();
        return;
    }

    leaveStudentResultPage();
});

$("stayOnResultButton")?.addEventListener("click", () => {
    hideUnsavedChangesModal();
});

$("leaveResultButton")?.addEventListener("click", () => {
    hideUnsavedChangesModal();
    leaveStudentResultPage();
});

$("saveAndLeaveResultButton")?.addEventListener("click", async () => {
    if (!isEditMode) {
        hideUnsavedChangesModal();
        leaveStudentResultPage();
        return;
    }
    const button = $("saveAndLeaveResultButton");
    const originalLabel = button.textContent;
    button.disabled = true;
    button.textContent = "Saving...";
    try {
        if (!validateAllScoreInputs()) {
            throw new Error("Please correct the red scores before saving.");
        }
        await saveAllStudentChanges();
        setEditMode(false);
        captureOriginalEditState();
        hideUnsavedChangesModal();
        leaveStudentResultPage();
    } catch (error) {
        let message = $("unsavedChangesSaveError");
        if (!message) {
            message = document.createElement("p");
            message.id = "unsavedChangesSaveError";
            message.className = "markaz-confirm-save-error";
            $("unsavedChangesModal")?.querySelector(".markaz-confirm-content")?.appendChild(message);
        }
        if (message) message.textContent = error.message || "Unable to save changes. Please try again.";
        button.disabled = false;
        button.textContent = originalLabel || "Save & Leave";
    }
});

$("unsavedChangesModal")?.addEventListener("click", event => {
    if (event.target.id === "unsavedChangesModal") {
        hideUnsavedChangesModal();
    }
});

function checkStudentResultCompletion() {
    const subjects = (studentData?.subjects || []).filter(subject =>
        subject.is_required === true ||
        subject.is_required === 1 ||
        subject.is_required === "1"
    );
    const fields = ["ca1", "ca2", "exam"];
    let required = 0;
    let entered = 0;

    subjects.forEach(subject => {
        const values = getEditedSubjectValues(subject.id) || {};
        fields.forEach(field => {
            required++;
            const value = values[field];
            if (value !== null && value !== undefined && value !== "") entered++;
        });
    });

    const behaviour = getBehaviourValues() || {};
    const behaviourFields = [
        "attendance", "neatness", "contentment", "obedience",
        "responsiveness", "tolerance", "attentiveness",
        "truthfulness", "calmness"
    ];
    behaviourFields.forEach(field => {
        required++;
        const value = behaviour[field];
        if (value !== null && value !== undefined && value !== "") entered++;
    });

    return required ? Math.round(entered / required * 100) : 0;
}

async function applyResultPrintScale() {
    // Keep printing available at the default size if the setting cannot be read.
    document.documentElement.style.setProperty("--markaz-result-print-scale", "1");
    try {
        const response = await fetch("./api/settings/result-print-scale.php", {
            credentials: "same-origin",
            cache: "no-store"
        });
        const data = await response.json();
        if (response.ok && data.success) {
            const scale = Number(data.scale);
            if (Number.isInteger(scale) && scale >= 50 && scale <= 100 && scale % 5 === 0) {
                document.documentElement.style.setProperty(
                    "--markaz-result-print-scale",
                    String(scale / 100)
                );
            }
        }
    } catch (error) {
        console.warn("Using the default 100% result print scale.", error);
    }
}

async function printStudentResult() {
    setStudentResultDocumentTitle();
    await applyResultPrintScale();
    window.print();
}

async function waitForResultFooterImages() {
    await resultFooterSettingsPromise;
    const images = Array.from(
        document.querySelectorAll(".signature-space .result-signature-image")
    );
    await Promise.all(images.map(image => {
        if (image.complete) return Promise.resolve();
        if (typeof image.decode === "function") {
            return image.decode().catch(() => undefined);
        }
        return new Promise(resolve => {
            image.addEventListener("load", resolve, { once: true });
            image.addEventListener("error", resolve, { once: true });
        });
    }));
}

async function authorizeAndPrint() {
    try {
        // Student printing is limited to the read-only report bound to the
        // verified, short-lived student portal session. Staff use role permissions.
        const actionQuery = new URLSearchParams({
            action: "print_results",
            class_id: classId || "",
            class_type: classType || "conventional",
            student_id: studentId || ""
        });
        if (isStudentPortalView) actionQuery.set("student_portal", "1");
        const response = await fetch(
            `./api/results/authorize-action.php?${actionQuery.toString()}`,
            { credentials: "same-origin" }
        );
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "You are not permitted to perform this action.");
        }

        await waitForStudentPhotoImage();
        await waitForResultFooterImages();
        setStudentResultDocumentTitle();
        await applyResultPrintScale();
        window.print();
    } catch (error) {
        alert(error.message || "Unable to verify permission.");
    }
}

// The printable report is generated in-browser; printing is the only document action.

const printStudentResultButton = $("printStudentResultButton");
const resultDocumentActions = $("resultDocumentActions");

function buildDedicatedPrintUrl(autoPrint = false) {
    const query = new URLSearchParams({
        student_id: studentId || "",
        class_type: classType || "conventional"
    });
    if (classId) query.set("class_id", classId);
    query.set("print", "1");
    if (autoPrint) query.set("auto_print", "1");
    return `./student-result-print.html?${query.toString()}`;
}

printStudentResultButton?.addEventListener("click", () => {
    if (!isPrintMode || !studentData || resultPrintBlocked) return;
    authorizeAndPrint();
});

const editScoresButton = $("editScoresButton");
const saveScoresButton = $("saveScoresButton");
const cancelScoresButton = $("cancelScoresButton");
if (isPrintMode) {
    document.querySelectorAll(
        ".student-edit-actions, #editScoresButton, #saveScoresButton, #cancelScoresButton"
    ).forEach(control => control.remove());
}

const memorizationInput = $("studentMemorizationInput");
const memorizationDisplay = $("studentMemorizationPortion");

const behaviourFieldIds = [
    "behaviourAttendance",
    "behaviourNeatness",
    "behaviourContentment",
    "behaviourObedience",
    "behaviourResponsiveness",
    "behaviourTolerance",
    "behaviourAttentiveness",
    "behaviourTruthfulness",
    "behaviourCalmness"
];

function getBehaviourValues() {
    const values = {};

    behaviourFieldIds.forEach(id => {
        const field = $(id);

        if (field) {
            values[id] = field.value;
        }
    });

    return values;
}

function teacherHasFullClassAccess() {
    const value = studentData?.student?.teacher_full_class_access;
    return value === true || value === 1 || value === "1";
}

function setBehaviourEditing(editing) {
    const canEdit = currentUserRole === "admin" ||
        (currentUserRole === "teacher" && teacherHasFullClassAccess());
    document.querySelectorAll(".behaviour-rating").forEach(select => {
        select.disabled = !(editing && canEdit);
    });
}

function getCurrentScoreValues() {
    const values = [];

    document
        .querySelectorAll(".result-score-input")
        .forEach(input => {
            values.push({
                subject_id: Number(input.dataset.subjectId),
                field: input.dataset.scoreField,
                value: input.value
            });
        });

    return values;
}

function captureOriginalEditState() {
    originalEditState = {
        memorization: memorizationInput
            ? memorizationInput.value
            : "",

        behaviour: getBehaviourValues(),

        results: studentData
            ? JSON.parse(
                JSON.stringify(studentData.results || {})
            )
            : {}
    };
}

function restoreOriginalEditState() {
    if (!originalEditState) return;

    if (memorizationInput) {
        memorizationInput.value =
            originalEditState.memorization;
    }

    if (memorizationDisplay) {
        memorizationDisplay.textContent =
            originalEditState.memorization || "—";
    }

    Object.entries(originalEditState.behaviour).forEach(
        ([id, value]) => {
            const field = $(id);

            if (field) {
                field.value = value;
            }
        }
    );

    if (studentData) {
        studentData.results =
            JSON.parse(
                JSON.stringify(originalEditState.results)
            );

        renderResults(
            studentData.subjects || [],
            studentData.results || {}
        );
    }

    calculateBehaviourPerformance();
}

function setResultActionStatus(message, state = "") {
    const status = $("resultActionStatus");
    if (!status) return;

    status.textContent = message || "";
    status.dataset.state = state;
}

function showSavedMessage() {
    setResultActionStatus("✓ Changes saved successfully.", "success");
}

function setEditMode(editing) {
    if (isPrintMode) editing = false;
    isEditMode = editing;

    const canEditBehaviour = currentUserRole === "admin" ||
        (currentUserRole === "teacher" && teacherHasFullClassAccess());
    const canEditMemorization = currentUserRole === "admin" ||
        (currentUserRole === "teacher" && teacherHasFullClassAccess());

    setBehaviourEditing(editing && canEditBehaviour);

    if (memorizationInput) {
        memorizationInput.hidden = !(editing && canEditMemorization);
        memorizationInput.disabled = !(editing && canEditMemorization);
    }

    if (memorizationDisplay) {
        memorizationDisplay.hidden = editing && canEditMemorization;
    }

    if (!editing) lastFocusedScoreInput = null;
    if (editing) setResultActionStatus("", "");

    if (editScoresButton) {
        editScoresButton.textContent = "Edit";
        editScoresButton.disabled = editing;
        editScoresButton.classList.toggle("primary-action", false);
        editScoresButton.classList.toggle("secondary-action", true);
    }

    if (saveScoresButton) {
        saveScoresButton.hidden = !editing;
        saveScoresButton.disabled = false;
        saveScoresButton.textContent = "Save";
    }

    if (cancelScoresButton) {
        cancelScoresButton.hidden = !editing;
    }

    if (isStudentPortalView && studentDocumentPermissionsResolved) {
        // Disabled student actions must not exist in the page at all.
        if (!currentUserPermissions.can_print_results) printStudentResultButton?.remove();
        else if (printStudentResultButton) printStudentResultButton.hidden = !isPrintMode;
        if (resultDocumentActions) {
            const hasStudentDocumentAction = Boolean(
                currentUserPermissions.can_print_results && printStudentResultButton?.isConnected
            );
            resultDocumentActions.hidden = !isPrintMode || editing || !studentData || !hasStudentDocumentAction;
            if (!hasStudentDocumentAction) resultDocumentActions.remove();
        }
    } else if (isStudentPortalView) {
        if (printStudentResultButton) printStudentResultButton.hidden = true;
        if (resultDocumentActions) resultDocumentActions.hidden = true;
    } else {
        if (printStudentResultButton) {
            printStudentResultButton.hidden = !isPrintMode || !currentUserPermissions.can_print_results;
        }
        if (resultDocumentActions) {
            resultDocumentActions.hidden = !isPrintMode || editing || !studentData
                || !currentUserPermissions.can_print_results;
        }
    }

    if (studentData) {
        renderResults(
            studentData.subjects || [],
            studentData.results || {}
        );
    }
}

async function loadBehaviouralReport() {
    if (!studentId) return;
    if (!isStudentPortalView) await getCurrentUserRole();
    if (!isStudentPortalView && currentUserRole === "teacher" && !teacherHasFullClassAccess()) {
        setBehaviourEditing(false);
        return;
    }

    const behaviourQuery = new URLSearchParams({
        student_id: studentId,
        class_id: classId || "",
        class_type: classType
    });
    if (isStudentPortalView) behaviourQuery.set("student_portal", "1");

    const response = await fetch(
        `./api/results/behavioural.php?${behaviourQuery.toString()}`,
        {
            credentials: "same-origin",
            cache: "no-store"
        }
    );

    const data = await response.json();

    if (!response.ok || !data.success) {
        throw new Error(
            data.message ||
            "Unable to load behavioural report."
        );
    }

    const behaviour = data.behaviour || {};

    const columnMap = {
        attendance: "behaviourAttendance",
        neatness: "behaviourNeatness",
        contentment: "behaviourContentment",
        obedience: "behaviourObedience",
        responsiveness: "behaviourResponsiveness",
        tolerance: "behaviourTolerance",
        attentiveness: "behaviourAttentiveness",
        truthfulness: "behaviourTruthfulness",
        calmness: "behaviourCalmness"
    };

    Object.entries(columnMap).forEach(
        ([field, id]) => {
            const element = $(id);

            if (element) {
                element.value =
                    behaviour[field] === null ||
                    behaviour[field] === undefined
                        ? ""
                        : String(behaviour[field]);
            }
        }
    );

    calculateBehaviourPerformance();
}

async function saveAllStudentChanges() {
    if (!studentData || !studentId || !classId) {
        throw new Error(
            "Student result information is incomplete."
        );
    }

    const role = await getCurrentUserRole();
    if (!validateAllScoreInputs()) {
        throw new Error("Please correct the red scores before saving.");
    }

    const scoreInputs =
        getCurrentScoreValues();

    const groupedScores = {};

    scoreInputs.forEach(item => {
        if (!groupedScores[item.subject_id]) {
            groupedScores[item.subject_id] = {};
        }

        groupedScores[item.subject_id][item.field] =
            item.value;
    });

    const subjects =
        studentData.subjects || [];
    const originalResults =
        originalEditState?.results || studentData.results || {};
    const normalizeScore = value =>
        value === null || value === undefined || value === ""
            ? null
            : Number(value);

    for (const subject of subjects) {
        const values =
            groupedScores[subject.id] || {};

        const ca1 =
            values.ca1 === "" ||
            values.ca1 === undefined
                ? null
                : Number(values.ca1);

        const ca2 =
            values.ca2 === "" ||
            values.ca2 === undefined
                ? null
                : Number(values.ca2);

        const exam =
            values.exam === "" ||
            values.exam === undefined
                ? null
                : Number(values.exam);

        const previous = originalResults[String(subject.id)] || {};
        const subjectChanged =
            normalizeScore(ca1) !== normalizeScore(previous.ca1) ||
            normalizeScore(ca2) !== normalizeScore(previous.ca2) ||
            normalizeScore(exam) !== normalizeScore(previous.exam);

        // Only submit subjects actually edited; teacher access is subject-specific.
        if (!subjectChanged) continue;

        const maxCa1 =
            Number(subject.max_ca1 ?? 20);

        const maxCa2 =
            Number(subject.max_ca2 ?? 20);

        const maxExam =
            Number(subject.max_exam ?? 60);

        if (
            ca1 !== null &&
            (!Number.isFinite(ca1) ||
             ca1 < 0 ||
             ca1 > maxCa1)
        ) {
            throw new Error(
                `${subject.name}: 1st CA must be between 0 and ${maxCa1}.`
            );
        }

        if (
            ca2 !== null &&
            (!Number.isFinite(ca2) ||
             ca2 < 0 ||
             ca2 > maxCa2)
        ) {
            throw new Error(
                `${subject.name}: 2nd CA must be between 0 and ${maxCa2}.`
            );
        }

        if (
            exam !== null &&
            (!Number.isFinite(exam) ||
             exam < 0 ||
             exam > maxExam)
        ) {
            throw new Error(
                `${subject.name}: Exam must be between 0 and ${maxExam}.`
            );
        }

        const response = await fetch(
            "./api/results/save.php",
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type":
                        "application/json"
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
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message ||
                `Unable to save ${subject.name}.`
            );
        }
    }

    const portion =
        memorizationInput
            ? memorizationInput.value.trim()
            : "";
    const originalPortion =
        originalEditState?.memorization ?? portion;

    if ((role === "admin" || (role === "teacher" && teacherHasFullClassAccess())) && portion !== originalPortion) {
        const memorizationResponse =
            await fetch(
                "./api/results/student-profile-save.php",
                {
                    method: "POST",
                    credentials: "same-origin",
                    body: (() => {
                        const formData =
                            new FormData();
    
                        formData.append(
                            "student_id",
                            studentId
                        );
                        formData.append("class_id", classId);
                        formData.append("class_type", classType);
    
                        formData.append(
                            "memorization_portion",
                            portion
                        );
    
                        return formData;
                    })()
                }
            );
    
        const memorizationData =
            await memorizationResponse.json();
    
        if (
            !memorizationResponse.ok ||
            !memorizationData.success
        ) {
            throw new Error(
                memorizationData.message ||
                "Unable to save memorization portion."
            );
        }
    
    
    }

    const behaviourChanged = behaviourFieldIds.some(id => {
        const field = $(id);
        const currentValue = field ? field.value : "";
        const originalValue = originalEditState?.behaviour?.[id] ?? "";
        return currentValue !== originalValue;
    });

    if ((role === "admin" || role === "teacher") && behaviourChanged) {
        const behaviourPayload = {
            class_id: Number(classId),
            class_type: classType,
            student_id: Number(studentId)
        };
    
        behaviourFieldIds.forEach(id => {
            const field = $(id);
    
            const apiName =
                id.replace(
                    "behaviour",
                    ""
                ).replace(
                    /^[A-Z]/,
                    letter => letter.toLowerCase()
                );
    
            behaviourPayload[apiName] =
                field && field.value !== ""
                    ? Number(field.value)
                    : null;
        });
    
        const behaviourResponse =
            await fetch(
                "./api/results/behavioural.php",
                {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        "Content-Type":
                            "application/json"
                    },
                    body:
                        JSON.stringify(
                            behaviourPayload
                        )
                }
            );
    
        const behaviourData =
            await behaviourResponse.json();
    
        if (
            !behaviourResponse.ok ||
            !behaviourData.success
        ) {
            throw new Error(
                behaviourData.message ||
                "Unable to save behavioural report."
            );
        }
    
    
    }

    await loadStudentResult();
}

editScoresButton?.addEventListener(
    "click",
    async () => {
        if (isEditMode) return;

        try {
            await getCurrentUserRole();
            captureOriginalEditState();
            setEditMode(true);
        } catch (error) {
            setResultActionStatus(
                error.message || "Unable to enable result editing.",
                "error"
            );
        }
    }
);

saveScoresButton?.addEventListener(
    "click",
    async () => {
        if (!isEditMode) return;

        if (!validateAllScoreInputs()) {
            const firstInvalid = document.querySelector(
                ".result-score-input.score-invalid"
            );
            setResultActionStatus(
                "Correct the red score before saving.",
                "error"
            );
            firstInvalid?.focus({ preventScroll: true });
            firstInvalid?.select();
            return;
        }

        saveScoresButton.disabled = true;
        saveScoresButton.textContent = "Saving...";

        try {
            await saveAllStudentChanges();
            setEditMode(false);
            captureOriginalEditState();
            showSavedMessage();
        } catch (error) {
            saveScoresButton.disabled = false;
            saveScoresButton.textContent = "Save";
            setResultActionStatus(
                error.message || "Unable to save changes.",
                "error"
            );
        }
    }
);

cancelScoresButton?.addEventListener(
    "click",
    () => {
        restoreOriginalEditState();
        setEditMode(false);
    }
);

document.addEventListener(
    "keydown",
    event => {
        if (
            (event.ctrlKey || event.metaKey) &&
            event.key.toLowerCase() === "s" &&
            isEditMode
        ) {
            event.preventDefault();
        }
    }
);

setupBehaviourCalculation();

resultFooterSettingsPromise = loadResultFooterSettings();

loadStudentResult().catch(error => {
    console.error(error);
    if (isPrintMode) {
        $("printStudentResultButton")?.setAttribute("disabled", "disabled");
    }

    if ($("studentName")?.textContent === "Loading...") {
        $("studentName").textContent = "Unable to load student result";
    }
    if ($("studentResultsBody")) {
        $("studentResultsBody").innerHTML = `
            <tr>
                <td colspan="8">
                    ${escapeHtml(error.message || "Unable to load student result.")}
                </td>
            </tr>
        `;
    }
    if (isPrintMode) {
        const notice = $("resultPrintBlockedNotice");
        if (notice) {
            notice.textContent = error.message || "Unable to load this result.";
            notice.hidden = false;
        }
    }
});

/* UNSAVED CHANGES PROTECTION */
window.addEventListener("beforeunload", event => {
    if (!isEditMode) return;

    event.preventDefault();
    event.returnValue = "";
});


/* SHARED RESULT SIGNATURES */
async function loadResultFooterSettings() {
    try {
        const response = await fetch("./api/settings/result-footer.php", {
            credentials: "same-origin",
            cache: "no-store"
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to load result footer settings.");
        }

        const settings = data.settings || {};
        const entries = [
            ["classTeacherSignatureDisplay", settings.class_teacher_signature_image, settings.class_teacher_signature, "Class Teacher signature"],
            ["directorSignatureDisplay", settings.director_signature_image, settings.director_signature, "Director / Principal signature"]
        ];

        entries.forEach(([id, imagePath, legacyText, alt]) => {
            const target = $(id);
            if (!target) return;
            target.replaceChildren();
            if (imagePath) {
                const image = document.createElement("img");
                image.className = "result-signature-image";
                image.src = new URL(String(imagePath).replace(/^\/+/, ""), window.location.origin + "/").href + "?v=" + Date.now();
                image.alt = alt;
                image.addEventListener("error", () => {
                    target.textContent = legacyText || "—";
                }, { once: true });
                target.appendChild(image);
            } else {
                target.textContent = legacyText || "—";
            }
        });

        const date = $("resultSignatureDateDisplay");
        if (date) date.textContent = settings.result_signature_date || "—";
    } catch (error) {
        console.error("Result footer settings:", error);
    }
}

