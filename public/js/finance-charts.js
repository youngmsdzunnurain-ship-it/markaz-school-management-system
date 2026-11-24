/* MARKAZ School Fees: charts and figures.
   Cash flow: a candlestick chart per month (income against expenses, green when income covers expenses,
   red when it does not) with a net line, hover values, and the month labels.
   Income mix: horizontal bars with shares, no pie. Figures count up when they change. */
(() => {
    "use strict";
    const NGN = new Intl.NumberFormat("en-NG", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = value => "₦" + NGN.format(Number(value || 0));
    const monthLabel = value => {
        const [year, month] = String(value || "").split("-");
        const names = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        const index = Number(month) - 1;
        return names[index] ? `${names[index]} ${year.slice(2)}` : String(value || "");
    };
    const esc = text => String(text).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

    function candles(host, rows) {
        const data = rows.map(r => {
            const income = Number(r.fees || 0) + Number(r.other_income || 0);
            const expenses = Number(r.expenses || 0);
            return { label: monthLabel(r.period), income, expenses, net: income - expenses };
        });
        const width = 720, height = 280, padL = 58, padR = 18, padT = 22, padB = 44;
        const innerW = width - padL - padR, innerH = height - padT - padB;
        const max = Math.max(1, ...data.flatMap(d => [d.income, d.expenses]));
        const y = v => padT + innerH - (v / max) * innerH;
        const step = innerW / data.length;
        const bodyW = Math.min(26, step * 0.42);
        let grid = "";
        for (let i = 0; i <= 4; i++) {
            const v = (max / 4) * i, yy = y(v).toFixed(1);
            grid += `<line x1="${padL}" x2="${width - padR}" y1="${yy}" y2="${yy}" class="fc-grid"/>`;
            grid += `<text x="${padL - 10}" y="${(Number(yy) + 4).toFixed(1)}" class="fc-axis" text-anchor="end">${esc(v >= 1000 ? "₦" + Math.round(v / 1000) + "k" : "₦" + Math.round(v))}</text>`;
        }
        let bodies = "", wicks = "", labels = "", netPoints = [];
        data.forEach((d, i) => {
            const cx = padL + step * i + step / 2;
            const up = d.income >= d.expenses;
            const top = y(Math.max(d.income, d.expenses)), bottom = y(Math.min(d.income, d.expenses));
            const high = y(Math.max(d.income, d.expenses)), low = y(Math.min(d.income, d.expenses));
            wicks += `<line x1="${cx.toFixed(1)}" x2="${cx.toFixed(1)}" y1="${high.toFixed(1)}" y2="${low.toFixed(1)}" class="fc-wick ${up ? "up" : "down"}"/>`;
            bodies += `<rect x="${(cx - bodyW / 2).toFixed(1)}" y="${top.toFixed(1)}" width="${bodyW.toFixed(1)}" height="${Math.max(3, bottom - top).toFixed(1)}" rx="4" class="fc-body ${up ? "up" : "down"}"><title>${esc(d.label)}\nIncome ${money(d.income)}\nExpenses ${money(d.expenses)}\nNet ${money(d.net)}</title></rect>`;
            labels += `<text x="${cx.toFixed(1)}" y="${height - 14}" class="fc-label" text-anchor="middle">${esc(d.label)}</text>`;
            netPoints.push(`${cx.toFixed(1)},${y(Math.max(0, d.net + max * 0.0)).toFixed(1)}`);
        });
        const netLine = data.length > 1 ? `<polyline points="${netPoints.join(" ")}" class="fc-net"/>` : "";
        const last = data[data.length - 1];
        host.innerHTML = `
            <div class="fc-head">
                <div><span class="fc-kicker">Net this month</span><strong class="fc-net-value ${last.net >= 0 ? "up" : "down"}">${money(last.net)}</strong></div>
                <div class="fc-legend"><span class="up"><i></i>Income covers expenses</span><span class="down"><i></i>Expenses higher</span></div>
            </div>
            <svg class="fc-svg" viewBox="0 0 ${width} ${height}" role="img" aria-label="Income against expenses by month">
                <defs><linearGradient id="fcFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#0f5a3a" stop-opacity=".18"/><stop offset="1" stop-color="#0f5a3a" stop-opacity="0"/></linearGradient></defs>
                ${grid}${wicks}${bodies}${netLine}${labels}
            </svg>`;
    }

    function mixBars(host, mix) {
        const fees = Number(mix?.fees || mix?.school_fees || 0);
        const other = Number(mix?.other_income || mix?.other || 0);
        const total = fees + other;
        if (!total) {
            host.innerHTML = '<p class="chart-empty">No confirmed income has been recorded in this period yet.</p>';
            return;
        }
        const row = (label, value, tone) => {
            const share = Math.round((value / total) * 100);
            return `<div class="fc-bar-row"><div class="fc-bar-top"><span>${esc(label)}</span><strong>${money(value)}</strong></div>
                <div class="fc-track"><span class="fc-fill ${tone}" style="width:${share}%"></span></div>
                <small>${share}% of confirmed income</small></div>`;
        };
        host.innerHTML = `<div class="fc-mix">${row("School fees", fees, "fees")}${row("Other income", other, "other")}</div>`;
    }

    function countUp(element) {
        const text = element.textContent.trim();
        const money_ = text.startsWith("₦");
        const number = Number(text.replace(/[^0-9.-]/g, ""));
        if (!Number.isFinite(number) || element.dataset.counting === "1") return;
        const from = Number(element.dataset.last || 0);
        element.dataset.last = String(number);
        if (from === number || Math.abs(number) > 1e12) return;
        element.dataset.counting = "1";
        const start = performance.now(), duration = 900;
        const format = value => money_ ? money(value) : Math.round(value).toLocaleString("en-NG");
        const reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        const tick = now => {
            const t = reduce ? 1 : Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            element.textContent = format(from + (number - from) * eased);
            if (t < 1) requestAnimationFrame(tick); else { element.textContent = format(number); element.dataset.counting = ""; }
        };
        requestAnimationFrame(tick);
    }

    /* the figures are rewritten when data arrives; count them up whenever they change */
    const watch = () => {
        document.querySelectorAll(".metric strong, .fin-figure, [data-count]").forEach(el => {
            if (el.dataset.watched) return;
            el.dataset.watched = "1";
            new MutationObserver(() => { if (!el.dataset.counting) countUp(el); })
                .observe(el, { childList: true, characterData: true, subtree: true });
        });
    };

    window.finCashflow = rows => {
        const host = document.getElementById("cashflowChart");
        if (!host) return;
        const data = Array.isArray(rows) ? rows : [];
        const total = data.reduce((sum, r) => sum + Number(r.fees || 0) + Number(r.other_income || 0) + Number(r.expenses || 0), 0);
        if (!data.length || total === 0) {
            host.innerHTML = '<p class="chart-empty">There is not enough confirmed financial history to draw this chart yet. Charts will fill as transactions are recorded.</p>';
            return;
        }
        candles(host, data);
    };
    window.finMix = mix => {
        const host = document.getElementById("incomeMixChart");
        if (host) mixBars(host, mix);
    };

    const ready = () => { watch(); setTimeout(watch, 600); setTimeout(watch, 2500); };
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", ready); else ready();
})();
