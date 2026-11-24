/* DEMO DATA for School Fees & Finance (temporary).
   Shows sample figures, students, payments and invoices so the page can be seen as it will look.
   Nothing is saved: every change in demo mode only shows a message. To remove this file completely,
   follow the erase instructions given with the demo. */
(() => {
    "use strict";
    const KEY = "markaz-finance-demo";
    const demoOn = window.localStorage.getItem(KEY) === "on";

    const students = [
        ["Aisha", "Bello", "MKZ/26/101", "JSS 1"], ["Yusuf", "Sani", "MKZ/26/102", "JSS 1"],
        ["Maryam", "Musa", "MKZ/26/103", "JSS 2"], ["Ibrahim", "Lawal", "MKZ/26/104", "JSS 2"],
        ["Khadija", "Abubakar", "MKZ/26/105", "Basic 3"], ["Hauwa", "Yusuf", "MKZ/26/106", "Basic 3"],
        ["Zainab", "Garba", "MKZ/26/107", "Hifz A"], ["Umar", "Danjuma", "MKZ/26/108", "Hifz A"]
    ].map(([first, last, admission, className], index) => ({
        id: index + 1, first_name: first, middle_name: "", last_name: last,
        admission_number: admission, class_name: className, arabic_class_name: className === "Hifz A" ? "Hifz A" : "",
        class_type: className === "Hifz A" ? "arabic" : "conventional", is_active: 1
    }));
    const nameOf = s => `${s.first_name} ${s.last_name}`;
    const invoices = students.map((s, i) => {
        const amount = [95000, 95000, 110000, 110000, 120000, 120000, 130000, 130000][i];
        const paid = [95000, 40000, 110000, 0, 120000, 60000, 130000, 0][i];
        const due = i === 3 || i === 7 ? "2026-10-30" : "2026-11-30";
        return {
            id: i + 1, invoice_number: `INV-2611-${String(i + 1).padStart(3, "0")}`, student_name: nameOf(s),
            admission_number: s.admission_number, class_name: s.class_name, amount, amount_due: amount,
            base_amount: amount, discount_amount: 0, billed: amount, paid, balance: amount - paid,
            outstanding: amount - paid, due_date: due,
            status: paid === amount ? "paid" : paid > 0 ? "part_paid" : due < "2026-11-23" ? "overdue" : "unpaid"
        };
    });
    const payments = [
        ["RCT-2611-0031", students[0], 95000, "Bank transfer", "2026-11-18 10:12"],
        ["RCT-2611-0032", students[1], 40000, "Cash", "2026-11-18 11:40"],
        ["RCT-2611-0033", students[2], 110000, "Bank transfer", "2026-11-19 09:05"],
        ["RCT-2611-0034", students[4], 120000, "POS", "2026-11-20 14:22"],
        ["RCT-2611-0035", students[5], 60000, "Cash", "2026-11-21 08:50"],
        ["RCT-2611-0036", students[6], 130000, "Bank transfer", "2026-11-22 16:30"],
        ["RCT-2611-0037", students[3], 25000, "POS", "2026-11-23 09:14"]
    ].map(([receipt, s, amount, method, paidAt], index) => ({
        receipt_number: receipt, student_name: nameOf(s), admission_number: s.admission_number,
        amount, method, paid_at: paidAt, status: index === 5 ? "pending" : index === 6 ? "failed" : "verified"
    }));
    const plans = [
        { id: 1, scope: "class", class_name: "JSS 1", class_type: "conventional", description: "Term fee, JSS 1", amount: 95000, due_day: 30, effective_from: "2026-09-01", effective_until: null, is_active: 1 },
        { id: 2, scope: "class", class_name: "JSS 2", class_type: "conventional", description: "Term fee, JSS 2", amount: 110000, due_day: 30, effective_from: "2026-09-01", effective_until: null, is_active: 1 },
        { id: 3, scope: "class", class_name: "Hifz A", class_type: "arabic", description: "Memorisation fee, Hifz A", amount: 130000, due_day: 30, effective_from: "2026-09-01", effective_until: null, is_active: 1 }
    ];
    const income = [
        { received_date: "2026-11-17", category: "Uniform sales", description: "School uniforms", payer_name: "Parent counter", amount: 35000, status: "verified" },
        { received_date: "2026-11-19", category: "Admission form", description: "Admission forms", payer_name: "Walk-in", amount: 15000, status: "pending" }
    ];
    const expenses = [
        { expense_date: "2026-11-16", category: "Utilities", description: "Generator fuel", vendor_name: "Gwagwalada Fuel Depot", amount: 62000, status: "approved" },
        { expense_date: "2026-11-18", category: "Maintenance", description: "Classroom repairs", vendor_name: "Local contractor", amount: 30000, status: "rejected" }
    ];
    const staff = [
        { id: 1, full_name: "Ustadh Ibrahim Sani", staff_number: "STF-001", job_title: "Teacher", base_salary: 180000, expected_arrival: "07:30", lateness_rate_per_minute: 50, is_active: 1, email: "ibrahim@markaz.test", phone: "08000000001" },
        { id: 2, full_name: "Ustadha Maryam Bello", staff_number: "STF-002", job_title: "Teacher", base_salary: 165000, expected_arrival: "07:30", lateness_rate_per_minute: 50, is_active: 1, email: "maryam@markaz.test", phone: "08000000002" }
    ];
    const classes = [
        { id: 1, name: "JSS 1", class_name: "JSS 1", class_type: "conventional" },
        { id: 2, name: "JSS 2", class_name: "JSS 2", class_type: "conventional" },
        { id: 3, name: "Basic 3", class_name: "Basic 3", class_type: "conventional" },
        { id: 4, name: "Hifz A", class_name: "Hifz A", class_type: "arabic" }
    ];
    const months = ["2026-06", "2026-07", "2026-08", "2026-09", "2026-10", "2026-11"];
    const trend = months.map((m, i) => ({ period: m, month: m, fees: 610000 + i * 36000 + (i % 2) * 22000, other_income: 90000 + i * 8000, expenses: 520000 + (i % 3) * 64000 + i * 9000 }));

    const teacherDemo = [
        { id: 1, finance_staff_id: 1, full_name: "Ustadh Ibrahim Sani", staff_number: "STF-001", job_title: "Teacher", expected_arrival: "07:30", actual_arrival: "07:24", late_minutes: 0, status: "present", notes: "" },
        { id: 2, finance_staff_id: 2, full_name: "Ustadha Maryam Bello", staff_number: "STF-002", job_title: "Teacher", expected_arrival: "07:30", actual_arrival: "07:52", late_minutes: 22, status: "late", notes: "Traffic on the Gwagwalada road" },
        { id: 3, finance_staff_id: 3, full_name: "Mallam Yusuf Lawal", staff_number: "STF-003", job_title: "Administrator", expected_arrival: "08:00", actual_arrival: "", late_minutes: 0, status: "absent", notes: "On approved leave" },
        { id: 4, finance_staff_id: 4, full_name: "Hajiya Khadija Musa", staff_number: "STF-004", job_title: "Exam officer", expected_arrival: "07:45", actual_arrival: "07:41", late_minutes: 0, status: "present", notes: "" }
    ];
    const DEMO = {
        dashboard: {
            summary: { fees_today: 485000, other_income_today: 35000, expenses_today: 92000, net_today: 428000, net_movement: 428000, outstanding: 1642500, open_invoices: 74 },
            monthly_trend: trend,
            income_mix: { fees: 1820000, school_fees: 1820000, other: 210000, other_income: 210000 },
            recent_payments: payments
        },
        plans: { plans }, invoices: { invoices }, payments: { payments }, income: { income }, expenses: { expenses }, staff: { staff }, classes: { classes },
        students: { students },
        setup: { sessions: [{ id: 1, session_name: "2026/2027", is_current: 1 }], classes, current_admin_id: 1, pending_adjustments: [
            { id: 1, student_name: "Umar Danjuma", admission_number: "MKZ/26/108", class_name: "Hifz A", adjustment_type: "Negotiated final fee", amount: 15000, reason: "Family hardship, approved by management", requested_by: "Mallam Yusuf Lawal", status: "pending" },
            { id: 2, student_name: "Khadija Abubakar", admission_number: "MKZ/26/105", class_name: "Basic 3", adjustment_type: "Scholarship 50%", amount: 60000, reason: "Top of class, Term 1", requested_by: "Hajiya Khadija Musa", status: "pending" }
        ] },
        report: { student_count: students.length, billed: 870000, paid: 420000, outstanding: 450000, invoices, classes: [
            { class_name: "JSS 1", students_billed: 2, total_billed: 190000, paid: 135000, outstanding: 55000 },
            { class_name: "JSS 2", students_billed: 2, total_billed: 220000, paid: 110000, outstanding: 110000 },
            { class_name: "Basic 3", students_billed: 2, total_billed: 240000, paid: 180000, outstanding: 60000 },
            { class_name: "Hifz A", students_billed: 2, total_billed: 260000, paid: 130000, outstanding: 130000 }
        ] },
        reports: {
            category: "All categories", expense_categories: [{ category: "Utilities", total: 62000 }, { category: "Maintenance", total: 30000 }],
            fees_collected: 1820000, other_income: 210000, expenses_paid: 920000, net_movement: 1110000,
            pending_payment_amount: 150000, pending_payment_count: 2, transaction_count: 58
        },
        payroll: { period: null, rows: [] },
        teachers: { success: true, staff: teacherDemo, teachers: teacherDemo, data: teacherDemo, rows: teacherDemo }
    };

    if (demoOn) {
        const original = window.fetch.bind(window);
        window.fetch = (input, init) => {
            const url = typeof input === "string" ? input : (input && input.url) || String(input);
            if (!/\/api\/(finance|attendance\/teachers)/.test(url)) return original(input, init);
            const method = ((init && init.method) || "GET").toUpperCase();
            const page = new URL(url, location.href);
            const key = page.pathname.includes("payroll") ? "payroll" : page.pathname.includes("teachers") ? "teachers" : (page.searchParams.get("action") || "");
            const body = method !== "GET"
                ? { success: true, message: "Demo mode: nothing was saved." }
                : { success: true, ...(DEMO[key] || {}) };
            return Promise.resolve(new Response(JSON.stringify(body), { status: 200, headers: { "Content-Type": "application/json" } }));
        };
    }

    const banner = document.createElement("div");
    banner.className = "finance-demo-banner";
    banner.setAttribute("role", "status");
    banner.innerHTML = demoOn
        ? '<span>Demo data is showing. Nothing is saved.</span><button type="button">Turn demo off</button>'
        : '<span>Live data is showing.</span><button type="button">Show demo data</button>';
    banner.querySelector("button").addEventListener("click", () => {
        window.localStorage.setItem(KEY, demoOn ? "off" : "on");
        window.location.reload();
    });
    const style = document.createElement("style");
    style.textContent = `
        .finance-demo-banner{position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:90;display:flex;align-items:center;gap:12px;width:max-content;
            padding:8px 8px 8px 16px;border-radius:999px;background:#0f3324;color:#fff;font:600 13px/1.2 Manrope,system-ui,sans-serif;box-shadow:0 14px 30px rgba(6,32,21,.35);max-width:calc(100% - 24px)}
        .finance-demo-banner span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
        .finance-demo-banner button{min-height:36px;padding:6px 14px;border-radius:999px;border:1px solid #e6c26a;background:#fbf3d6;color:#5a4308;font:700 13px/1 Manrope,system-ui,sans-serif;cursor:pointer;white-space:nowrap}
        @media print{.finance-demo-banner{display:none}}`;
    document.head.appendChild(style);
    document.addEventListener("DOMContentLoaded", () => document.body.appendChild(banner));
    if (document.readyState !== "loading") document.body.appendChild(banner);
})();
