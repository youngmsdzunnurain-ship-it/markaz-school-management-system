const adminName = document.getElementById("adminName");
const welcomeAdminName = document.getElementById("welcomeAdminName");
const adminDetailsName = document.getElementById("adminDetailsName");
const adminEmail = document.getElementById("adminEmail");
const profileName = document.getElementById("profileName");
const profileEmail = document.getElementById("profileEmail");

const adminInitials = document.getElementById("adminInitials");
const dashboardAdminPhoto = document.getElementById("dashboardAdminPhoto");
const dashboardAdminName = document.getElementById("dashboardAdminName");
const dashboardAdminEmail = document.getElementById("dashboardAdminEmail");
const profileInitials = document.getElementById("profileInitials");

const menuButton = document.getElementById("menuButton");
const sidebar = document.getElementById("sidebar");
const sidebarOverlay = document.getElementById("sidebarOverlay");
const sidebarCloseButton = document.getElementById("sidebarCloseButton");

const adminProfileButton = document.getElementById("adminProfileButton");
const profileDropdown = document.getElementById("profileDropdown");

const logoutButton = document.getElementById("logoutButton");
const profileLogoutButton = document.getElementById("profileLogoutButton");

const dashboardSearch = document.getElementById("dashboardSearch");

function getInitials(name) {
    if (!name) return "AD";

    const parts = name.trim().split(/\s+/);

    if (parts.length === 1) {
        return parts[0].substring(0, 2).toUpperCase();
    }

    return (
        parts[0].charAt(0) +
        parts[parts.length - 1].charAt(0)
    ).toUpperCase();
}

async function loadAdmin() {
    try {
        const response = await fetch("./api/auth/me.php", {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store",
            headers: {
                "Accept": "application/json"
            }
        });

        const text = await response.text();

        let data;

        try {
            data = JSON.parse(text);
        } catch (error) {
            console.error("Invalid response from me.php:", text);
            return;
        }

        if (!response.ok || !data.success || !data.admin) {
            window.location.replace("login.html");
            return;
        }

        const name = data.admin.name || "Administrator";
        const email = data.admin.email || "";
        const role = data.admin.role || "";
        const hour = new Date().getHours();
        const greeting = hour < 12 ? "Good morning" : (hour < 17 ? "Good afternoon" : "Good evening");
        const greetingNode = document.getElementById("welcomeGreeting");
        if (greetingNode) greetingNode.textContent = greeting;
        const roleLabel = document.getElementById("welcomeRoleLabel");
        if (roleLabel) roleLabel.textContent = ({admin:"School administration",exam_officer:"Examination office",teacher:"Teacher workspace",management:"School management",student:"Student portal"})[role] || "MARKAZ School portal";
        window.markazCurrentRole = role;
        document.body.classList.toggle('role-exam-officer', role === 'exam_officer');
        loadDashboardAttendance();
        const profileImage = data.admin.profile_image || "";
        const initials = getInitials(name);

        console.log("MARKAZ ADMIN DATA:", {
            name,
            email,
            profileImage,
            initials,
            topUpdaterReady: typeof window.markazUpdateTopAdmin === "function"
        });

        applyDashboardRoleAccess(role, data.admin);

        if (adminName) adminName.textContent = name;
        if (welcomeAdminName) welcomeAdminName.textContent = name;
        if (adminDetailsName) adminDetailsName.textContent = name;
        const profileRoleTitle = document.getElementById("dashboardProfileRoleTitle");
        if (profileRoleTitle) profileRoleTitle.textContent = role === "exam_officer" ? "Exam Officer" : "Administrator";
        if (adminEmail) adminEmail.textContent = email;
        if (profileName) profileName.textContent = name;
        const sidebarRole = document.getElementById("sidebarProfileRole");
        if (sidebarRole) sidebarRole.textContent = role === "exam_officer" ? "Exam Officer" : "Administrator";
        if (profileEmail) profileEmail.textContent = email;
        if (dashboardAdminName) dashboardAdminName.textContent = name;
        if (dashboardAdminEmail) dashboardAdminEmail.textContent = email;
        if (dashboardAdminPhoto) {
            dashboardAdminPhoto.innerHTML = "";

            if (profileImage) {
                const image = document.createElement("img");
                image.src = "/" + profileImage.replace(/^\/+/, "");
                image.alt = name;
                image.loading = "eager";
                image.decoding = "async";
                dashboardAdminPhoto.appendChild(image);
            } else {
                const initialsSpan = document.createElement("span");
                initialsSpan.textContent = initials;
                dashboardAdminPhoto.appendChild(initialsSpan);
            }

            dashboardAdminPhoto.classList.add("avatar-ready");
        }

        if (adminInitials) { if (profileImage) { adminInitials.textContent = ""; adminInitials.style.backgroundImage = "url(\"" + profileImage + "\")"; adminInitials.style.backgroundSize = "cover"; adminInitials.style.backgroundPosition = "center"; adminInitials.style.backgroundRepeat = "no-repeat"; } else { adminInitials.style.backgroundImage = "none"; adminInitials.textContent = initials; } }
        if (window.markazUpdateTopAdmin) window.markazUpdateTopAdmin(name, email, profileImage);
        if (profileInitials) profileInitials.textContent = initials;

    } catch (error) {
        console.error("Unable to load administrator:", error);
    }
}

