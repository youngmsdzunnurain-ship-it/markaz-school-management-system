(() => {
    "use strict";

    const root = document.getElementById("resultsScanner");
    if (!root || window.MarkazResultsScanner) return;

    const $ = selector => root.querySelector(selector);
    const state = { config: null, worker: null, rows: [], imageUrl: "", scopeKey: "", busy: false };

    function status(message, kind = "") {
        const node = $("[data-scanner-status]");
        node.textContent = message;
        node.dataset.kind = kind;
    }

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

    function renderReview() {
        const wrap = $("[data-scanner-review]");
        wrap.replaceChildren();
        if (!state.rows.length) {
            wrap.hidden = true;
            return;
        }
        const heading = document.createElement("p");
        heading.className = "results-scanner-review-note";
        heading.textContent = "Review every detected mark against the original sheet. Only checked rows will be saved.";
        wrap.appendChild(heading);
        const tableWrap = document.createElement("div");
        tableWrap.className = "results-scanner-table-wrap";
        const table = document.createElement("table");
        table.className = "results-scanner-table";
        table.innerHTML = "<thead><tr><th>Save</th><th>Student</th><th>CA1</th><th>CA2</th><th>Exam</th><th>OCR match</th></tr></thead>";
        const body = document.createElement("tbody");
        state.rows.forEach((item, index) => {
            const tr = document.createElement("tr");
            tr.dataset.studentId = String(item.student.id);
            const checkbox = document.createElement("input");
            checkbox.type = "checkbox";
            checkbox.checked = item.detected;
            checkbox.setAttribute("aria-label", "Include " + item.student.first_name + " in save");
            const selectCell = document.createElement("td");
            selectCell.appendChild(checkbox);
            tr.appendChild(selectCell);
            const nameCell = document.createElement("td");
            nameCell.textContent = [item.student.first_name, item.student.middle_name, item.student.last_name].filter(Boolean).join(" ");
            const admission = document.createElement("small");
            admission.textContent = item.student.admission_number || "";
            nameCell.appendChild(document.createElement("br"));
            nameCell.appendChild(admission);
            tr.appendChild(nameCell);
            ["ca1", "ca2", "exam"].forEach((field, fieldIndex) => {
                const td = document.createElement("td");
                const input = document.createElement("input");
                input.type = "number";
                input.min = "0";
                input.max = String([state.config.subject.max_ca1, state.config.subject.max_ca2, state.config.subject.max_exam][fieldIndex]);
                input.step = "0.01";
                input.value = item[field];
                input.dataset.field = field;
                input.setAttribute("aria-label", field.toUpperCase() + " for " + nameCell.firstChild.textContent);
                td.appendChild(input);
                tr.appendChild(td);
            });
            const matchCell = document.createElement("td");
            matchCell.textContent = item.detected ? "Name matched · verify marks" : "Name matched · enter marks";
            tr.appendChild(matchCell);
            body.appendChild(tr);
        });
        table.appendChild(body);
        tableWrap.appendChild(table);
        wrap.appendChild(tableWrap);
        const save = document.createElement("button");
        save.type = "button";
        save.className = "primary-button";
        save.textContent = "Save reviewed results";
        save.addEventListener("click", saveReviewedRows);
        wrap.appendChild(save);
        wrap.hidden = false;
    }

    async function loadOcr() {
        if (window.Tesseract) return window.Tesseract;
        if (window.markazResultsOcrLoading) return window.markazResultsOcrLoading;
        window.markazResultsOcrLoading = new Promise((resolve, reject) => {
            const script = document.createElement("script");
            script.src = "https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js";
            script.async = true;
            script.onload = () => window.Tesseract ? resolve(window.Tesseract) : reject(new Error("OCR engine did not initialise."));
            script.onerror = () => reject(new Error("Could not load the free OCR engine. Check your internet connection."));
            document.head.appendChild(script);
        });
        try { return await window.markazResultsOcrLoading; }
        catch (error) { window.markazResultsOcrLoading = null; throw error; }
    }

    async function prepareImage(file) {
        if (typeof createImageBitmap !== "function") return file;
        const bitmap = await createImageBitmap(file);
        try {
            const scale = Math.min(1, 2200 / Math.max(bitmap.width, bitmap.height));
            if (scale === 1) return file;
            const canvas = document.createElement("canvas");
            canvas.width = Math.max(1, Math.round(bitmap.width * scale));
            canvas.height = Math.max(1, Math.round(bitmap.height * scale));
            const context = canvas.getContext("2d", {alpha:false});
            context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            return await new Promise(resolve => canvas.toBlob(blob => resolve(blob || file), "image/jpeg", 0.88));
        } finally {
            bitmap.close();
        }
    }

    async function scan(file) {
        if (!state.config || !state.config.subject) {
            status("Select a subject in the Results section first.", "error");
            return;
        }
        if (!file || !["image/jpeg", "image/png", "image/webp"].includes(file.type) || file.size > 8 * 1024 * 1024) {
            status("Choose a JPEG, PNG or WebP image up to 8 MB.", "error");
            return;
        }
        const scanScope = state.scopeKey;
        const scanConfig = state.config;
        state.busy = true;
        status("Preparing the free on-device OCR engine…");
        const progress = $("[data-scanner-progress]");
        progress.hidden = false;
        try {
            if (state.imageUrl) URL.revokeObjectURL(state.imageUrl);
            state.imageUrl = URL.createObjectURL(file);
            const preview = $("[data-scanner-preview]");
            preview.src = state.imageUrl;
            preview.hidden = false;
            const Tesseract = await loadOcr();
            const ocrImage = await prepareImage(file);
            const ocrLanguage = scanConfig.classType === "arabic" ? "ara+eng" : "eng";
            state.worker = await Tesseract.createWorker(ocrLanguage, 1, {
                logger: message => {
                    if (message.status === "recognizing text") {
                        const percent = Math.round((message.progress || 0) * 100);
                        progress.value = percent;
                        status("Reading result sheet… " + percent + "%");
                    }
                }
            });
            const recognised = await state.worker.recognize(ocrImage);
            if (state.scopeKey !== scanScope) return;
            const lines = recognised?.data?.lines || [];
            state.rows = extractRows(lines, scanConfig.students, scanConfig.subject);
            if (!state.rows.length) {
                const raw = String(recognised?.data?.text || "").trim();
                status(raw
                    ? "Text was detected, but no student could be matched confidently to this class. Check the image and roster, or enter marks manually."
                    : "No readable text found. Try a sharper, well-lit photo.", "error");
                return;
            }
            renderReview();
            status(state.rows.length + " student name(s) matched. Check every score before saving.", "success");
        } catch (error) {
            status(error.message || "Could not scan this image.", "error");
        } finally {
            if (state.worker) {
                await state.worker.terminate().catch(() => {});
                state.worker = null;
            }
            state.busy = false;
        }
    }

    async function saveReviewedRows() {
        if (!state.config || state.busy) return;
        const selected = [...$("[data-scanner-review] tbody").querySelectorAll("tr")]
            .filter(row => row.querySelector('input[type="checkbox"]').checked);
        if (!selected.length) {
            status("Select at least one reviewed student row.", "error");
            return;
        }
        for (const row of selected) {
            const values = ["ca1", "ca2", "exam"].map(field => {
                const raw = row.querySelector('[data-field="' + field + '"]').value.trim();
                return raw === "" ? null : Number(raw);
            });
            const maxes = [state.config.subject.max_ca1, state.config.subject.max_ca2, state.config.subject.max_exam].map(Number);
            if (values.some(value => value === null)) {
                status("Enter CA1, CA2 and Exam for every selected student. Blank marks are never saved.", "error");
                return;
            }
            if (values.some((value, index) => !Number.isFinite(value) || value < 0 || value > maxes[index])) {
                status("A selected row contains a mark outside the subject's allowed range. Correct it before saving.", "error");
                return;
            }
        }
        if (!window.confirm("Save the reviewed results for " + selected.length + " selected student(s) in " + state.config.subject.name + "?")) return;
        state.busy = true;
        let saved = 0;
        try {
            for (const row of selected) {
                const studentId = Number(row.dataset.studentId);
                const existingResult = (state.config.results || []).find(result =>
                    Number(result.student_id) === studentId &&
                    Number(result.subject_id) === Number(state.config.subject.id)
                );
                const payload = {
                    class_type: state.config.classType,
                    class_id: Number(state.config.classId),
                    student_id: studentId,
                    subject_id: Number(state.config.subject.id),
                    teacher_remark: existingResult?.teacher_remark ?? null,
                    result_remark: existingResult?.result_remark ?? null,
                    ca1: row.querySelector('[data-field="ca1"]').value.trim() === "" ? null : Number(row.querySelector('[data-field="ca1"]').value),
                    ca2: row.querySelector('[data-field="ca2"]').value.trim() === "" ? null : Number(row.querySelector('[data-field="ca2"]').value),
                    exam: row.querySelector('[data-field="exam"]').value.trim() === "" ? null : Number(row.querySelector('[data-field="exam"]').value)
                };
                const response = await fetch("./api/results/save.php", {
                    method: "POST",
                    credentials: "same-origin",
                    cache: "no-store",
                    headers: {"Content-Type":"application/json", "Accept":"application/json"},
                    body: JSON.stringify(payload)
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) throw new Error(data.message || "A result could not be saved.");
                row.querySelector('input[type="checkbox"]').checked = false;
                saved++;
            }
            status(saved + " reviewed result(s) saved successfully. Refreshing the class results…", "success");
            if (typeof state.config.onSaved === "function") await state.config.onSaved();
            state.rows = [];
            $("[data-scanner-review]").replaceChildren();
            $("[data-scanner-review]").hidden = true;
        } catch (error) {
            status(saved + " row(s) saved before the following error: " + (error.message || "Save failed.") + " Review the class results before retrying.", "error");
            if (typeof state.config.onSaved === "function") await state.config.onSaved();
        } finally {
            state.busy = false;
        }
    }

    const button = document.getElementById("openResultsScanner");
    // The panel attribute is on the root section itself, so querySelector() inside the root cannot find it.
    const panel = root.matches("[data-scanner-panel]") ? root : $("[data-scanner-panel]");
    button?.addEventListener("click", () => {
        panel.hidden = !panel.hidden;
        if (!panel.hidden) panel.scrollIntoView({behavior:"smooth", block:"start"});
    });
    $("[data-scanner-upload]")?.addEventListener("change", event => scan(event.target.files?.[0]));
    $("[data-scanner-camera]")?.addEventListener("change", event => scan(event.target.files?.[0]));
    $("[data-scanner-close]")?.addEventListener("click", () => { panel.hidden = true; });
    $("[data-scanner-reset]")?.addEventListener("click", () => {
        state.rows = [];
        $("[data-scanner-review]").replaceChildren();
        $("[data-scanner-review]").hidden = true;
        $("[data-scanner-preview]").hidden = true;
        if (state.imageUrl) URL.revokeObjectURL(state.imageUrl);
        state.imageUrl = "";
        $("[data-scanner-upload]").value = "";
        $("[data-scanner-camera]").value = "";
        status("");
    });

    window.MarkazResultsScanner = {
        configure(config) {
            const nextConfig = config && config.subject ? config : null;
            const nextKey = nextConfig ? [nextConfig.classType, nextConfig.classId, nextConfig.subject.id].join("|") : "";
            if (state.scopeKey && nextKey !== state.scopeKey) {
                state.rows = [];
                $("[data-scanner-review]").replaceChildren();
                $("[data-scanner-review]").hidden = true;
                $("[data-scanner-preview]").hidden = true;
                if (state.imageUrl) URL.revokeObjectURL(state.imageUrl);
                state.imageUrl = "";
                panel.hidden = true;
                status("The class or subject changed. Start a new scan for the selected subject.");
            }
            state.scopeKey = nextKey;
            state.config = nextConfig;
            if (button) button.hidden = !state.config || !["admin", "exam_officer", "teacher"].includes(state.config.role);
            if (!state.config) {
                panel.hidden = true;
                status("Select an assigned subject to use the scanner.");
            }
        }
    };
})();
