/* MARKAZ Results Scanner: live camera, whole class sheet.
   "Scan results" opens the camera at once. Hold it over the sheet and move it slowly over the whole page.
   The sheet is read on this device: the subject names written at the top, every student row (admission
   number and name) and the three scores (CA1, CA2, Exam) under each subject. Any number of subjects on
   the sheet is supported, and the page can have 30 or more student rows.
   Results are saved automatically only when every student and every subject on the sheet has been read the
   same way on consecutive frames. Anything unclear goes to the review table instead. An existing, different
   score is never overwritten. Nothing is uploaded and no picture is kept. */
(() => {
    "use strict";
    if (window.MarkazResultsScanner) return;

    const ROLES = ["admin", "exam_officer", "teacher"];
    const FIELDS = ["ca1", "ca2", "exam"];
    const LABELS = { ca1: "CA1", ca2: "CA2", exam: "Exam" };
    const MAX_KEYS = { ca1: "max_ca1", ca2: "max_ca2", exam: "max_exam" };
    const READ_EVERY_MS = 400;
    const CONFIRM_READS = 2;
    const WARN_MS = 40000;
    const CAMERA = { width: 1920, height: 1440 };
    const OCR_MAX_WIDTH = 2000;
    const NUMBER = /^\d{1,3}(\.\d{1,2})?$/;

    const state = {
        meta: null, phase: "closed", stream: null, timer: null, reading: false, worker: null,
        startedAt: 0, warned: false, scopeKey: "", markTimer: null, hintTimer: null,
        subjectSeen: new Map(), subjectInfo: new Map(),
        votes: new Map(), lastSeen: new Map(), studentSeen: new Set(),
        previewUrl: null, torchOn: false, reviewSubjects: []
    };

    const $ = (selector, scope = document) => scope.querySelector(selector);
    const button = document.getElementById("openResultsScanner");
    const hint = document.createElement("p");
    hint.className = "mkls-hint";
    hint.hidden = true;
    hint.setAttribute("role", "status");
    if (button) button.insertAdjacentElement("afterend", hint);
    let overlay = null;

    function normalise(value) {
        return String(value || "").toLowerCase().normalize("NFKD")
            .replace(/[\u0300-\u036f\u064b-\u065f\u0670]/g, "")
            .replace(/[^\p{L}\p{N}]/gu, "");
    }

    function rosterMatch(line, students) {
        const candidate = normalise(line);
        if (!candidate) return null;

        const admissionMatches = students.filter(student => {
            const admission = normalise(student.admission_number);
            return admission.length >= 3 && candidate.includes(admission);
        });
        if (admissionMatches.length === 1) return admissionMatches[0];
        if (admissionMatches.length > 1) return null;

        const exactNameMatches = students.filter(student => {
            const name = normalise([student.first_name, student.middle_name, student.last_name].filter(Boolean).join(" "));
            return name.length >= 6 && candidate.includes(name);
        });
        if (exactNameMatches.length === 1) return exactNameMatches[0];
        if (exactNameMatches.length > 1) return null;

        const partialNameMatches = students.filter(student => {
            const first = normalise(student.first_name);
            const last = normalise(student.last_name);
            return first.length >= 2 && last.length >= 2 &&
                candidate.includes(first) && candidate.includes(last);
        });
        return partialNameMatches.length === 1 ? partialNameMatches[0] : null;
    }

    /* Conservative OCR fallback: only accept a clearly unique, close full-name match. */
    function fuzzyStudentMatch(line, students) {
        const candidate = normalise(line).replace(/\d+/g, "");
        if (candidate.length < 6 || !students.length) return null;

        const scored = students.map(student => {
            const full = normalise([
                student.first_name, student.middle_name, student.last_name
            ].filter(Boolean).join(" "));
            return { student, full, distance: editDistance(candidate, full) };
        }).filter(item => item.full.length >= 6)
          .sort((a, b) => a.distance - b.distance);

        const best = scored[0];
        const second = scored[1];
        if (!best) return null;

        const allowed = Math.max(1, Math.floor(best.full.length * 0.15));
        if (best.distance > allowed) return null;
        if (second && second.distance - best.distance < 2) return null;
        return best.student;
    }

    async function loadOcr() {
        if (window.Tesseract) return window.Tesseract;
        if (window.markazResultsOcrLoading) return window.markazResultsOcrLoading;
        window.markazResultsOcrLoading = new Promise((resolve, reject) => {
            const script = document.createElement("script");
            script.src = "https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js";
            script.async = true;
            script.onload = () => window.Tesseract ? resolve(window.Tesseract) : reject(new Error("OCR engine did not initialise."));
            script.onerror = () => reject(new Error("Could not load the reader. Check your internet connection, then try again."));
            document.head.appendChild(script);
        });
        try { return await window.markazResultsOcrLoading; }
        catch (error) { window.markazResultsOcrLoading = null; throw error; }
    }


    /* ------------------------------------------------------------------ text on the page */
    function editDistance(a, b) {
        const row = Array.from({ length: b.length + 1 }, (_, i) => i);
        for (let i = 1; i <= a.length; i++) {
            let previous = row[0];
            row[0] = i;
            for (let j = 1; j <= b.length; j++) {
                const current = row[j];
                row[j] = Math.min(row[j] + 1, row[j - 1] + 1, previous + (a[i - 1] === b[j - 1] ? 0 : 1));
                previous = current;
            }
        }
        return row[b.length];
    }

    function wordsOf(result) {
        const raw = (result && result.data && result.data.words) || [];
        return raw.map(word => {
            const box = word.bbox || {};
            return {
                text: String(word.text || "").trim(),
                x0: box.x0, x1: box.x1, y0: box.y0, y1: box.y1,
                cx: (box.x0 + box.x1) / 2, cy: (box.y0 + box.y1) / 2,
                h: Math.max(1, box.y1 - box.y0)
            };
        }).filter(word => word.text && [word.x0, word.x1, word.cx, word.cy].every(Number.isFinite));
    }

    /* words on the same line of the page form one row, read left to right */
    function toRows(words) {
        if (!words.length) return [];
        const heights = words.map(word => word.h).sort((a, b) => a - b);
        const tolerance = Math.max(6, heights[Math.floor(heights.length / 2)] * 0.6);
        const rows = [];
        [...words].sort((a, b) => a.cy - b.cy).forEach(word => {
            const last = rows[rows.length - 1];
            if (last && Math.abs(last.cy - word.cy) <= tolerance) {
                last.words.push(word);
                last.cy = last.words.reduce((sum, w) => sum + w.cy, 0) / last.words.length;
            } else {
                rows.push({ cy: word.cy, words: [word] });
            }
        });
        rows.forEach(row => row.words.sort((a, b) => a.cx - b.cx));
        return rows;
    }

    /* Match printed subject headings despite common OCR substitutions and word-splitting.
       A close match is accepted only against a subject assigned to this class. */
    function findSubjectSpans(words, subjects) {
        const spans = [];
        subjects.forEach(subject => {
            const target = normalise(subject.name);
            if (target.length < 3) return;
            const allowed = target.length >= 9 ? 2 : target.length >= 5 ? 1 : 0;
            let best = null;
            for (let i = 0; i < words.length; i++) {
                let joined = "";
                for (let j = i; j < Math.min(words.length, i + 7); j++) {
                    joined += normalise(words[j].text);
                    if (joined.length > target.length + allowed + 2) break;
                    if (joined.length < target.length - allowed) continue;
                    const distance = editDistance(joined, target);
                    if (distance > allowed) continue;
                    const group = words.slice(i, j + 1);
                    const score = distance / target.length + (group.length - 1) * 0.002;
                    if (!best || score < best.score) {
                        best = {
                            score,
                            span: {
                                subject,
                                x0: Math.min(...group.map(w => w.x0)),
                                x1: Math.max(...group.map(w => w.x1)),
                                count: group.length
                            }
                        };
                    }
                }
            }
            if (best) spans.push(best.span);
        });
        return spans.sort((a, b) => a.x0 - b.x0);
    }

    /* the column titles CA1, CA2 and Exam (one letter of OCR error allowed, e.g. "CAl1" for "CA1") */
    function findLabels(words) {
        const labels = [];
        const sorted = [...words].sort((a, b) => a.cx - b.cx);
        /* the closest title wins; a tie (or more than one letter of difference) is not a label */
        const kindOf = token => {
            const scores = [["ca1", editDistance(token, "ca1")], ["ca2", editDistance(token, "ca2")], ["exam", editDistance(token, "exam")]]
                .sort((a, b) => a[1] - b[1]);
            if (scores[0][1] > 1 || scores[0][1] === scores[1][1]) return null;
            return scores[0][0];
        };
        for (let i = 0; i < sorted.length; i++) {
            /* OCR often reads the digit 1 as a letter l or i in a title (CA1 -> CAl) */
            const token = normalise(sorted[i].text).replace(/[li]/g, "1");
            const kind = kindOf(token);
            if (kind) { labels.push({ kind, cx: sorted[i].cx }); continue; }
            /* "CA" and "1" written as two words */
            const next = sorted[i + 1] ? normalise(sorted[i + 1].text) : "";
            if (token === "ca" && (next === "1" || next === "2")) {
                labels.push({ kind: next === "1" ? "ca1" : "ca2", cx: (sorted[i].cx + sorted[i + 1].cx) / 2 });
                i++;
            }
        }
        return labels;
    }

    /* the centre of each column title (CA1, CA2, Exam) for every subject, matched to the nearest subject name */
    function columnsFor(spans, labels) {
        const columns = new Map();
        const groups = new Map(spans.map(span => [String(span.subject.id), { ca1: [], ca2: [], exam: [] }]));
        const centre = span => (span.x0 + span.x1) / 2;
        labels.forEach(label => {
            let best = null;
            spans.forEach(span => {
                const distance = Math.abs(label.cx - centre(span));
                if (!best || distance < best.distance) best = { span, distance };
            });
            if (best) groups.get(String(best.span.subject.id))[label.kind].push(label.cx);
        });
        groups.forEach((group, id) => {
            columns.set(id, FIELDS.every(field => group[field].length === 1)
                ? { ca1: group.ca1[0], ca2: group.ca2[0], exam: group.exam[0] }
                : null);
        });
        return columns;
    }

    /* the three scores of every subject on one student row, read from the digits-only pass.
       A score belongs to a column only when exactly one number sits under that column title. */
    function readRowScores(row, digits, spans, columns, tolerance) {
        const numbers = digits.filter(number => Math.abs(number.cy - row.cy) <= tolerance);
        const allColumns = [];
        columns.forEach(cols => { if (cols) FIELDS.forEach(field => allColumns.push(cols[field])); });
        const sorted = allColumns.slice().sort((x, y) => x - y);
        let spacing = Infinity;
        for (let i = 1; i < sorted.length; i++) spacing = Math.min(spacing, sorted[i] - sorted[i - 1]);
        const reach = Number.isFinite(spacing) ? spacing * 0.5 : 40;
        const cells = new Map();
        spans.forEach(span => {
            const id = String(span.subject.id);
            const cols = columns.get(id);
            if (!cols) { cells.set(id, null); return; }
            const values = {};
            let ok = true;
            FIELDS.forEach(field => {
                const near = numbers.filter(number => Math.abs(number.cx - cols[field]) <= reach);
                if (near.length !== 1) ok = false;
                else values[field] = Number(near[0].text);
            });
            cells.set(id, ok ? values : null);
        });
        return cells;
    }

    /* one camera frame. The text pass finds subject names and student rows; the digits pass reads the scores. */
    function parseFrame(textResult, digitResult, students, subjects) {
        const rows = toRows(wordsOf(textResult));
        const digits = wordsOf(digitResult).filter(word => NUMBER.test(word.text));
        const heights = wordsOf(textResult).map(word => word.h).sort((a, b) => a - b);
        const tolerance = Math.max(6, (heights[Math.floor(heights.length / 2)] || 20) * 0.6);
        const studentRows = [];
        const seen = new Set();
        rows.forEach(row => {
            const line = row.words.map(word => word.text).join(" ");
            const student = rosterMatch(line, students) || fuzzyStudentMatch(line, students);
            if (student && !seen.has(String(student.id))) {
                seen.add(String(student.id));
                studentRows.push({ row, student });
            }
        });
        /* Still inspect the page header when roster OCR misses a student row. The photo
           must not be rejected solely because one name or admission number was missed. */
        const firstStudentY = studentRows.length ? studentRows[0].row.cy : Infinity;
        const headerWords = rows.filter(row => row.cy < firstStudentY - 2).flatMap(row => row.words);
        const spans = findSubjectSpans(headerWords, subjects);
        const columns = columnsFor(spans, findLabels(headerWords));
        const read = studentRows.map(({ row, student }) => ({ student, cells: readRowScores(row, digits, spans, columns, tolerance) }));
        state.debug = {
            labels: findLabels(headerWords).map(label => `${label.kind}@${Math.round(label.cx)}`),
            columns: [...columns.entries()].map(([id, cols]) => `${id}:${cols ? `${Math.round(cols.ca1)},${Math.round(cols.ca2)},${Math.round(cols.exam)}` : "null"}`),
            firstRow: studentRows[0] ? { y: Math.round(studentRows[0].row.cy), numbers: digits.filter(n => Math.abs(n.cy - studentRows[0].row.cy) <= tolerance).map(n => `${n.text}@${Math.round(n.cx)}`) } : null,
            tolerance: Math.round(tolerance)
        };
        return { spans, read };
    }

    /* ------------------------------------------------------------------ collecting across frames */
    function resetSession() {
        state.subjectSeen = new Map();
        state.subjectInfo = new Map();
        state.votes = new Map();
        state.lastSeen = new Map();
        state.studentSeen = new Set();
        state.reviewSubjects = [];
    }

    function classStudents() { return (state.meta && state.meta.students) || []; }

    function confirmedSubjects() {
        return [...state.subjectSeen.keys()]
            .filter(id => state.subjectSeen.get(id) >= CONFIRM_READS)
            .map(id => state.subjectInfo.get(id))
            .sort((a, b) => a.name.localeCompare(b.name));
    }

    function confirmedValue(studentId, subjectId, field) {
        const vote = state.votes.get(`${studentId}|${subjectId}|${field}`);
        return vote && vote.count >= CONFIRM_READS ? vote.value : null;
    }

    function studentDone(student, subjects) {
        return subjects.every(subject => FIELDS.every(field => confirmedValue(String(student.id), String(subject.id), field) !== null));
    }

    function judge(parsed) {
        parsed.spans.forEach(span => {
            const id = String(span.subject.id);
            state.subjectInfo.set(id, span.subject);
            state.subjectSeen.set(id, (state.subjectSeen.get(id) || 0) + 1);
        });
        parsed.read.forEach(({ student, cells }) => {
            const studentId = String(student.id);
            state.studentSeen.add(studentId);
            cells.forEach((values, subjectId) => {
                if (!values) return;
                FIELDS.forEach(field => {
                    const key = `${studentId}|${subjectId}|${field}`;
                    const value = values[field];
                    state.lastSeen.set(key, value);
                    const vote = state.votes.get(key);
                    state.votes.set(key, vote && vote.value === value ? { value, count: vote.count + 1 } : { value, count: 1 });
                });
            });
        });

        const subjects = confirmedSubjects();
        const students = classStudents();
        const done = subjects.length ? students.filter(student => studentDone(student, subjects)).length : 0;
        if (subjects.length && students.length && done === students.length) { succeed(subjects); return; }

        if (!parsed.read.length && !subjects.length && !state.studentSeen.size) {
            setGuide("Looking for the result sheet. Move closer and keep the whole sheet in view.");
            return;
        }
        const names = subjects.length ? subjects.map(subject => subject.name).join(", ") : "not yet read";
        setGuide(`Subjects on this sheet: ${names}. Students complete: ${done} of ${students.length}. ` +
            (subjects.length ? "Keep moving the camera slowly over the whole sheet." : "Keep the subject names at the top of the sheet in view."));
    }

    /* ------------------------------------------------------------------ what to save */
    function sameNumber(a, b) { return a !== null && a !== undefined && a !== "" && Number(a) === Number(b); }

    /* entries: [{ student, subject, values: { ca1, ca2, exam } }]. Blank lines are never saved. */
    function planSave(entries) {
        const payloads = [], conflicts = [], unchanged = [], incomplete = [];
        const results = (state.meta && state.meta.results) || [];
        entries.forEach(({ student, subject, values }) => {
            const label = `${student.first_name} ${student.last_name} · ${subject.name}`;
            const filled = FIELDS.filter(field => values[field] !== null && values[field] !== undefined && values[field] !== "");
            if (!filled.length) return;
            if (filled.length < FIELDS.length) { incomplete.push(label); return; }
            const existing = results.find(result => Number(result.student_id) === Number(student.id) && Number(result.subject_id) === Number(subject.id));
            if (existing && FIELDS.some(field => existing[field] !== null && existing[field] !== undefined && !sameNumber(existing[field], values[field]))) {
                conflicts.push(label);
                return;
            }
            if (existing && FIELDS.every(field => sameNumber(existing[field], values[field]))) {
                unchanged.push(label);
                return;
            }
            payloads.push({
                class_type: state.meta.classType,
                class_id: Number(state.meta.classId),
                student_id: Number(student.id),
                subject_id: Number(subject.id),
                teacher_remark: existing ? (existing.teacher_remark ?? null) : null,
                result_remark: existing ? (existing.result_remark ?? null) : null,
                ca1: Number(values.ca1),
                ca2: Number(values.ca2),
                exam: Number(values.exam)
            });
        });
        return { payloads, conflicts, unchanged, incomplete };
    }

    function scoreRangeError(values, subject) {
        for (const field of FIELDS) {
            const score = Number(values[field]);
            if (!Number.isFinite(score) || score < 0 || score > Number(subject[MAX_KEYS[field]])) {
                return `${LABELS[field]} must be between 0 and ${subject[MAX_KEYS[field]]}.`;
            }
        }
        return "";
    }

    async function saveEntries(plan, messageNode) {
        if (plan.conflicts.length || plan.incomplete.length) {
            throw new Error("Resolve every conflicting or incomplete result row before saving. No results were saved.");
        }
        if (!plan.payloads.length) {
            if (typeof state.meta.onSaved === "function") {
                try { await state.meta.onSaved(); }
                catch (refreshError) { console.error("MARKAZ class view refresh failed:", refreshError); }
            }
            return 0;
        }
        messageNode.textContent = `Validating all ${plan.payloads.length} result rows…`;
        const first = plan.payloads[0];
        const entries = plan.payloads.map(({ student_id, subject_id, ca1, ca2, exam, teacher_remark, result_remark }) => ({
            student_id, subject_id, ca1, ca2, exam, teacher_remark, result_remark
        }));
        const response = await fetch("./api/results/batch-save.php", {
            method: "POST",
            credentials: "same-origin",
            cache: "no-store",
            headers: { "Content-Type": "application/json", "Accept": "application/json" },
            body: JSON.stringify({ class_type: first.class_type, class_id: first.class_id, entries })
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) throw new Error(data.message || "The result batch was not saved.");
        if (typeof state.meta.onSaved === "function") {
            try { await state.meta.onSaved(); }
            catch (refreshError) { console.error("MARKAZ results saved, but the class view did not refresh:", refreshError); }
        }
        return Number(data.saved || 0);
    }

    /* ------------------------------------------------------------------ the window */
    const TEMPLATE = `
      <div class="mkls-sheet" role="dialog" aria-modal="true" aria-labelledby="mklsTitle">
        <header class="mkls-top">
          <div class="mkls-titles">
            <p class="mkls-eyebrow">Scan results</p>
            <h2 id="mklsTitle" class="mkls-title">Scan the class sheet</h2>
          </div>
          <button type="button" class="mkls-close" data-mkls-close aria-label="Close">&times;</button>
        </header>
        <section class="mkls-camera" data-mkls-camera>
          <video class="mkls-video" playsinline muted autoplay></video>
          <img class="mkls-upload-preview" data-mkls-upload-preview alt="Selected result sheet preview" hidden>
          <div class="mkls-frame" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
          <div class="mkls-scanline" aria-hidden="true"></div>
          <div class="mkls-mark" data-mkls-mark hidden>
            <span class="mkls-mark-icon" data-mkls-mark-icon aria-hidden="true"></span>
            <strong class="mkls-mark-text" data-mkls-mark-text></strong>
          </div>
        </section>
        <section class="mkls-upload-status" data-mkls-upload-status hidden aria-live="polite">
          <div class="mkls-class-facts">
            <strong data-mkls-class-name>Class</strong>
            <span><b data-mkls-student-count>0</b> students</span>
            <span><b data-mkls-subject-count>0</b> subjects</span>
          </div>
          <div class="mkls-progress-track" role="progressbar" aria-label="Photo scanning progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-mkls-progress>
            <span data-mkls-progress-fill></span>
          </div>
          <div class="mkls-progress-meta"><span data-mkls-progress-label>Preparing photo…</span><strong data-mkls-progress-value>0%</strong></div>
        </section>
        <p class="mkls-guide" data-mkls-guide role="status" aria-live="polite"></p>
        <div class="mkls-controls" data-mkls-bar>
          <div class="mkls-control-row">
            <button type="button" class="mkls-icon-btn" data-mkls-upload-photo aria-label="Upload photo" title="Upload photo">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h3l1.5-2h7L17 7h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1Z"/><circle cx="12" cy="13" r="3.5"/><path d="M12 2v5m-2-2 2-2 2 2"/></svg>
            </button>
            <button type="button" class="mkls-icon-btn" data-mkls-torch aria-label="Turn torch on" aria-pressed="false" title="Turn torch on" disabled>
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 2h6l-1 7h3l-7 13 1-9H8l1-11Z"/></svg>
            </button>
          </div>
          <button type="button" class="mkls-btn ghost mkls-review-control" data-mkls-review>Review scanned results</button>
          <input type="file" data-mkls-photo-input accept="image/*" hidden>
        </div>
        <section class="mkls-preview" data-mkls-preview hidden>
          <p class="mkls-preview-note" data-mkls-note></p>
          <div class="mkls-table-wrap">
            <table class="mkls-table">
              <thead><tr><th>Student</th><th>Subject</th><th>CA1</th><th>CA2</th><th>Exam</th><th>Total</th></tr></thead>
              <tbody data-mkls-rows></tbody>
            </table>
          </div>
          <div class="mkls-bar">
            <button type="button" class="mkls-btn ghost" data-mkls-rescan>Scan again</button>
            <button type="button" class="mkls-btn primary" data-mkls-save>Save reviewed results</button>
          </div>
          <p class="mkls-save-msg" data-mkls-save-msg role="status" aria-live="polite"></p>
        </section>
        <section class="mkls-summary" data-mkls-summary hidden>
          <p class="mkls-summary-title" data-mkls-summary-title></p>
          <ul class="mkls-summary-list" data-mkls-summary-list></ul>
          <div class="mkls-bar"><button type="button" class="mkls-btn primary" data-mkls-done>Done</button></div>
        </section>
      </div>`;

    function buildOverlay() {
        overlay = document.createElement("div");
        overlay.className = "mkls-overlay";
        overlay.hidden = true;
        overlay.innerHTML = TEMPLATE;
        document.body.appendChild(overlay);
        $("[data-mkls-close]", overlay).addEventListener("click", closeScanner);
        $("[data-mkls-done]", overlay).addEventListener("click", closeScanner);
        $("[data-mkls-review]", overlay).addEventListener("click", reviewNow);
        $("[data-mkls-rescan]", overlay).addEventListener("click", startCamera);
        $("[data-mkls-upload-photo]", overlay).addEventListener("click", () => $("[data-mkls-photo-input]", overlay).click());
        $("[data-mkls-photo-input]", overlay).addEventListener("change", event => {
            const file = event.target.files && event.target.files[0];
            event.target.value = "";
            if (file) processUploadedFiles([file]);
        });
        $("[data-mkls-torch]", overlay).addEventListener("click", toggleTorch);
        $("[data-mkls-save]", overlay).addEventListener("click", saveReviewed);
        document.addEventListener("keydown", event => {
            if (event.key === "Escape" && state.phase !== "closed") closeScanner();
        });
    }

    function showHint(text) {
        hint.textContent = text;
        hint.hidden = false;
        clearTimeout(state.hintTimer);
        state.hintTimer = setTimeout(() => { hint.hidden = true; }, 5000);
    }

    function setGuide(text) {
        const guide = $("[data-mkls-guide]", overlay);
        if (guide) guide.textContent = text;
    }

    function setMark(kind, text, hideAfter) {
        const mark = $("[data-mkls-mark]", overlay);
        clearTimeout(state.markTimer);
        if (!kind) { mark.hidden = true; return; }
        mark.hidden = false;
        mark.dataset.kind = kind;
        $("[data-mkls-mark-icon]", overlay).textContent = kind === "ok" ? "✓" : "!";
        $("[data-mkls-mark-text]", overlay).textContent = text;
        if (hideAfter) state.markTimer = setTimeout(() => { mark.hidden = true; }, hideAfter);
    }

    function showPhase(name) {
        state.phase = name;
        const imagePreview = $("[data-mkls-upload-preview]", overlay);
        const hasPhoto = Boolean(state.previewUrl);
        $("[data-mkls-camera]", overlay).hidden = !(name === "camera" || name === "uploading" || name === "review" || name === "marking");
        if (imagePreview) imagePreview.hidden = !hasPhoto;
        const video = videoEl();
        if (video) video.hidden = hasPhoto;
        $("[data-mkls-guide]", overlay).hidden = !(name === "camera" || name === "uploading" || name === "marking" || name === "saving");
        $("[data-mkls-bar]", overlay).hidden = !["camera", "uploading", "review"].includes(name);
        $("[data-mkls-review]", overlay).disabled = name !== "camera";
        $("[data-mkls-preview]", overlay).hidden = name !== "review";
        $("[data-mkls-summary]", overlay).hidden = name !== "summary";
        $("[data-mkls-upload-status]", overlay).hidden = !["uploading", "review"].includes(name) || !hasPhoto;
    }

    function setUploadProgress(percent, label) {
        const value = Math.max(0, Math.min(100, Math.round(percent)));
        const progress = $("[data-mkls-progress]", overlay);
        progress.setAttribute("aria-valuenow", String(value));
        $("[data-mkls-progress-fill]", overlay).style.width = value + "%";
        $("[data-mkls-progress-value]", overlay).textContent = value + "%";
        $("[data-mkls-progress-label]", overlay).textContent = label;
    }

    function resetPhotoPreview() {
        if (state.previewUrl) URL.revokeObjectURL(state.previewUrl);
        state.previewUrl = null;
        const preview = $("[data-mkls-upload-preview]", overlay);
        if (preview) { preview.removeAttribute("src"); preview.hidden = true; }
        const video = videoEl();
        if (video) video.hidden = false;
    }

    async function toggleTorch() {
        const track = state.stream && state.stream.getVideoTracks()[0];
        const control = $("[data-mkls-torch]", overlay);
        if (!track || typeof track.applyConstraints !== "function") return;
        const next = !state.torchOn;
        try {
            await track.applyConstraints({ advanced: [{ torch: next }] });
            state.torchOn = next;
            control.setAttribute("aria-pressed", String(next));
            control.setAttribute("aria-label", next ? "Turn torch off" : "Turn torch on");
            control.title = next ? "Turn torch off" : "Turn torch on";
            control.classList.toggle("is-on", next);
        } catch (error) {
            setGuide("The torch is not supported by this camera.");
        }
    }

    function syncTorchAvailability() {
        const control = $("[data-mkls-torch]", overlay);
        const track = state.stream && state.stream.getVideoTracks()[0];
        const capabilities = track && track.getCapabilities ? track.getCapabilities() : {};
        const supported = Boolean(capabilities && capabilities.torch);
        control.disabled = !supported;
        if (!supported) {
            state.torchOn = false;
            control.setAttribute("aria-pressed", "false");
            control.setAttribute("aria-label", "Torch unavailable");
            control.title = "Torch unavailable on this camera";
            control.classList.remove("is-on");
        }
    }

    function videoEl() { return overlay ? $("video", overlay) : null; }

    /* ------------------------------------------------------------------ camera */
    function stopStream() {
        if (state.stream) { state.stream.getTracks().forEach(track => track.stop()); state.stream = null; }
        state.torchOn = false;
        const control = overlay && $("[data-mkls-torch]", overlay);
        if (control) {
            control.setAttribute("aria-pressed", "false");
            control.setAttribute("aria-label", "Turn torch on");
            control.title = "Turn torch on";
            control.classList.remove("is-on");
            control.disabled = true;
        }
        const video = videoEl();
        if (video) video.srcObject = null;
    }

    function scheduleRead(delay) {
        clearTimeout(state.timer);
        state.timer = setTimeout(readFrame, delay);
    }

    function captureFrame(video) {
        const scale = Math.min(1, OCR_MAX_WIDTH / video.videoWidth);
        const canvas = document.createElement("canvas");
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        canvas.getContext("2d").drawImage(video, 0, 0, canvas.width, canvas.height);
        return canvas;
    }

    async function prepareWorker() {
        if (state.worker) return;
        const Tesseract = await loadOcr();
        const language = state.meta.classType === "arabic" ? "ara+eng" : "eng";
        const worker = await Tesseract.createWorker(language, 1, {});
        await worker.setParameters({ tessedit_pageseg_mode: "6", preserve_interword_spaces: "1" }).catch(() => {});
        state.worker = worker;
    }

    async function startCamera() {
        if (!overlay) buildOverlay();
        clearTimeout(state.timer);
        stopStream();
        showPhase("camera");
        setMark(null);
        state.warned = false;
        setGuide("Opening the camera…");

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setGuide("This browser cannot open the camera. Use Chrome or Edge on a secure (https) address.");
            return;
        }
        try {
            state.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: "environment" }, width: { ideal: CAMERA.width }, height: { ideal: CAMERA.height } },
                audio: false
            });
        } catch (error) {
            setGuide("Camera access is needed. Allow the camera for this site in your browser settings, then tap Scan again.");
            return;
        }
        if (state.phase !== "camera") { stopStream(); return; }

        const video = videoEl();
        resetPhotoPreview();
        video.hidden = false;
        video.srcObject = state.stream;
        await video.play().catch(() => {});
        syncTorchAvailability();
        state.startedAt = Date.now();

        setGuide("Getting the reader ready…");
        try {
            await prepareWorker();
        } catch (error) {
            setGuide(error.message || "Could not start the reader.");
            return;
        }
        if (state.phase !== "camera") return;
        setGuide("Hold the camera over the whole sheet. Start with the subject names at the top, then move slowly down the page.");
        scheduleRead(300);
    }

    async function processUploadedFiles(files) {
        clearTimeout(state.timer);
        stopStream();
        resetSession();
        resetPhotoPreview();
        const file = files.find(item => item && /^image\//i.test(item.type));
        if (!file) {
            showPhase("camera");
            setGuide("Choose a supported photo of the result sheet.");
            return;
        }

        state.previewUrl = URL.createObjectURL(file);
        const preview = $("[data-mkls-upload-preview]", overlay);
        preview.src = state.previewUrl;
        preview.hidden = false;
        const className = (document.getElementById("classTitle")?.textContent || "Selected class").trim();
        $("[data-mkls-class-name]", overlay).textContent = className;
        $("[data-mkls-student-count]", overlay).textContent = String(classStudents().length);
        $("[data-mkls-subject-count]", overlay).textContent = String((state.meta.subjects || []).length);
        setUploadProgress(1, "Preparing photo");
        showPhase("uploading");
        setGuide("Your photo is ready. Reading the result sheet on this device…");

        let bitmap = null;
        try {
            setUploadProgress(8, "Preparing scanner");
            await prepareWorker();
            setUploadProgress(18, "Photo ready — reading text");
            bitmap = await createImageBitmap(file);
            const scale = Math.min(1, OCR_MAX_WIDTH / bitmap.width);
            const canvas = document.createElement("canvas");
            canvas.width = Math.max(1, Math.round(bitmap.width * scale));
            canvas.height = Math.max(1, Math.round(bitmap.height * scale));
            const context = canvas.getContext("2d");
            if (!context) throw new Error("Image processing is unavailable.");
            context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

            await state.worker.setParameters({ tessedit_char_whitelist: "" });
            setUploadProgress(30, "Reading student names and subjects");
            const textResult = await state.worker.recognize(canvas);
            setUploadProgress(68, "Reading CA1, CA2 and Exam marks");
            await state.worker.setParameters({ tessedit_char_whitelist: "0123456789." });
            const digitResult = await state.worker.recognize(canvas);
            await state.worker.setParameters({ tessedit_char_whitelist: "" });
            setUploadProgress(88, "Matching the class roster");
            let parsed = parseFrame(textResult, digitResult, classStudents(), state.meta.subjects || []);
            if (!parsed.spans.length) {
                /* Sparse-text mode is more reliable for printed headings separated by table borders
                   or wide gaps. Retry only when the normal page layout found no subject at all. */
                setUploadProgress(78, "Checking printed subject headings");
                await state.worker.setParameters({ tessedit_char_whitelist: "", tessedit_pageseg_mode: "11" });
                const sparseTextResult = await state.worker.recognize(canvas);
                await state.worker.setParameters({ tessedit_pageseg_mode: "6" });
                const sparseParsed = parseFrame(sparseTextResult, digitResult, classStudents(), state.meta.subjects || []);
                if (sparseParsed.spans.length > parsed.spans.length ||
                    (sparseParsed.spans.length === parsed.spans.length && sparseParsed.read.length > parsed.read.length)) {
                    parsed = sparseParsed;
                }
            }

            parsed.spans.forEach(span => {
                const id = String(span.subject.id);
                state.subjectInfo.set(id, span.subject);
                state.subjectSeen.set(id, CONFIRM_READS);
            });
            parsed.read.forEach(({ student, cells }) => {
                state.studentSeen.add(String(student.id));
                cells.forEach((values, subjectId) => {
                    if (!values) return;
                    FIELDS.forEach(field => state.lastSeen.set(`${student.id}|${subjectId}|${field}`, values[field]));
                });
            });
            setUploadProgress(96, "Checking detected scores");

            const subjects = confirmedSubjects();
            const reviewSubjects = (state.meta.subjects || []).length ? state.meta.subjects : subjects;
            setUploadProgress(100, "Scan complete");
            buildReview(reviewSubjects);
            showPhase("review");
            const detectedStudents = state.studentSeen.size;
            const captured = Math.round(state.lastSeen.size / FIELDS.length);
            const missing = Math.max(0, classStudents().length * reviewSubjects.length - captured);
            const invalid = [...$("[data-mkls-rows]", overlay).querySelectorAll("tr.needs-check")].length;
            if (!subjects.length) {
                $("[data-mkls-note]", overlay).textContent =
                    "The photo was processed, but the printed subject headings could not be matched confidently. All subjects assigned to this class are listed below; verify the subject rows and enter or correct any missing marks before saving.";
                setGuide("Your photo remains available above. Review every assigned subject and check the marks before saving.");
                return;
            }
            $("[data-mkls-note]", overlay).textContent =
                `Scan complete: ${detectedStudents} of ${classStudents().length} students detected; ${subjects.length} subject heading(s) recognised. ${captured} score line(s) read; ${missing} need checking. ${invalid ? `${invalid} row(s) need review.` : "No range errors detected."} All assigned subjects are shown for review.`;
            setGuide("Review every subject and the highlighted rows, then confirm all scores before saving.");
        } catch (error) {
            setUploadProgress(100, "Scan could not finish");
            setGuide(error.message || "The photo could not be read. Your selected photo is still visible; try another clear image.");
            state.phase = "uploading";
            showPhase("uploading");
        } finally {
            await state.worker?.setParameters({ tessedit_char_whitelist: "" }).catch(() => {});
            bitmap?.close?.();
        }
    }

    async function readFrame() {
        if (state.phase !== "camera" || state.reading) return;
        const video = videoEl();
        if (!state.worker || !video || !video.videoWidth) { scheduleRead(300); return; }
        state.reading = true;
        try {
            const canvas = captureFrame(video);
            await state.worker.setParameters({ tessedit_char_whitelist: "" });
            const textResult = await state.worker.recognize(canvas);
            await state.worker.setParameters({ tessedit_char_whitelist: "0123456789." });
            const digitResult = await state.worker.recognize(canvas);
            await state.worker.setParameters({ tessedit_char_whitelist: "" });
            if (state.phase !== "camera") return;
            judge(parseFrame(textResult, digitResult, classStudents(), state.meta.subjects || []));
        } catch (error) {
            setGuide("The reader paused. Keep the sheet in view.");
        } finally {
            state.reading = false;
            if (state.phase === "camera") {
                if (!state.warned && Date.now() - state.startedAt > WARN_MS) warnNotDone();
                scheduleRead(READ_EVERY_MS);
            }
        }
    }

    function warnNotDone() {
        state.warned = true;
        const subjects = confirmedSubjects();
        const students = classStudents();
        const done = subjects.length ? students.filter(student => studentDone(student, subjects)).length : 0;
        setMark("warn", `${done} of ${students.length} complete`, 3000);
        setGuide("Not everything is clear yet. Keep moving the camera slowly, or tap Review scanned students to check the rest by hand.");
    }

    /* the whole sheet is clear: stop the camera, show the tick, save automatically */
    function succeed(subjects) {
        showPhase("marking");
        stopStream();
        clearTimeout(state.timer);
        buildReview(subjects);
        showPhase("review");
        $("[data-mkls-note]", overlay).textContent =
            `Scan complete: all ${classStudents().length} students were read across ${subjects.length} subject(s). Review the marks, then save.`;
        setGuide("The scan is complete. Review the marks before saving.");
    }

    async function autoSave(subjects) {
        showPhase("saving");
        const entries = [];
        classStudents().forEach(student => subjects.forEach(subject => {
            const values = {};
            FIELDS.forEach(field => { values[field] = confirmedValue(String(student.id), String(subject.id), field); });
            entries.push({ student, subject, values });
        }));
        const outOfRange = entries.filter(entry => scoreRangeError(entry.values, entry.subject)).length;
        if (outOfRange) {
            buildReview(subjects);
            showPhase("review");
            $("[data-mkls-note]", overlay).textContent =
                `${outOfRange} score line(s) need correction before anything can be saved. Review the highlighted values; no results have been saved yet.`;
            return;
        }
        const plan = planSave(entries);
        let saved = 0, failure = "";
        try {
            saved = await saveEntries(plan, $("[data-mkls-guide]", overlay));
        } catch (error) {
            saved = error.saved || 0;
            failure = error.message || "Save failed.";
        }
        showSummary(plan, saved, failure, outOfRange);
    }

    function showSummary(plan, saved, failure, outOfRange) {
        showPhase("summary");
        $("[data-mkls-summary-title]", overlay).textContent = failure
            ? `No results were saved because the batch was rejected: ${failure}`
            : `${saved} result(s) saved to student results.`;
        const list = $("[data-mkls-summary-list]", overlay);
        list.replaceChildren();
        const add = text => { const item = document.createElement("li"); item.textContent = text; list.appendChild(item); };
        if (plan.unchanged.length) add(`${plan.unchanged.length} already saved with the same scores.`);
        if (plan.conflicts.length) add(`${plan.conflicts.length} not changed, because the school already has a different score: ${plan.conflicts.slice(0, 6).join("; ")}${plan.conflicts.length > 6 ? "…" : ""}`);
        if (plan.incomplete.length) add(`${plan.incomplete.length} not saved, because a score was missing: ${plan.incomplete.slice(0, 6).join("; ")}`);
        if (outOfRange) add(`${outOfRange} score line(s) were outside the allowed range and were not saved.`);
        if (!failure && !plan.payloads.length && !plan.conflicts.length) add("Nothing new needed saving.");
    }

    /* ------------------------------------------------------------------ review (the manual fallback) */
    function reviewNow() {
        if (state.phase !== "camera") return;
        const subjects = confirmedSubjects();
        if (!subjects.length) {
            setGuide("No subject names have been read yet. Move the camera to the top of the sheet, where the subject names are written.");
            return;
        }
        clearTimeout(state.timer);
        stopStream();
        buildReview(subjects);
        showPhase("review");
    }

    function buildReview(subjects) {
        state.reviewSubjects = [...subjects];
        const body = $("[data-mkls-rows]", overlay);
        body.replaceChildren();
        let missing = 0;
        classStudents().forEach(student => subjects.forEach(subject => {
            const studentId = String(student.id), subjectId = String(subject.id);
            const values = {};
            let clear = true;
            FIELDS.forEach(field => {
                const confirmed = confirmedValue(studentId, subjectId, field);
                const lastSeen = state.lastSeen.get(`${studentId}|${subjectId}|${field}`);
                values[field] = confirmed !== null ? confirmed : (lastSeen ?? "");
                if (confirmed === null) clear = false;
            });
            const empty = FIELDS.every(field => values[field] === "");
            if (empty) missing++;
            clear = clear && !scoreRangeError(values, subject);
            body.appendChild(reviewRow(student, subject, values, clear, empty));
        }));
        $("[data-mkls-note]", overlay).textContent = missing
            ? `${missing} score line(s) not captured yet. Scan again to capture them, or type the scores. Blank lines are never saved.`
            : "Check each score, then save reviewed results.";
        $("[data-mkls-save-msg]", overlay).textContent = "";
    }

    function reviewRow(student, subject, values, clear, empty) {
        const tr = document.createElement("tr");
        tr.dataset.studentId = String(student.id);
        tr.dataset.subjectId = String(subject.id);
        if (!clear) tr.className = "needs-check";
        const name = [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(" ");
        const nameCell = document.createElement("td");
        nameCell.textContent = name;
        const subjectCell = document.createElement("td");
        subjectCell.textContent = subject.name;
        tr.append(nameCell, subjectCell);
        FIELDS.forEach(field => {
            const td = document.createElement("td");
            const input = document.createElement("input");
            input.type = "number";
            input.inputMode = "decimal";
            input.min = "0";
            input.max = String(subject[MAX_KEYS[field]]);
            input.step = "any";
            input.dataset.field = field;
            input.setAttribute("aria-label", `${LABELS[field]} for ${name}, ${subject.name}`);
            input.value = values[field] === "" || values[field] === null ? "" : String(values[field]);
            input.addEventListener("input", () => updateTotal(tr));
            td.appendChild(input);
            tr.appendChild(td);
        });
        const total = document.createElement("td");
        total.className = "mkls-total";
        tr.appendChild(total);
        updateTotal(tr);
        if (empty) total.textContent = "Not captured";
        return tr;
    }

    function updateTotal(tr) {
        const values = FIELDS.map(field => tr.querySelector(`[data-field="${field}"]`).value.trim());
        const total = tr.querySelector(".mkls-total");
        if (values.some(value => value === "")) { total.textContent = values.every(value => value === "") ? "—" : "Check"; return; }
        total.textContent = String(values.reduce((sum, value) => sum + Number(value), 0));
    }

    async function saveReviewed() {
        if (state.phase !== "review") return;
        const message = $("[data-mkls-save-msg]", overlay);
        const saveButton = $("[data-mkls-save]", overlay);
        const subjectsById = new Map((state.reviewSubjects.length ? state.reviewSubjects : confirmedSubjects())
            .map(subject => [String(subject.id), subject]));
        const studentsById = new Map(classStudents().map(student => [String(student.id), student]));
        const entries = [];
        for (const tr of overlay.querySelectorAll("[data-mkls-rows] tr")) {
            const subject = subjectsById.get(tr.dataset.subjectId);
            const student = studentsById.get(tr.dataset.studentId);
            if (!subject || !student) continue;
            const raw = FIELDS.map(field => tr.querySelector(`[data-field="${field}"]`).value.trim());
            if (raw.every(value => value === "")) continue;
            const values = {};
            FIELDS.forEach((field, index) => { values[field] = raw[index] === "" ? "" : Number(raw[index]); });
            if (raw.some(value => value === "")) {
                message.textContent = `${student.first_name} ${student.last_name} · ${subject.name}: fill in all three scores or clear the line.`;
                return;
            }
            const error = scoreRangeError(values, subject);
            if (error) {
                message.textContent = `${student.first_name} ${student.last_name} · ${subject.name}: ${error}`;
                return;
            }
            entries.push({ student, subject, values });
        }
        const plan = planSave(entries);
        if (!plan.payloads.length) {
            message.textContent = plan.conflicts.length
                ? "Nothing was saved: the school already has different scores for these lines."
                : "There are no complete score lines to save.";
            return;
        }
        saveButton.disabled = true;
        let saved = 0, failure = "";
        try {
            saved = await saveEntries(plan, message);
        } catch (error) {
            saved = error.saved || 0;
            failure = error.message || "Save failed.";
        }
        saveButton.disabled = false;
        showSummary(plan, saved, failure, 0);
    }

    /* ------------------------------------------------------------------ open / close */
    function openScanner() {
        if (!overlay) buildOverlay();
        resetSession();
        overlay.hidden = false;
        document.body.classList.add("mkls-open");
        startCamera();
    }

    function closeScanner() {
        state.phase = "closed";
        clearTimeout(state.timer);
        clearTimeout(state.markTimer);
        stopStream();
        if (state.worker) { state.worker.terminate().catch(() => {}); state.worker = null; }
        resetPhotoPreview();
        if (overlay) overlay.hidden = true;
        document.body.classList.remove("mkls-open");
    }

    button?.addEventListener("click", () => {
        const meta = state.meta;
        if (!meta || !ROLES.includes(meta.role)) return;
        if (!classStudents().length) { showHint("The class list is still loading. Try again in a moment."); return; }
        if (!(meta.subjects || []).length) { showHint("This class has no subjects set up yet."); return; }
        openScanner();
    });

    /* class.js gives the scanner the class, the roster, the class subjects and the saved results */
    function configure(config) {
        const next = config || {};
        state.meta = next;
        if (button) button.hidden = !ROLES.includes(next.role);
        const key = [next.classId, next.classType].join("|");
        if (key !== state.scopeKey) {
            state.scopeKey = key;
            if (state.phase !== "closed") closeScanner();
        }
    }

    /* a readable summary of what is still missing (used by support and by the tests) */
    function status() {
        const subjects = confirmedSubjects();
        const missing = [];
        classStudents().forEach(student => subjects.forEach(subject => FIELDS.forEach(field => {
            if (confirmedValue(String(student.id), String(subject.id), field) === null) {
                missing.push(`${student.admission_number} ${subject.name} ${LABELS[field]} (last read: ${state.lastSeen.get(`${student.id}|${subject.id}|${field}`) ?? "none"})`);
            }
        })));
        return { subjects: subjects.map(subject => subject.name), missing, debug: state.debug };
    }

    window.MarkazResultsScanner = { configure, status };
})();
