/* MARKAZ Results Scanner (live camera).
   Press "Scan results": the camera opens straight away. Hold it over the result sheet and move it slowly.
   The sheet is read on this device, frame by frame. Nothing is uploaded and no picture is kept.
   When every score line is read clearly and stays the same on two readings, a green tick appears and
   the scores are shown in a preview. Saving sends each student's scores through the normal result-save path. */
(() => {
    "use strict";
    if (window.MarkazResultsScanner) return;

    const ROLES = ["admin", "exam_officer", "teacher"];
    const FIELDS = ["ca1", "ca2", "exam"];
    const LABELS = ["CA1", "CA2", "Exam"];
    const MAX_KEYS = ["max_ca1", "max_ca2", "max_exam"];
    const READ_EVERY_MS = 650;
    const CLEAR_WARNING_MS = 30000;
    const STABLE_READS = 2;

    const state = { meta: null, phase: "closed", stream: null, timer: null, reading: false, worker: null,
        startedAt: 0, clearWarned: false, scopeKey: "",
        seen: new Map(), confirmed: new Map(), unclear: new Map() };

    const $ = (selector, scope = document) => scope.querySelector(selector);
    const button = document.getElementById("openResultsScanner");
    const hint = document.createElement("p");
    hint.className = "mkls-hint";
    hint.hidden = true;
    hint.setAttribute("role", "status");
    if (button) button.insertAdjacentElement("afterend", hint);
    let hintTimer = null;
    let markTimer = null;
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

    function extractRows(lines, students, subject) {
        const found = new Map();
        for (const line of lines) {
            const text = String(line.text || "")
                .replace(/[٠-٩]/g, digit => String("٠١٢٣٤٥٦٧٨٩".indexOf(digit)))
                .trim();
            const student = rosterMatch(text, students);
            if (!student || found.has(String(student.id))) continue;
            const name = [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(" ");
            const tokens = text.match(/(?:^|\s)(\d{1,3}(?:\.\d{1,2})?)(?=\s|$)/g) || [];
            const scores = tokens.map(token => Number(token.trim())).filter(Number.isFinite);
            const max = [Number(subject.max_ca1), Number(subject.max_ca2), Number(subject.max_exam)];
            const plausible = scores.length >= 3 ? scores.slice(-3) : [];
            const valid = plausible.length === 3 && plausible.every((score, index) => score >= 0 && score <= max[index]);
            found.set(String(student.id), {
                student,
                ca1: valid ? plausible[0] : "",
                ca2: valid ? plausible[1] : "",
                exam: valid ? plausible[2] : "",
                detected: valid,
                source: text
            });
        }
        return [...found.values()];
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

    /* ------------------------------------------------------------------ the window */
    const TEMPLATE = `
      <div class="mkls-sheet" role="dialog" aria-modal="true" aria-labelledby="mklsTitle">
        <header class="mkls-top">
          <div class="mkls-titles">
            <p class="mkls-eyebrow">Scan results</p>
            <h2 id="mklsTitle" class="mkls-title"></h2>
          </div>
          <button type="button" class="mkls-close" data-mkls-close aria-label="Close">&times;</button>
        </header>
        <section class="mkls-camera" data-mkls-camera>
          <video class="mkls-video" playsinline muted autoplay></video>
          <div class="mkls-frame" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
          <div class="mkls-scanline" aria-hidden="true"></div>
          <div class="mkls-mark" data-mkls-mark hidden>
            <span class="mkls-mark-icon" data-mkls-mark-icon aria-hidden="true"></span>
            <strong class="mkls-mark-text" data-mkls-mark-text></strong>
          </div>
        </section>
        <p class="mkls-guide" data-mkls-guide role="status" aria-live="polite"></p>
        <div class="mkls-bar" data-mkls-bar>
          <button type="button" class="mkls-btn ghost" data-mkls-capture>Review scanned students</button>
        </div>
        <section class="mkls-preview" data-mkls-preview hidden>
          <p class="mkls-preview-note" data-mkls-note></p>
          <div class="mkls-table-wrap">
            <table class="mkls-table">
              <thead><tr><th>Student</th><th>Admission no.</th><th>CA1</th><th>CA2</th><th>Exam</th><th>Total</th></tr></thead>
              <tbody data-mkls-rows></tbody>
            </table>
          </div>
          <div class="mkls-bar">
            <button type="button" class="mkls-btn ghost" data-mkls-rescan>Scan again</button>
            <button type="button" class="mkls-btn primary" data-mkls-save>Save to student results</button>
          </div>
          <p class="mkls-save-msg" data-mkls-save-msg role="status" aria-live="polite"></p>
        </section>
      </div>`;

    function buildOverlay() {
        overlay = document.createElement("div");
        overlay.className = "mkls-overlay";
        overlay.hidden = true;
        overlay.innerHTML = TEMPLATE;
        document.body.appendChild(overlay);
        $("[data-mkls-close]", overlay).addEventListener("click", closeScanner);
        $("[data-mkls-capture]", overlay).addEventListener("click", captureNow);
        $("[data-mkls-rescan]", overlay).addEventListener("click", startCamera);
        $("[data-mkls-save]", overlay).addEventListener("click", saveRows);
        document.addEventListener("keydown", event => {
            if (event.key === "Escape" && state.phase !== "closed") closeScanner();
        });
    }

    function showHint(text) {
        if (!hint) return;
        hint.textContent = text;
        hint.hidden = false;
        clearTimeout(hintTimer);
        hintTimer = setTimeout(() => { hint.hidden = true; }, 5000);
    }

    function setGuide(text) {
        const guide = $("[data-mkls-guide]", overlay);
        if (guide) guide.textContent = text;
    }

    function setMark(kind, text, hideAfter) {
        const mark = $("[data-mkls-mark]", overlay);
        clearTimeout(markTimer);
        if (!kind) { mark.hidden = true; return; }
        mark.hidden = false;
        mark.dataset.kind = kind;
        $("[data-mkls-mark-icon]", overlay).textContent = kind === "ok" ? "✓" : "!";
        $("[data-mkls-mark-text]", overlay).textContent = text;
        if (hideAfter) markTimer = setTimeout(() => { mark.hidden = true; }, hideAfter);
    }

    function showPhase(name) {
        state.phase = name;
        const cameraOn = name === "camera" || name === "marking";
        $("[data-mkls-camera]", overlay).hidden = !cameraOn;
        $("[data-mkls-guide]", overlay).hidden = !cameraOn;
        $("[data-mkls-bar]", overlay).hidden = name !== "camera";
        $("[data-mkls-preview]", overlay).hidden = !(name === "preview" || name === "saving");
    }

    function videoEl() { return overlay ? $("video", overlay) : null; }

    /* ------------------------------------------------------------------ camera */
    function stopStream() {
        if (state.stream) { state.stream.getTracks().forEach(track => track.stop()); state.stream = null; }
        const video = videoEl();
        if (video) video.srcObject = null;
    }

    async function prepareWorker() {
        if (state.worker) return;
        const Tesseract = await loadOcr();
        const language = state.meta.classType === "arabic" ? "ara+eng" : "eng";
        state.worker = await Tesseract.createWorker(language, 1, {});
    }

    function scheduleRead(delay) {
        clearTimeout(state.timer);
        state.timer = setTimeout(readFrame, delay);
    }

    function captureFrame(video) {
        const scale = Math.min(1, 1800 / video.videoWidth);
        const canvas = document.createElement("canvas");
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        canvas.getContext("2d").drawImage(video, 0, 0, canvas.width, canvas.height);
        return canvas;
    }

    async function startCamera() {
        if (!overlay) buildOverlay();
        clearTimeout(state.timer);
        stopStream();
        showPhase("camera");
        setMark(null);
        state.clearWarned = false;
        setGuide("Opening the camera…");

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setGuide("This browser cannot open the camera. Use Chrome or Edge on a secure (https) address.");
            return;
        }
        try {
            state.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: "environment" }, width: { ideal: 1920 }, height: { ideal: 1440 } },
                audio: false
            });
        } catch (error) {
            setGuide("Camera access is needed. Allow the camera for this site in your browser settings, then tap Scan again.");
            return;
        }
        if (state.phase !== "camera") { stopStream(); return; }

        const video = videoEl();
        video.srcObject = state.stream;
        await video.play().catch(() => {});
        state.startedAt = Date.now();

        setGuide("Getting the reader ready…");
        try {
            await prepareWorker();
        } catch (error) {
            setGuide(error.message || "Could not start the reader.");
            return;
        }
        if (state.phase !== "camera") return;
        setGuide("Hold the camera over the result sheet. Move slowly up, down and sideways.");
        scheduleRead(300);
    }

    /* ------------------------------------------------------------------ reading the whole sheet */
    function resetSession() {
        state.seen = new Map();
        state.confirmed = new Map();
        state.unclear = new Map();
    }

    function classTotal() {
        return (state.meta && state.meta.students ? state.meta.students : []).length;
    }

    async function readFrame() {
        if (state.phase !== "camera" || state.reading) return;
        const video = videoEl();
        if (!state.worker || !video || !video.videoWidth) { scheduleRead(300); return; }
        state.reading = true;
        try {
            const result = await state.worker.recognize(captureFrame(video));
            if (state.phase !== "camera") return;
            const lines = (result && result.data && result.data.lines) || [];
            judge(extractRows(lines, state.meta.students || [], state.meta.subject));
        } catch (error) {
            setGuide("The reader paused. Keep the sheet in view.");
        } finally {
            state.reading = false;
            if (state.phase === "camera") {
                if (!state.clearWarned && Date.now() - state.startedAt > CLEAR_WARNING_MS) warnNotClear();
                scheduleRead(READ_EVERY_MS);
            }
        }
    }

    /* The camera can cover a long class sheet in several movements, so students are collected across
       every frame. A student is kept once the same scores are read on STABLE_READS frames. */
    function judge(rows) {
        rows.forEach(row => {
            const id = String(row.student.id);
            if (state.confirmed.has(id)) return;
            if (!row.detected) { state.unclear.set(id, row); return; }
            const signature = `${row.ca1}|${row.ca2}|${row.exam}`;
            const entry = state.seen.get(id);
            const count = entry && entry.signature === signature ? entry.count + 1 : 1;
            state.seen.set(id, { signature, count });
            if (count >= STABLE_READS) {
                state.confirmed.set(id, row);
                state.unclear.delete(id);
            } else {
                state.unclear.set(id, row);
            }
        });

        const total = classTotal();
        const captured = state.confirmed.size;
        const checking = [...state.unclear.keys()].filter(id => !state.confirmed.has(id)).length;
        if (total && captured >= total) { succeed(); return; }
        if (!rows.length && !captured) {
            setGuide("Looking for the result sheet. Move closer and keep the whole sheet in view.");
            return;
        }
        let text = `Captured ${captured} of ${total} student(s). Keep moving the camera slowly up, down and sideways to reach the rest.`;
        if (checking) text += ` ${checking} line(s) still being checked.`;
        setGuide(text);
    }

    /* Every student of the class has been captured with clear scores. */
    function succeed() {
        const total = classTotal();
        showPhase("marking");
        setMark("ok", `All ${total} students captured`);
        setGuide("Checking the scores…");
        stopStream();
        clearTimeout(state.timer);
        setTimeout(() => { if (state.phase === "marking") showPreview(buildPreviewRows()); }, 1100);
    }

    function warnNotClear() {
        state.clearWarned = true;
        const total = classTotal();
        const captured = state.confirmed.size;
        setMark("warn", `${captured} of ${total} captured`, 3000);
        setGuide(`Still scanning: ${captured} of ${total} captured. Keep moving the camera, or tap Review scanned students to check what you have.`);
    }

    /* One row for every student of the class: captured scores, unclear scores, or not captured yet. */
    function buildPreviewRows() {
        return (state.meta.students || []).map(student => {
            const id = String(student.id);
            const confirmed = state.confirmed.get(id);
            const partial = confirmed || state.unclear.get(id);
            if (partial) {
                return { student, ca1: partial.ca1, ca2: partial.ca2, exam: partial.exam, detected: !!confirmed, missing: false };
            }
            return { student, ca1: "", ca2: "", exam: "", detected: false, missing: true };
        });
    }

    function captureNow() {
        if (state.phase !== "camera") return;
        if (!state.confirmed.size && !state.unclear.size) {
            setGuide("No students have been read yet. Keep the sheet in view and try again.");
            return;
        }
        clearTimeout(state.timer);
        stopStream();
        showPreview(buildPreviewRows());
    }

    /* ------------------------------------------------------------------ preview */
    function showPreview(rows) {
        showPhase("preview");
        setMark(null);
        const body = $("[data-mkls-rows]", overlay);
        body.replaceChildren(...rows.map(previewRow));
        const missing = rows.filter(row => row.missing).length;
        const checking = rows.filter(row => !row.detected && !row.missing).length;
        $("[data-mkls-note]", overlay).textContent = missing
            ? `${missing} student(s) not captured yet. Scan again to capture them, or type their scores.`
            : checking
                ? `${checking} student(s) need checking. Correct any score that is wrong before saving.`
                : "Check each score, then save to student results.";
        $("[data-mkls-save-msg]", overlay).textContent = "";
    }

    function previewRow(row) {
        const subject = state.meta.subject;
        const tr = document.createElement("tr");
        tr.dataset.studentId = String(row.student.id);
        if (!row.detected) tr.className = "needs-check";

        const name = [row.student.first_name, row.student.middle_name, row.student.last_name].filter(Boolean).join(" ");
        const nameCell = document.createElement("td");
        nameCell.textContent = name;
        const admissionCell = document.createElement("td");
        admissionCell.textContent = row.student.admission_number || "";
        tr.append(nameCell, admissionCell);

        FIELDS.forEach((field, index) => {
            const td = document.createElement("td");
            const input = document.createElement("input");
            input.type = "number";
            input.inputMode = "decimal";
            input.min = "0";
            input.max = String(subject[MAX_KEYS[index]]);
            input.step = "any";
            input.dataset.field = field;
            input.setAttribute("aria-label", `${LABELS[index]} for ${name}`);
            input.value = row[field] === "" || row[field] == null ? "" : String(row[field]);
            input.addEventListener("input", () => updateTotal(tr));
            td.appendChild(input);
            tr.appendChild(td);
        });

        const total = document.createElement("td");
        total.className = "mkls-total";
        tr.appendChild(total);
        updateTotal(tr);
        if (row.missing) {
            tr.className = "needs-check";
            total.textContent = "Not captured";
        }
        return tr;
    }

    function updateTotal(tr) {
        const values = FIELDS.map(field => tr.querySelector(`[data-field="${field}"]`).value.trim());
        const filled = values.filter(value => value !== "");
        tr.querySelector(".mkls-total").textContent = filled.length ? String(filled.reduce((sum, value) => sum + Number(value), 0)) : "—";
    }

    /* ------------------------------------------------------------------ save */
    async function saveRows() {
        if (state.phase !== "preview") return;
        const meta = state.meta;
        const subject = meta.subject;
        const message = $("[data-mkls-save-msg]", overlay);
        const saveButton = $("[data-mkls-save]", overlay);
        const payloads = [];

        for (const tr of overlay.querySelectorAll("[data-mkls-rows] tr")) {
            const values = FIELDS.map(field => tr.querySelector(`[data-field="${field}"]`).value.trim());
            if (values.every(value => value === "")) continue;
            const numbers = values.map(value => (value === "" ? null : Number(value)));
            for (let index = 0; index < numbers.length; index++) {
                const score = numbers[index];
                if (score !== null && (!Number.isFinite(score) || score < 0 || score > Number(subject[MAX_KEYS[index]]))) {
                    message.textContent = `${tr.children[0].textContent}: ${LABELS[index]} must be between 0 and ${subject[MAX_KEYS[index]]}.`;
                    tr.querySelector(`[data-field="${FIELDS[index]}"]`).focus();
                    return;
                }
            }
            const studentId = Number(tr.dataset.studentId);
            const existing = (meta.results || []).find(result =>
                Number(result.student_id) === studentId && Number(result.subject_id) === Number(subject.id));
            payloads.push({
                class_type: meta.classType,
                class_id: Number(meta.classId),
                student_id: studentId,
                subject_id: Number(subject.id),
                teacher_remark: existing ? (existing.teacher_remark ?? null) : null,
                result_remark: existing ? (existing.result_remark ?? null) : null,
                ca1: numbers[0],
                ca2: numbers[1],
                exam: numbers[2]
            });
        }

        if (!payloads.length) {
            message.textContent = "There are no scores to save.";
            return;
        }

        state.phase = "saving";
        saveButton.disabled = true;
        let saved = 0;
        try {
            for (const payload of payloads) {
                message.textContent = `Saving ${saved + 1} of ${payloads.length}…`;
                const response = await fetch("./api/results/save.php", {
                    method: "POST",
                    credentials: "same-origin",
                    cache: "no-store",
                    headers: { "Content-Type": "application/json", "Accept": "application/json" },
                    body: JSON.stringify(payload)
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) throw new Error(data.message || "A result could not be saved.");
                saved++;
            }
            message.textContent = `${saved} result(s) saved to student results.`;
            if (typeof meta.onSaved === "function") await meta.onSaved();
            setTimeout(closeScanner, 1400);
        } catch (error) {
            message.textContent = `${saved} saved before an error: ${error.message || "Save failed."} Check the scores and try again.`;
            if (typeof meta.onSaved === "function") await meta.onSaved();
            state.phase = "preview";
        } finally {
            saveButton.disabled = false;
        }
    }

    /* ------------------------------------------------------------------ open / close */
    function openScanner() {
        if (!overlay) buildOverlay();
        resetSession();
        $("#mklsTitle", overlay).textContent = state.meta.subject.name;
        overlay.hidden = false;
        document.body.classList.add("mkls-open");
        startCamera();
    }

    function closeScanner() {
        state.phase = "closed";
        clearTimeout(state.timer);
        clearTimeout(markTimer);
        stopStream();
        if (state.worker) { state.worker.terminate().catch(() => {}); state.worker = null; }
        if (overlay) overlay.hidden = true;
        document.body.classList.remove("mkls-open");
    }

    button?.addEventListener("click", () => {
        const meta = state.meta;
        if (!meta || !ROLES.includes(meta.role)) return;
        if (!meta.subject) { showHint("Choose the subject in the results table first, then press Scan results."); return; }
        if (!meta.students || !meta.students.length) { showHint("No students are loaded for this class yet."); return; }
        openScanner();
    });

    /* class.js gives the scanner the class, the roster, the chosen subject and a refresh callback */
    function configure(config) {
        const next = config || {};
        state.meta = next;
        if (button) button.hidden = !ROLES.includes(next.role);
        const key = [next.classId, next.classType, next.subject ? next.subject.id : ""].join("|");
        if (key !== state.scopeKey) {
            state.scopeKey = key;
            if (state.phase !== "closed") closeScanner();
        }
    }

    window.MarkazResultsScanner = { configure };
})();
