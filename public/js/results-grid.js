/* MARKAZ grid reader: reads a ruled broadsheet cell by cell.
   1. Ruling lines are found from the dark pixels along each row and column (projection profiles).
   2. The lines give every cell of the table.
   3. Each cell is read on its own, so small digits and long subject names are not lost in the table.
   Layout (the school broadsheet): S/N, name, then 12 subjects x (CA1, CA2, EX); three header rows
   (subject names, column titles, maximum scores), then one row per student. */
(() => {
    "use strict";
    const SUBJECT_COLUMNS = 3;
    const HEADER_LINES = 4;      // top edge + the bottom of each of the three header rows
    const LEADING_COLUMNS = 3;   // lines counted before the subject columns: left edge, after S/N, after name
    const FIRST_SCORE_LINE = 2;  // index of the first subject column's left line (the line after the name)

    function grayOf(canvas) {
        const ctx = canvas.getContext("2d");
        const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const d = img.data;
        const n = canvas.width * canvas.height;
        const g = new Uint8Array(n);
        for (let p = 0, i = 0; p < n; p++, i += 4) g[p] = (0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2]) | 0;
        return { w: canvas.width, h: canvas.height, g };
    }

    /* ink = darker than its surroundings (local mean). Works in uneven light. */
    function inkOf({ w, h, g }, win = 31, c = 14) {
        const ii = new Float64Array((w + 1) * (h + 1));
        for (let y = 1; y <= h; y++) {
            let row = 0;
            for (let x = 1; x <= w; x++) {
                row += g[(y - 1) * w + (x - 1)];
                ii[y * (w + 1) + x] = ii[(y - 1) * (w + 1) + x] + row;
            }
        }
        const r = win >> 1;
        const ink = new Uint8Array(w * h);
        for (let y = 0; y < h; y++) {
            const y0 = Math.max(0, y - r), y1 = Math.min(h, y + r + 1);
            for (let x = 0; x < w; x++) {
                const x0 = Math.max(0, x - r), x1 = Math.min(w, x + r + 1);
                const area = (x1 - x0) * (y1 - y0);
                const sum = ii[y1 * (w + 1) + x1] - ii[y0 * (w + 1) + x1] - ii[y1 * (w + 1) + x0] + ii[y0 * (w + 1) + x0];
                ink[y * w + x] = g[y * w + x] < sum / area - c ? 1 : 0;
            }
        }
        return ink;
    }

    /* neighbouring positions that belong to one line become one line (its centre) */
    function clusterLines(positions, gap = 3) {
        const out = [];
        let group = [];
        positions.forEach(p => {
            if (group.length && p - group[group.length - 1] > gap) { out.push(group.reduce((a, b) => a + b, 0) / group.length); group = []; }
            group.push(p);
        });
        if (group.length) out.push(group.reduce((a, b) => a + b, 0) / group.length);
        return out.map(v => Math.round(v));
    }

    function detectGrid(canvas) {
        const gray = grayOf(canvas);
        const { w, h } = gray;
        const ink = inkOf(gray);
        const rowCounts = new Int32Array(h);
        for (let y = 0; y < h; y++) { let s = 0; for (let x = 0; x < w; x++) s += ink[y * w + x]; rowCounts[y] = s; }
        const maxRow = Math.max(...rowCounts);
        const hy = [];
        for (let y = 0; y < h; y++) if (rowCounts[y] >= maxRow * 0.5) hy.push(y);
        const ys = clusterLines(hy);
        if (ys.length < HEADER_LINES + 1) return { ok: false, reason: "The ruled lines of the table are not clear. Hold the whole sheet in view, flat and in good light." };
        const top = ys[0], bottom = ys[ys.length - 1];
        const colCounts = new Int32Array(w);
        const bandTop = ys[HEADER_LINES - 1] + 2, bandBottom = bottom - 2;
        for (let x = 0; x < w; x++) { let s = 0; for (let y = bandTop; y < bandBottom; y++) s += ink[y * w + x]; colCounts[x] = s; }
        const maxCol = Math.max(...colCounts);
        const vx = [];
        for (let x = 0; x < w; x++) if (colCounts[x] >= (bandBottom - bandTop) * 0.7) vx.push(x);
        const xs = clusterLines(vx);
        const subjects = (xs.length - LEADING_COLUMNS) / SUBJECT_COLUMNS;
        if (!Number.isInteger(subjects) || subjects < 1) {
            return { ok: false, reason: `Found ${xs.length} vertical lines. The table needs S/N, name and three columns for each subject. Hold the whole sheet in view.`, xs, ys };
        }
        const students = ys.length - HEADER_LINES;
        if (students < 1) return { ok: false, reason: "No student rows were found under the header. Hold the whole sheet in view." };
        return { ok: true, xs, ys, subjects, students, top, bottom, maxCol };
    }

    /* a cell crop, enlarged and framed in white, so the reader can see it clearly */
    function cropOf(canvas, x0, y0, x1, y1, enlarge = 4) {
        const inset = 3, scale = enlarge, pad = enlarge * 3 + 2;
        const sx = Math.max(0, Math.round(x0 + inset)), sy = Math.max(0, Math.round(y0 + inset));
        const sw = Math.max(2, Math.round(x1 - x0 - 2 * inset)), sh = Math.max(2, Math.round(y1 - y0 - 2 * inset));
        const c = document.createElement("canvas");
        c.width = sw * scale + pad * 2;
        c.height = sh * scale + pad * 2;
        const ctx = c.getContext("2d");
        ctx.fillStyle = "#fff";
        ctx.fillRect(0, 0, c.width, c.height);
        ctx.imageSmoothingEnabled = true;
        ctx.drawImage(canvas, sx, sy, sw, sh, pad, pad, sw * scale, sh * scale);
        return c;
    }

    /* reads the grid in two passes, so the reader changes mode only twice:
       pass 1 (text): the subject headings and the student names; pass 2 (digits): the score cells, row by row.
       Inside a row the ruling lines between the cells are painted out, so each number stands alone. */
    async function readCells(canvas, grid, recognise, onProgress) {
        const { xs, ys, subjects, students } = grid;
        const headerNames = [];
        for (let s = 0; s < subjects; s++) {
            const x0 = xs[FIRST_SCORE_LINE + s * SUBJECT_COLUMNS], x1 = xs[FIRST_SCORE_LINE + (s + 1) * SUBJECT_COLUMNS];
            headerNames.push(await recognise(cropOf(canvas, x0, ys[0], x1, ys[1]), "block"));
        }
        const names = [];
        for (let r = 0; r < students; r++) {
            names.push(await recognise(cropOf(canvas, xs[1], ys[HEADER_LINES - 1 + r], xs[2], ys[HEADER_LINES + r]), "text"));
            if (onProgress) onProgress(r + 1, students * 2);
        }
        /* each row: every score cell is placed on its own strip with a wide gap, so each number reads as one word */
        const rows = [];
        const rowTrios = [];
        const scale = 3, gap = 70, pad = 14;
        const columns = subjects * SUBJECT_COLUMNS;
        for (let r = 0; r < students; r++) {
            const y0 = ys[HEADER_LINES - 1 + r], y1 = ys[HEADER_LINES + r];
            const cellsW = [];
            for (let j = 0; j < columns; j++) cellsW.push(xs[FIRST_SCORE_LINE + j + 1] - xs[FIRST_SCORE_LINE + j]);
            const slot = Math.max(...cellsW) * scale + gap;
            const strip = document.createElement("canvas");
            strip.width = columns * slot + pad * 2;
            strip.height = Math.round((y1 - y0 - 6) * scale) + pad * 2;
            const ctx = strip.getContext("2d");
            ctx.fillStyle = "#fff";
            ctx.fillRect(0, 0, strip.width, strip.height);
            for (let j = 0; j < columns; j++) {
                const cx0 = xs[FIRST_SCORE_LINE + j] + 3, cx1 = xs[FIRST_SCORE_LINE + j + 1] - 3;
                const sy = Math.round(y0 + 3), sh = Math.round(y1 - y0 - 6);
                ctx.drawImage(canvas, Math.round(cx0), sy, Math.round(cx1 - cx0), sh, pad + j * slot, pad, Math.round((cx1 - cx0) * scale), sh * scale);
            }
            const words = await recognise(strip, "digits-words");
            const trios = [];
            for (let s = 0; s < subjects; s++) trios.push([null, null, null]);
            words.forEach(word => {
                const j = Math.floor((word.cx - pad) / slot);
                if (j < 0 || j >= columns) return;
                const s = Math.floor(j / SUBJECT_COLUMNS), k = j % SUBJECT_COLUMNS;
                trios[s][k] = trios[s][k] === null ? word.text : trios[s][k] + word.text;
            });
            rowTrios.push(trios);
            if (onProgress) onProgress(students + r + 1, students * 2);
        }
        /* second, independent reading of every cell on its own. A score counts only when both readings agree;
           where they differ the cell is left unclear, and the next pass reads it again. */
        for (let r = 0; r < students; r++) {
            const y0 = ys[HEADER_LINES - 1 + r], y1 = ys[HEADER_LINES + r];
            for (let s = 0; s < subjects; s++) for (let k = 0; k < SUBJECT_COLUMNS; k++) {
                const j = s * SUBJECT_COLUMNS + k;
                let single = (await recognise(cropOf(canvas, xs[FIRST_SCORE_LINE + j], y0, xs[FIRST_SCORE_LINE + j + 1], y1), "digits-cell")).replace(/\D/g, "");
                if (single === "") single = (await recognise(cropOf(canvas, xs[FIRST_SCORE_LINE + j], y0, xs[FIRST_SCORE_LINE + j + 1], y1, 7), "digits-cell")).replace(/\D/g, "");
                if (single === "") single = (await recognise(cropOf(canvas, xs[FIRST_SCORE_LINE + j], y0, xs[FIRST_SCORE_LINE + j + 1], y1, 5), "any-cell")).replace(/\D/g, "");
                const rowValue = rowTrios[r][s][k];
                rowTrios[r][s][k] = rowValue !== null && rowValue !== single ? "" : single;
            }
            if (onProgress) onProgress(students * 2, students * 2);
        }
        rowTrios.forEach((trios, r) => rows.push({ name: names[r], cells: trios.map(trio => trio.map(value => (value === null ? "" : value))) }));
        return { headerNames, rows };
    }

    window.MarkazResultsGrid = { detectGrid, readCells, cropOf };
})();