function openSidebar() {
    if (!sidebar) return;

    if (window.innerWidth > 850) {
        sidebar.classList.remove("desktop-closed");
        document.body.classList.remove("sidebar-desktop-closed");
    } else {
        sidebar.classList.add("open");
        sidebarOverlay?.classList.add("visible");
    }

    menuButton?.setAttribute("aria-expanded", "true");
}

function closeSidebar() {
    if (!sidebar) return;

    if (window.innerWidth > 850) {
        sidebar.classList.add("desktop-closed");
        document.body.classList.add("sidebar-desktop-closed");
    } else {
        sidebar.classList.remove("open");
        sidebarOverlay?.classList.remove("visible");
    }

    menuButton?.setAttribute("aria-expanded", "false");
}

function toggleSidebar() {
    if (!sidebar) return;

    const isOpen = window.innerWidth > 850
        ? !sidebar.classList.contains("desktop-closed")
        : sidebar.classList.contains("open");

    if (isOpen) closeSidebar();
    else openSidebar();
}

function openProfileDropdown() {
    if (!profileDropdown) return;

    profileDropdown.classList.add("open");

    if (adminProfileButton) {
        adminProfileButton.setAttribute("aria-expanded", "true");
    }
}

function closeProfileDropdown() {
    if (!profileDropdown) return;

    profileDropdown.classList.remove("open");

    if (adminProfileButton) {
        adminProfileButton.setAttribute("aria-expanded", "false");
    }
}

function toggleProfileDropdown() {
    if (!profileDropdown) return;

    if (profileDropdown.classList.contains("open")) {
        closeProfileDropdown();
    } else {
        openProfileDropdown();
    }
}

async function logout() {
    if (logoutButton) {
        logoutButton.disabled = true;
        logoutButton.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i><span>Logging out...</span>';
    }

    if (profileLogoutButton) {
        profileLogoutButton.disabled = true;
    }

    document.querySelectorAll(".profile-dropdown-item.logout").forEach(button => {
        button.disabled = true;
    });

    try {
        const response = await fetch("./api/auth/logout.php", {
            method: "POST",
            credentials: "same-origin",
            cache: "no-store",
            headers: {
                "Accept": "application/json"
            }
        });

        if (!response.ok) {
            throw new Error("Logout request failed with HTTP " + response.status);
        }

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || "The server did not confirm logout.");
        }
    } catch (error) {
        console.error("Logout request failed:", error);
        alert("We couldn't confirm logout with the server. Please try again.");
        if (logoutButton) {
            logoutButton.disabled = false;
            logoutButton.innerHTML =
                '<i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>';
        }
        if (profileLogoutButton) {
            profileLogoutButton.disabled = false;
        }
        document.querySelectorAll(".profile-dropdown-item.logout").forEach(button => {
            button.disabled = false;
        });
        return;
    }

    window.location.replace("./login.html");
}

if (menuButton) {
    menuButton.addEventListener("click", toggleSidebar);
}

if (sidebarCloseButton) {
    sidebarCloseButton.addEventListener("click", closeSidebar);
}

if (sidebarOverlay) {
    sidebarOverlay.addEventListener("click", closeSidebar);
}

if (adminProfileButton) {
    adminProfileButton.addEventListener(
        "click",
        toggleProfileDropdown
    );
}

document.addEventListener("click", (event) => {
    if (
        profileDropdown &&
        adminProfileButton &&
        !profileDropdown.contains(event.target) &&
        !adminProfileButton.contains(event.target)
    ) {
        closeProfileDropdown();
    }
});

document.querySelectorAll(".nav-link").forEach((link) => {
    link.addEventListener("click", () => {
        // Let the destination page determine its active navigation item.
        // Do not persist a temporary active state in this page's history entry.
        if (window.innerWidth <= 850) closeSidebar();
    });
});

document.querySelectorAll(".dashboard-attendance-link").forEach((link) => {
    link.addEventListener("click", () => link.blur());
});

if (logoutButton) {
    logoutButton.addEventListener("click", logout);
}

if (profileLogoutButton) {
    profileLogoutButton.addEventListener("click", logout);
}

if (dashboardSearch) {
    dashboardSearch.addEventListener("input", () => {
        const searchTerm = dashboardSearch.value.trim().toLowerCase();

        if (searchTerm !== "") {
            console.log("Dashboard search:", searchTerm);
        }
    });
}

window.addEventListener("resize", () => {
    if (window.innerWidth > 850 && sidebarOverlay) {
        sidebarOverlay.classList.remove("visible");
    }
});

