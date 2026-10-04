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
        const profileImage = data.admin.profile_image || "";
        const initials = getInitials(name);

        console.log("MARKAZ ADMIN DATA:", {
            name,
            email,
            profileImage,
            initials,
            topUpdaterReady: typeof window.markazUpdateTopAdmin === "function"
        });

        applyDashboardRoleAccess(role);

        if (adminName) adminName.textContent = name;
        if (welcomeAdminName) welcomeAdminName.textContent = name;
        if (adminDetailsName) adminDetailsName.textContent = name;
        if (adminEmail) adminEmail.textContent = email;
        if (profileName) profileName.textContent = name;
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

    sidebar.classList.add("open");

    if (sidebarOverlay) {
        sidebarOverlay.classList.add("visible");
    }

    if (menuButton) {
        menuButton.setAttribute("aria-expanded", "true");
    }
}

function closeSidebar() {
    if (!sidebar) return;

    sidebar.classList.remove("open");

    if (sidebarOverlay) {
        sidebarOverlay.classList.remove("visible");
    }

    if (menuButton) {
        menuButton.setAttribute("aria-expanded", "false");
    }
}

function toggleSidebar() {
    if (!sidebar) return;

    if (sidebar.classList.contains("open")) {
        closeSidebar();
    } else {
        openSidebar();
    }
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

    try {
        await fetch("./api/auth/logout.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        });
    } catch (error) {
        console.error("Logout request failed:", error);
    }

    window.location.href = "login.html";
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
        document
            .querySelectorAll(".nav-link")
            .forEach((item) => item.classList.remove("active"));

        link.classList.add("active");

        if (window.innerWidth <= 850) {
            closeSidebar();
        }
    });
});

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

    const refreshButton = document.createElement("button");
    refreshButton.type = "button";
    refreshButton.className = "dashboard-refresh-button";
    refreshButton.setAttribute("aria-label", "Refresh dashboard");
    refreshButton.title = "Refresh dashboard";
    refreshButton.innerHTML = "↻";

    refreshButton.addEventListener("click", () => {
        refreshButton.style.transform = "rotate(360deg)";

        setTimeout(() => {
            window.location.reload();
        }, 180);
    });

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
            <span>Administrator</span>
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

        <button type="button" class="profile-dropdown-item logout">
            Logout
        </button>
    `;

    profileWrap.appendChild(profileButton);
    profileWrap.appendChild(dropdown);

    actions.appendChild(refreshButton);
    actions.appendChild(profileWrap);
    topbar.appendChild(actions);

    const topAdminName = document.getElementById("topAdminName");
    const topDropdownName = document.getElementById("topDropdownName");
    const topDropdownEmail = document.getElementById("topDropdownEmail");
    const topAdminInitials = document.getElementById("topAdminInitials");

    function updateTopAdmin(name, email, profileImage) {

        const safeName = name || "Administrator";
        const safeEmail = email || "";

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
        event.stopPropagation();

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

    const item = event.target.closest(
        ".profile-dropdown-item"
    );

    if (!item) return;

    const label = item.textContent.trim().toLowerCase();

    if (label === "profile") {
        window.location.href = "settings.html";
    }

    if (label === "settings") {
        window.location.href = "settings.html";
    }

});


function applyDashboardRoleAccess(role) {
    document.querySelectorAll("[data-role-access]").forEach((element) => {
        const requiredRole = element.dataset.roleAccess;

        let allowed = true;

        if (requiredRole === "admin") {
            allowed = role === "admin";
        }

        if (requiredRole === "exam_officer") {
            allowed = role === "admin" || role === "exam_officer";
        }

        element.style.display = allowed ? "" : "none";
    });
}

/* =========================================================
   MARKAZ — DASHBOARD LIVE STATISTICS
   ========================================================= */

async function loadDashboardStats() {
    const statCards = document.querySelectorAll(".stat-card > strong");

    if (statCards.length < 4) return;

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

        statCards[0].textContent = data.stats.students;
        statCards[1].textContent = data.stats.classes;
        statCards[2].textContent = data.stats.subjects;
        statCards[3].textContent = data.stats.results;

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

        if (resultEntriesProgress) {
            resultEntriesProgress.textContent =
                `${data.stats.results ?? 0} / ${data.stats.expected_results ?? 0}`;
        }

        if (resultCompletionPercentage) {
            resultCompletionPercentage.textContent =
                `${data.stats.completion_percentage ?? 0}%`;
        }

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
            "Print Results": "classes.html",
            "System Settings": "settings.html"
        };

        const destination = destinations[button.textContent.trim()];

        if (destination) {
            window.location.href = destination;
        }
    });
});

/* =========================================================
   DESKTOP SIDEBAR TOGGLE
   ========================================================= */

(function () {
    const sidebar = document.getElementById("sidebar");
    const menuButton = document.getElementById("menuButton");
    const closeButton = document.getElementById("sidebarCloseButton");

    if (!sidebar) return;

    function closeDashboardSidebar() {
        if (window.innerWidth >= 851) {
            sidebar.classList.add("desktop-closed");
            document.body.classList.add("sidebar-desktop-closed");
        } else {
            sidebar.classList.remove("open");
        }
    }

    function openDashboardSidebar() {
        if (window.innerWidth >= 851) {
            sidebar.classList.remove("desktop-closed");
            document.body.classList.remove("sidebar-desktop-closed");
        } else {
            sidebar.classList.add("open");
        }
    }

    closeButton?.addEventListener("click", function () {
        closeDashboardSidebar();
    });

    menuButton?.addEventListener("click", function () {
        if (window.innerWidth >= 851) {
            if (sidebar.classList.contains("desktop-closed")) {
                openDashboardSidebar();
            } else {
                closeDashboardSidebar();
            }
        }
    });
})();
