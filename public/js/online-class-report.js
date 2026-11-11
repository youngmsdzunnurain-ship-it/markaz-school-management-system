(() => {
"use strict";

const esc = value => String(value ?? "").replace(/[&<>"']/g, char => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
})[char]);

function gradeFor(score) {
    if (score >= 80) return { grade: "A", remark: "Awesome" };
    if (score >= 70) return { grade: "B", remark: "Phenomenal" };
    if (score >= 50) return { grade: "C", remark: "Impressive" };
    if (score >= 30) return { grade: "D", remark: "Promising" };
    return { grade: "E", remark: "Poor" };
}

function tutorComment(score) {
    if (score >= 80) return "Mashallah! Your dedication and excellent effort are truly commendable. May Allah increase you in beneficial knowledge.";
    if (score >= 70) return "Well done. Your steady effort is showing beautiful progress. Keep revising and moving forward.";
    if (score >= 50) return "You are making promising progress. Continue with regular revision and confidence; every step matters.";
    if (score >= 40) return "You have made a beginning. With patient practice and consistent revision, you can make stronger progress.";
    return "Please do not be discouraged. Learning is a journey; take it one step at a time and keep trying.";
}

function signatureMarkup(signatures, imageKey, textKey, alt) {
    const image = signatures[imageKey] || "";
    const fallback = signatures[textKey] || "";
    if (!image) return esc(fallback || "—");
    const raw = String(image).trim();
    const src = /^data:image\//i.test(raw) || /^https?:\/\//i.test(raw)
        ? raw
        : "./" + raw.replace(/^\/+/, "").replace(/^\.\//, "") + "?v=" + Date.now();
    return '<img class="online-report-signature" src="' + esc(src) + '" alt="' + esc(alt) + '" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="signature-fallback" data-signature-fallback hidden>' + esc(fallback || "—") + "</span>";
}

function formatDate(value) {
    const match = String(value || "").match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return match ? match[3] + "/" + match[2] + "/" + match[1] : (value || "To be announced");
}

function render(data) {
    const student = data.student || {};
    const level = data.level || {};
    const rows = Array.isArray(data.rows) ? data.rows : [];
    const term = String(data.term || "");
    const session = String(data.session || "");
    const signatures = data.signatures || {};
    if (!rows.length) throw new Error("There are no released results to print.");
    if (rows.some(row => [row.attendance_score, row.test_score, row.exam_score].some(value => value === null || value === undefined || value === ""))) {
        throw new Error("Complete Attendance, Test and Final Examination scores for every subject before printing this report.");
    }

    const totals = rows.map(row => Number(row.attendance_score) + Number(row.test_score) + Number(row.exam_score));
    const average = totals.reduce((sum, value) => sum + value, 0) / totals.length;
    const overallGrade = gradeFor(average);
    const teacherRemark = rows.map(row => row.teacher_remark).find(Boolean) || tutorComment(average);
    const directorRemark = rows.map(row => row.director_remark).find(Boolean) || "Mashallah, Barakallahu fi";
    const dateKey = ({
        "First Term": "first_term_resumption",
        "Second Term": "second_term_resumption",
        "Third Term": "third_term_resumption"
    })[term];
    const resumption = formatDate(level[dateKey] || student[dateKey]);
    const teacherSignature = signatureMarkup(signatures, "class_teacher_signature_image", "class_teacher_signature", "Tutor signature");
    const directorSignature = signatureMarkup(signatures, "director_signature_image", "director_signature", "Director signature");
    const signatureDate = esc(signatures.result_signature_date || new Date().toLocaleDateString());

    const subjectRows = rows.map((row, index) => {
        const total = totals[index];
        const grade = gradeFor(total);
        return "<tr><td>" + (index + 1) + "</td><td>" + esc(row.subject_name) + "</td><td>" + Number(row.attendance_score).toFixed(1) + " / 20</td><td>" + Number(row.test_score).toFixed(1) + " / 20</td><td>" + Number(row.exam_score).toFixed(1) + " / 60</td><td><strong>" + total.toFixed(1) + " / 100</strong></td><td><strong>" + esc(row.grade || grade.grade) + "</strong></td><td>" + esc(row.grade_remark || grade.remark) + "</td></tr>";
    }).join("");

    return [
        '<div class="online-report-head"><img src="./assets/images/markaz-logo.jpg" alt="MARKAZ logo"><div><h1 class="school-name-ar" lang="ar" dir="rtl">مركز تعليم وتحفيظ القرآن الكريم</h1><p class="school-address-ar" lang="ar" dir="rtl">غواغوالادا، أبوجا</p><p class="school-name-en">CENTRE FOR LEARNING AND MEMORIZATION OF THE GLORIOUS QUR’AN</p><p class="school-address-en">Compensation Layout, Old Kuutunku, Gwagwalada, Abuja, Nigeria</p><p class="school-contact-line">Tel: +234 807 319 1275 · +234 807 095 5833</p><p class="school-contact-line">Email: markazilquran@gmail.com</p></div><div class="online-report-stamp"><strong>STUDENT RESULT</strong><span>OFFICIAL ACADEMIC RECORD</span></div></div>',
        '<div class="online-report-programme"><span lang="ar" dir="rtl">الفصل الدراسي عبر الإنترنت</span><span>ONLINE CLASS</span></div>',
        '<div class="online-report-title">' + esc(term.toUpperCase()) + ' RESULT SHEET</div>',
        '<p style="text-align:center">Academic Session: ' + esc(session) + '</p>',
        '<div class="online-report-details"><span><b>Name of Student:</b> ' + esc([student.first_name, student.middle_name, student.last_name].filter(Boolean).join(" ")) + '</span><span><b>Term:</b> ' + esc(term) + '</span><span><b>Admission No:</b> ' + esc(student.admission_number) + '</span><span><b>Next Term Begins:</b> ' + esc(resumption) + '</span><span><b>Class / Level:</b> ' + esc(level.name || student.level_name || "") + '</span><span><b>Section:</b> ' + esc(student.section || "—") + '</span><span><b>Tutor:</b> ' + esc(level.tutor_name || student.tutor_name || "") + '</span><span><b>Portion of Memorisation:</b> ' + esc(level.memorization_portion || student.memorization_portion || "") + '</span><span><b>Assessments:</b> Attendance 20 · Test 20 · Final Examination 60</span>''</div>',
        '<table class="online-report-table"><thead><tr><th>S/N</th><th>Subject</th><th>Attendance</th><th>Test</th><th>Final Examination</th><th>Total</th><th>Grade</th><th>Remark</th></tr></thead><tbody>' + subjectRows + '<tr><td colspan="5"><b>Overall Average</b></td><td><b>' + average.toFixed(1) + ' / 100</b></td><td><b>' + overallGrade.grade + '</b></td><td><b>' + esc(overallGrade.remark) + '</b></td></tr></tbody></table>',
        '<div class="online-report-comments"><section><b>Tutor’s Remark</b><p>' + esc(teacherRemark) + '</p><p>Signature: ' + teacherSignature + '</p><p class="online-report-signature-date">Date: <span>' + signatureDate + '</span></p></section><section><b>Coordinator’s / Director’s Remark</b><p>' + esc(directorRemark) + '</p><p>Signature: ' + directorSignature + '</p><p class="online-report-signature-date">Date: <span>' + signatureDate + '</span></p></section></div>',
        '<div class="online-report-footer"><p>MARKAZ — Centre for Learning and Memorization of the Glorious Qur’an</p><span class="official-results-label">Academic Report • Official School Record</span></div>'
    ].join("");
}

window.MarkazOnlineClassReport = Object.freeze({ render });
})();