/* =========================================================
   MARKAZ — BATCH 24
   TOPBAR CONTROLS
   ========================================================= */

const topbar = document.querySelector(".topbar");

if (topbar && !document.querySelector(".topbar-actions")) {

    const actions = document.createElement("div");
    actions.className = "topbar-actions";

    const profileWrap = document.createElement("div");
    profileWrap.className = "admin-profile-wrap";

    const profileButton = document.createElement("button");
    profileButton.type = "button";
    profileButton.className = "admin-profile-button";
    profileButton.setAttribute("aria-expanded", "false");

    profileButton.innerHTML = `
        <span
            class="admin-profile-avatar"
            id="topAdminInitials"
            style="background:#dff3e7;color:#145c38;-webkit-text-fill-color:#145c38;font-weight:900;"
        ></span>
        <span class="admin-profile-text">
            <strong id="topAdminName">Administrator</strong>
            <span id="topAdminRole">Administrator</span>
        </span>
        <span class="admin-profile-arrow">▾</span>
    `;

    const dropdown = document.createElement("div");
    dropdown.className = "profile-dropdown";

    dropdown.innerHTML = `
        <div class="profile-dropdown-header">
            <strong id="topDropdownName">Administrator</strong>
            <span id="topDropdownEmail">Loading...</span>
        </div>

        <button type="button" class="profile-dropdown-item">
            Profile
        </button>

        <button type="button" class="profile-dropdown-item">
            Settings
        </button>

        <div class="profile-dropdown-divider"></div>

        <button type="button" class="profile-dropdown-item logout" id="topProfileLogoutButton">
            Logout
        </button>
    `;

    profileWrap.appendChild(profileButton);
    profileWrap.appendChild(dropdown);

    actions.appendChild(profileWrap);
    topbar.appendChild(actions);

    const topAdminName = document.getElementById("topAdminName");
    const topDropdownName = document.getElementById("topDropdownName");
    const topDropdownEmail = document.getElementById("topDropdownEmail");
    const topAdminInitials = document.getElementById("topAdminInitials");
    const topAdminRole = document.getElementById("topAdminRole");

    function updateTopAdmin(name, email, profileImage) {

        const safeName = name || "Administrator";
        const safeEmail = email || "";
        if (topAdminRole) topAdminRole.textContent = window.markazCurrentRole === "exam_officer" ? "Exam Officer" : "Administrator";

        if (topAdminName) {
            topAdminName.textContent = safeName;
        }

        if (topDropdownName) {
            topDropdownName.textContent = safeName;
        }

        if (topDropdownEmail) {
            topDropdownEmail.textContent = safeEmail;
        }

        if (topAdminInitials) {
            topAdminInitials.innerHTML = "";

            if (profileImage) {
                const image = document.createElement("img");
                image.src = "/" + profileImage.replace(/^\/+/, "");
                image.alt = safeName;
                image.loading = "eager";
                image.decoding = "async";
                topAdminInitials.appendChild(image);
            } else {
                const initialsSpan = document.createElement("span");
                initialsSpan.textContent = getInitials(safeName);
                topAdminInitials.appendChild(initialsSpan);
            }

            topAdminInitials.classList.add("avatar-ready");
        }
    }

    profileButton.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
        const isOpen = dropdown.classList.toggle("open");

        profileButton.setAttribute(
            "aria-expanded",
            isOpen ? "true" : "false"
        );
    });

    document.addEventListener("click", (event) => {

        if (
            !profileWrap.contains(event.target)
        ) {
            dropdown.classList.remove("open");
            profileButton.setAttribute(
                "aria-expanded",
                "false"
            );
        }
    });

    const dropdownItems =
        dropdown.querySelectorAll(".profile-dropdown-item");

    dropdownItems[0]?.addEventListener("click", () => {
        console.log("Profile selected.");
    });

    dropdownItems[1]?.addEventListener("click", () => {
        console.log("Settings selected.");
    });

    dropdownItems[2]?.addEventListener("click", logout);

    window.markazUpdateTopAdmin = updateTopAdmin;
}

loadAdmin();


/* =========================================================
   MARKAZ — BATCH 32
   PROFILE + SETTINGS NAVIGATION
   ========================================================= */

document.addEventListener("click", (event) => {
    const item = event.target.closest(".profile-dropdown-item");
    if (!item) return;

    const label = item.textContent.trim().toLowerCase();
    if (label !== "profile" && label !== "settings") return;

    if (window.markazCurrentRole === "exam_officer") {
        window.location.href = "exam-officer-settings.html";
        return;
    }
    if (window.markazCurrentRole === "admin") window.location.href = "settings.html";
});


function applyDashboardRoleAccess(role, permissions = {}) {
    document.querySelectorAll("[data-role-access]").forEach((element) => {
        const requiredRole = element.dataset.roleAccess;
        let allowed = true;

        if (requiredRole === "admin") {
            allowed = role === "admin";
        } else if (requiredRole === "admin_or_exam_officer") {
            allowed = role === "admin" || role === "exam_officer";
        } else if (requiredRole === "exam_officer_settings") {
            allowed = role === "exam_officer";
        } else if (requiredRole === "exam_officer") {
            allowed = role === "admin" || role === "exam_officer";
        } else if (requiredRole === "print_results") {
            allowed = permissions.can_print_results === true;
        } else if (requiredRole === "export_results") {
            allowed = permissions.can_export_results === true;
        } else if (requiredRole === "attendance") {
            allowed = role === "admin";
        }

        element.style.display = allowed ? "" : "none";
        element.style.visibility = "visible";
    });
    document.body.classList.add("role-access-ready");
}

/* =========================================================
   MARKAZ — DASHBOARD LIVE STATISTICS
   ========================================================= */


function renderAttendanceDonut(element, values, size, radius, strokeWidth) {
    if (!element) return;

    const namespace = "http://www.w3.org/2000/svg";
    const center = size / 2;
    const circumference = 2 * Math.PI * radius;
    const total = values.reduce((sum, item) => sum + Math.max(0, Number(item.value) || 0), 0);
    let previousLength = 0;

    let svg = element.querySelector("svg.attendance-donut-svg");
    if (!svg) {
        svg = document.createElementNS(namespace, "svg");
        svg.classList.add("attendance-donut-svg");
        svg.setAttribute("viewBox", "0 0 " + size + " " + size);
        svg.setAttribute("aria-hidden", "true");
        element.insertBefore(svg, element.firstChild);
    }
    svg.replaceChildren();

    const track = document.createElementNS(namespace, "circle");
    track.setAttribute("cx", center);
    track.setAttribute("cy", center);
    track.setAttribute("r", radius);
    track.setAttribute("fill", "none");
    track.setAttribute("stroke", "#e6eadf");
    track.setAttribute("stroke-width", strokeWidth);
    svg.appendChild(track);

    if (total > 0) {
        const segments = document.createElementNS(namespace, "g");
        segments.setAttribute("transform", "rotate(-90 " + center + " " + center + ")");
        values.forEach(item => {
            const amount = Math.max(0, Number(item.value) || 0);
            if (!amount) return;

            const length = circumference * amount / total;
            const visibleLength = Math.max(0, length - 2.5);
            const circle = document.createElementNS(namespace, "circle");
            circle.setAttribute("cx", center);
            circle.setAttribute("cy", center);
            circle.setAttribute("r", radius);
            circle.setAttribute("fill", "none");
            circle.setAttribute("stroke", item.color);
            circle.setAttribute("stroke-width", strokeWidth);
            circle.setAttribute("stroke-linecap", "butt");
            circle.setAttribute("stroke-dasharray", visibleLength + " " + (circumference - length + 2.5));
            circle.setAttribute("stroke-dashoffset", String(-previousLength));
            segments.appendChild(circle);
            previousLength += length;
        });
        svg.appendChild(segments);
    }

    element.setAttribute(
        "aria-label",
        values.map(item => item.label + " " + item.value).join(", ")
    );
}

function renderAttendanceComboChart(rows, category, target) {
    const escape = value => String(value ?? "").replace(/[&<>"']/g, char => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[char]));
    const conventionalOrder = ["KG", "Pre-Nursery 1", "Pre-Nursery 2", "Nursery 1", "Nursery 2", "Basic 1", "Basic 2", "Basic 3", "Basic 4", "Basic 5", "JSS 1", "JSS 2", "JSS 3", "SS 1"];
    const arabicOrder = ["Roudoh Awwal", "Roudoh Sani", "Faslu Ula", "Faslu Sani", "Faslu Salis", "Faslu Rabi'", "Faslu Khamis", "Faslu Sadis"];
    const order = category === "arabic" ? arabicOrder : conventionalOrder;
    const normalize = value => String(value || "").trim().toLowerCase().replace(/[’']/g, "'").replace(/\s+/g, " ");
    const byName = new Map(rows.map(row => [normalize(row.class_name), row]));
    const data = order.map(name => {
        const row = byName.get(normalize(name)) || {};
        const present = Math.max(0, Number(row.present_count) || 0);
        const absent = Math.max(0, Number(row.absent_count) || 0);
        const late = Math.max(0, Number(row.late_count) || 0);
        const excused = Math.max(0, Number(row.excused_count) || 0);
        const marked = present + absent + late + excused;
        return { name, present, absent, late, excused, marked, rate: marked > 0 ? present / marked * 100 : null };
    });

    // Every class keeps its own slot, even with no records. The chart fills its card and only
    // scrolls sideways when the card is narrower than the minimum (44px / 40px per class).
    const slotMin = category === "arabic" ? 40 : 44;
    const top = 14, right = 46, bottom = 96, plotHeight = 150;
    // Room on the left for the first rotated class names (about 6.2px per character, rotated -38deg).
    const labelRoom = Math.max(...data.map((item, index) => {
        const text = item.name + " · " + (item.rate === null ? "—" : "100%");
        return text.length * 6.2 * Math.cos(38 * Math.PI / 180) - (index + .5) * slotMin;
    }));
    const left = Math.max(40, Math.ceil(labelRoom) + 12);
    const minWidth = data.length * slotMin + left + right;
    const available = target && target.clientWidth ? Math.floor(target.clientWidth) : minWidth;
    const width = Math.max(minWidth, available);
    const height = top + plotHeight + bottom;
    const plotWidth = width - left - right;
    // 4 equal intervals so the count axis (left) and the rate axis (right, 0-100%) share the same gridlines.
    const baseMax = category === "arabic" ? 12 : 20;
    const tallest = Math.max(0, ...data.map(item => item.marked));
    const countMax = Math.max(baseMax, Math.ceil(tallest / 4) * 4);
    const countStep = countMax / 4;
    const yCount = value => top + plotHeight - (Math.min(countMax, value) / countMax) * plotHeight;
    const yRate = value => top + plotHeight - (value / 100) * plotHeight;
    const slot = plotWidth / data.length;
    const x = index => left + (index + .5) * slot;
    const series = [
        ["present", "#2a8f58"], ["absent", "#e05a5a"],
        ["late", "#d9a62e"], ["excused", "#8a94a6"]
    ];
    const grid = Array.from({ length: 5 }, (_, index) => {
        const count = index * countStep, y = yCount(count), rate = index * 25;
        return '<line x1="' + left + '" y1="' + y + '" x2="' + (width - right) + '" y2="' + y + '" class="combo-gridline"/>' +
            '<text x="' + (left - 10) + '" y="' + (y + 4) + '" text-anchor="end" class="combo-axis-label">' + count + '</text>' +
            '<text x="' + (width - right + 10) + '" y="' + (y + 4) + '" text-anchor="start" class="combo-rate-axis-label">' + rate + '%</text>';
    }).join("");

    const bars = data.map((item, index) => {
        const center = x(index), barWidth = Math.min(34, slot * .52);
        let stacked = 0;
        const rects = series.map(([key, color]) => {
            const value = item[key], visibleValue = Math.max(0, Math.min(value, countMax - stacked));
            const y = yCount(stacked + visibleValue), barHeight = visibleValue / countMax * plotHeight;
            stacked += value;
            if (!visibleValue) return "";
            return '<rect x="' + (center - barWidth / 2) + '" y="' + y + '" width="' + barWidth + '" height="' + barHeight + '" rx="2" fill="' + color + '"><title>' +
                escape(item.name) + " — " + key + ": " + value + "</title></rect>";
        }).join("");
        const label = item.name + " · " + (item.rate === null ? "—" : item.rate.toFixed(0) + "%");
        const labelY = top + plotHeight + 16;
        return rects + '<text x="' + (center + 4) + '" y="' + labelY + '" transform="rotate(-38 ' + (center + 4) + " " + labelY + ')" text-anchor="end" class="combo-class-label">' +
            escape(label) + "</text>";
    }).join("");

    // Split the rate line at unmarked classes; null values never become zero-percent points.
    const ratePaths = [];
    let currentPath = [];
    data.forEach((item, index) => {
        if (item.rate === null) {
            if (currentPath.length) ratePaths.push(currentPath);
            currentPath = [];
            return;
        }
        currentPath.push([x(index), yRate(item.rate)]);
    });
    if (currentPath.length) ratePaths.push(currentPath);
    const lines = ratePaths.map(path => path.length > 1
        ? '<polyline points="' + path.map(point => point.join(",")).join(" ") + '" class="combo-rate-line"/>'
        : "").join("");
    const circles = data.map((item, index) => {
        if (item.rate === null) return "";
        return '<circle cx="' + x(index) + '" cy="' + yRate(item.rate) + '" r="4.5" class="combo-rate-point"><title>' +
            escape(item.name) + ": " + item.present + " present of " + item.marked + " marked records (" + item.rate.toFixed(1) + "%)</title></circle>";
    }).join("");

    return '<div class="dashboard-attendance-combo-scroll"><svg class="dashboard-attendance-combo-chart" viewBox="0 0 ' + width + " " + height +
        '" width="' + width + '" height="' + height + '" role="img" aria-label="' + escape(category) +
        ' attendance chart. Stacked bars show present, absent, late and excused marked records; the line shows present rate among marked records.">' +
        grid + '<line x1="' + left + '" y1="' + (top + plotHeight) + '" x2="' + (width - right) + '" y2="' + (top + plotHeight) + '" class="combo-baseline"/>' +
        bars + lines + circles + "</svg></div>";
}

async function loadDashboardAttendance() {
    const panel = document.getElementById("dashboardAttendancePanel");
    if (!panel) return;

    if ((window.markazCurrentRole || "") !== "admin") {
        panel.hidden = true;
        return;
    }

    panel.hidden = false;
    const date = new Date();
    const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 10);
    const dateLabel = document.getElementById("dashboardAttendanceDate");
    const layout = panel.querySelector(".dashboard-attendance-layout");
    const emptyState = document.getElementById("dashboardAttendanceEmpty");

    if (dateLabel) {
        dateLabel.textContent = new Intl.DateTimeFormat(undefined, {
            dateStyle: "full"
        }).format(date);
    }

    if (emptyState) emptyState.hidden = true;
    if (layout) layout.hidden = false;

    try {
        // One school-wide request supplies both class categories. The report endpoint
        // requires class_id whenever class_type is supplied, so category-only requests fail.
        const response = await fetch(
            "./api/attendance/reports.php?from=" + encodeURIComponent(localDate) +
            "&to=" + encodeURIComponent(localDate),
            {
                credentials: "same-origin",
                cache: "no-store",
                headers: { Accept: "application/json" }
            }
        );
        const report = await response.json();

        if (!response.ok || !report.success) {
            throw new Error(report.message || "Attendance summary unavailable.");
        }

        const summary = report.summary || {};
        const markedRecords = Number(summary.marked_records || 0);
        const setText = (id, value) => {
            const node = document.getElementById(id);
            if (node) node.textContent = String(value);
        };

        // Keep both category panels visible even before the first attendance entry.
        // The API returns every active class; zero-record classes must remain in the report.
        if (emptyState) emptyState.hidden = markedRecords > 0;
        if (layout) layout.hidden = false;

        const values = [
            { label: "Present", value: Number(summary.present || 0), color: "#2a8f58" },
            { label: "Absent", value: Number(summary.absent || 0), color: "#e05a5a" },
            { label: "Late", value: Number(summary.late || 0), color: "#d9a62e" },
            { label: "Excused", value: Number(summary.excused || 0), color: "#8a94a6" }
        ];
        const total = values.reduce((sum, item) => sum + item.value, 0);
        const donut = document.getElementById("dashboardAttendanceDonut");

        if (donut) {
            renderAttendanceDonut(donut, values, 148, 66, 16);
        }

        setText("dashboardAttendanceMarked", total);
        setText("dashboardAttendancePresent", values[0].value);
        setText("dashboardAttendanceAbsent", values[1].value);
        setText("dashboardAttendanceLate", values[2].value);
        setText("dashboardAttendanceExcused", values[3].value);

        const legend = document.getElementById("dashboardAttendanceLegend");
        if (legend) {
            legend.innerHTML = values.map(item =>
                '<span><i style="background:' + item.color + '"></i>' +
                item.label + ': <strong>' + item.value + '</strong></span>'
            ).join("");
        }

        const allClasses = Array.isArray(report.classes) ? report.classes : [];

        for (const [type, targetId] of [
            ["conventional", "dashboardConventionalAttendance"],
            ["arabic", "dashboardArabicAttendance"]
        ]) {
            const target = document.getElementById(targetId);
            if (!target) continue;

            const rows = allClasses.filter(item => String(item.class_type || "").trim().toLowerCase() === type);
            const categoryValues = [
                { label: "Present", value: rows.reduce((sum, item) => sum + Number(item.present_count || 0), 0), color: "#2a8f58" },
                { label: "Absent", value: rows.reduce((sum, item) => sum + Number(item.absent_count || 0), 0), color: "#e05a5a" },
                { label: "Late", value: rows.reduce((sum, item) => sum + Number(item.late_count || 0), 0), color: "#d9a62e" },
                { label: "Excused", value: rows.reduce((sum, item) => sum + Number(item.excused_count || 0), 0), color: "#8a94a6" }
            ];
            const categoryTotal = categoryValues.reduce((sum, item) => sum + item.value, 0);
            const categoryPrefix = type === "conventional" ? "dashboardConventional" : "dashboardArabic";
            const categoryDonut = document.getElementById(categoryPrefix + "AttendanceDonut");

            if (categoryDonut) {
                renderAttendanceDonut(categoryDonut, categoryValues, 126, 56, 14);
                const number = categoryDonut.querySelector("strong");
                if (number) number.textContent = String(categoryTotal);
                categoryDonut.setAttribute(
                    "aria-label",
                    type + " attendance: " +
                    categoryValues.map(item => item.label + " " + item.value).join(", ")
                );
            }

            const categoryLegend = document.getElementById(categoryPrefix + "AttendanceLegend");
            if (categoryLegend) {
                categoryLegend.innerHTML = categoryValues.map(item =>
                    '<span><i style="background:' + item.color + '"></i>' +
                    item.label + ' <strong>' + item.value + '</strong></span>'
                ).join("") + '<span class="dashboard-attendance-rate-key"><i></i>Present rate</span>';
            }

            window.markazAttendanceRows = window.markazAttendanceRows || {};
            window.markazAttendanceRows[type] = rows;
            target.innerHTML = rows.length
                ? renderAttendanceComboChart(rows, type, target)
                :
                '<div class="dashboard-chart-empty dashboard-chart-empty-state">' +
                '<i class="fa-regular fa-calendar-check"></i>' +
                '<strong>No attendance entered for this category</strong>' +
                '<span>Saved attendance for today will appear here.</span></div>';
        }
    } catch (error) {
        const message = String(error && error.message || "Unknown error")
            .replace(/[&<>"']/g, ch => ({
                "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
            }[ch]));

        for (const id of ["dashboardConventionalAttendance", "dashboardArabicAttendance"]) {
            const node = document.getElementById(id);
            if (node) {
                node.innerHTML = '<div class="dashboard-chart-empty dashboard-chart-error">' +
                    '<i class="fa-solid fa-triangle-exclamation"></i>' +
                    '<strong>Attendance summary unavailable</strong><span>' + message +
                    '</span></div>';
            }
        }
        console.error("Dashboard attendance report failed:", error);
    }
}
window.setInterval(loadDashboardAttendance, 60000);

// Redraw the two class charts when the window width changes, so they always fill their cards.
let attendanceResizeTimer = null;
let attendanceLastWidth = window.innerWidth;
window.addEventListener("resize", () => {
    if (window.innerWidth === attendanceLastWidth) return;
    attendanceLastWidth = window.innerWidth;
    window.clearTimeout(attendanceResizeTimer);
    attendanceResizeTimer = window.setTimeout(() => {
        const saved = window.markazAttendanceRows || {};
        for (const [type, targetId] of [["conventional", "dashboardConventionalAttendance"], ["arabic", "dashboardArabicAttendance"]]) {
            const target = document.getElementById(targetId);
            if (target && saved[type] && saved[type].length) {
                target.innerHTML = renderAttendanceComboChart(saved[type], type, target);
            }
        }
    }, 150);
});
document.addEventListener("visibilitychange", () => {
    if (!document.hidden) {
        loadDashboardAttendance();
        loadDashboardStats();
    }
});

// A dashboard restored from the browser back-forward cache does not rerun
// its initial script. Refresh live statistics when that page is shown again.
window.addEventListener("pageshow", () => {
    loadAdmin();
    loadDashboardStats();
});

async function loadDashboardStats() {
    const statCards = document.querySelectorAll(".stat-card .stat-main > strong");

    if (statCards.length < 3) return;

    try {
        const response = await fetch("./api/dashboard/stats.php", {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store",
            headers: {
                "Accept": "application/json"
            }
        });

        const data = await response.json();

        if (!response.ok || !data.success || !data.stats) {
            console.error("Dashboard statistics request failed:", data);
            return;
        }

        if (data.role === "teacher") {
            const labels = document.querySelectorAll(".stat-card .stat-label");
            const descriptions = document.querySelectorAll(".stat-card small");
            const values = [
                data.stats.assigned_students ?? 0,
                data.stats.assigned_classes ?? 0,
                data.stats.assigned_subjects ?? 0,
            ];
            const names = ["Assigned Students", "Assigned Classes", "Assigned Subjects"];
            const notes = [
                "Students in your assigned classes",
                "Classes assigned to you",
                "Subjects assigned to you",
            ];

            statCards.forEach((node, index) => node.textContent = values[index]);
            labels.forEach((node, index) => node.textContent = names[index]);
            descriptions.forEach((node, index) => node.textContent = notes[index]);

            const charts = document.querySelector(".dashboard-completion-charts");
            if (charts) charts.style.display = "none";

            const progress = document.querySelector(".result-progress-section");
            if (progress) progress.hidden = true;

            const section = document.getElementById("teacherAssignmentsSection");
            const list = document.getElementById("teacherAssignmentsList");
            if (section && list) {
                section.style.display = "";
                list.replaceChildren();

                const assignments = Array.isArray(data.assignments) ? data.assignments : [];
                if (!assignments.length) {
                    const empty = document.createElement("p");
                    empty.textContent = "No active teaching assignments yet.";
                    list.appendChild(empty);
                }

                assignments.forEach(item => {
                    const card = document.createElement("article");
                    card.style.cssText = "border:1px solid #dce7df;border-radius:14px;padding:18px;background:#fff;";

                    const heading = document.createElement("h3");
                    heading.textContent = `${item.class_name} · ${item.class_type === "arabic" ? "Arabic" : "Conventional"}`;
                    heading.style.marginTop = "0";

                    const students = document.createElement("p");
                    students.textContent = `${item.student_count} assigned students`;

                    const subjects = document.createElement("p");
                    subjects.textContent = item.full_class
                        ? "Full class access · Behavioural assessments included"
                        : (Array.isArray(item.subjects) && item.subjects.length
                            ? `Subjects: ${item.subjects.join(", ")}`
                            : "No active subjects assigned");

                    const progressLine = document.createElement("p");
                    progressLine.textContent =
                        `Entry progress: ${item.completed_entries}/${item.expected_entries} (${item.completion_percentage}%)`;

                    const track = document.createElement("div");
                    track.style.cssText = "height:8px;background:#e8eee9;border-radius:8px;overflow:hidden;";
                    const bar = document.createElement("div");
                    bar.style.cssText = `height:100%;width:${Math.max(0, Math.min(100, Number(item.completion_percentage) || 0))}%;background:#198754;border-radius:8px;`;
                    track.appendChild(bar);

                    const button = document.createElement("button");
                    button.type = "button";
                    button.className = "btn btn-primary";
                    button.textContent = "Open Class";
                    button.addEventListener("click", () => {
                        const params = new URLSearchParams({
                            class_id: String(item.class_id),
                            class_type: String(item.class_type)
                        });
                        window.location.href = `./class.html?id=${encodeURIComponent(item.class_id)}&class_type=${encodeURIComponent(item.class_type)}`;
                    });

                    card.append(heading, students, subjects, progressLine, track, button);
                    list.appendChild(card);
                });
            }
            return;
        }

        statCards[0].textContent = data.stats.students;
        statCards[1].textContent = data.stats.classes;
        statCards[2].textContent = data.stats.subjects;

        const completeStudents = document.getElementById("completeStudents");
        const incompleteStudents = document.getElementById("incompleteStudents");
        const noResultStudents = document.getElementById("noResultStudents");
        const resultEntriesProgress = document.getElementById("resultEntriesProgress");
        const resultCompletionPercentage = document.getElementById("resultCompletionPercentage");

        if (completeStudents) {
            completeStudents.textContent = data.stats.complete_students ?? 0;
        }

        if (incompleteStudents) {
            incompleteStudents.textContent = data.stats.incomplete_students ?? 0;
        }

        if (noResultStudents) {
            noResultStudents.textContent = data.stats.no_result_students ?? 0;
        }

        const unassignedStudentsNote = document.getElementById("unassignedStudentsNote");
        if (unassignedStudentsNote) {
            const count = Number(data.stats.unassigned_students ?? 0);
            unassignedStudentsNote.textContent =
                count + (count === 1 ? " student is" : " students are") +
                " awaiting required subject assignments";
        }

        if (resultEntriesProgress) {
            resultEntriesProgress.textContent =
                `${data.stats.completed_results ?? 0} / ${data.stats.expected_results ?? 0}`;
        }

        if (resultCompletionPercentage) {
            resultCompletionPercentage.textContent =
                `${data.stats.completion_percentage ?? 0}%`;
        }

        const overall = Number(data.stats.completion_percentage ?? 0);
        const donut = document.getElementById("dashboardCompletionPie");
        const donutValue = document.getElementById("dashboardCompletionPieValue");
        const overallCaption = document.getElementById("dashboardSchoolCompletionCaption");
        if (donut) { donut.style.setProperty("--completion", overall + "%"); donut.setAttribute("aria-label", "Overall examination completion " + overall + "%"); }
        if (donutValue) donutValue.textContent = overall + "%";
        if (overallCaption) overallCaption.textContent = (data.stats.completed_results ?? 0) + " of " + (data.stats.expected_results ?? 0) + " required subject entries completed";

    } catch (error) {
        console.error("Unable to load dashboard statistics:", error);
    }
}

loadDashboardStats();

/* =========================================================
   MARKAZ — QUICK ACCESS NAVIGATION
   ========================================================= */

document.querySelectorAll(".quick-actions button").forEach((button) => {
    button.addEventListener("click", () => {
        const destinations = {
            "Add Student": "students.html",
            "Manage Classes": "classes.html",
            "Manage Subjects": "subjects/index.html",
            "Enter Results": "classes.html",
            "Print Results": "print-results.html",
            "Attendance": "attendance.html",
            "School Fees": "school-fees.html",
            "System Settings": "settings.html",
            "Settings": "exam-officer-settings.html"
        };

        const destination = destinations[button.textContent.trim()];

        if (destination) {
            window.location.href = destination;
        }
    });
});

/* Sidebar controls are handled once by the shared responsive toggle above. */

// Keep exactly ONE logout control in the sidebar (the icon button inside the profile card).
// Any other logout button/link that another script adds to the sidebar is removed.
(function keepSingleSidebarLogout() {
    const sidebar = document.getElementById("sidebar");
    const keep = document.getElementById("logoutButton");
    if (!sidebar || !keep) return;
    const looksLikeLogout = element => {
        const text = element.children.length ? "" : (element.textContent || "");
        const label = [element.id, element.className && element.className.toString(), element.getAttribute("href"), text]
            .join(" ").toLowerCase();
        return /log[\s_-]?out|sign[\s_-]?out/.test(label);
    };
    const clean = () => {
        sidebar.querySelectorAll("button, a, div, span").forEach(element => {
            if (element === keep || keep.contains(element) || element.contains(keep)) return;
            if (looksLikeLogout(element)) element.remove();
        });
    };
    clean();
    let timer = null;
    new MutationObserver(() => {
        window.clearTimeout(timer);
        timer = window.setTimeout(clean, 50);
    }).observe(sidebar, { childList: true, subtree: true });
})();
