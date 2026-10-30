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
        if (roleLabel) roleLabel.textContent = ({admin:"SCHOOL ADMINISTRATION",exam_officer:"EXAMINATION OFFICE",teacher:"TEACHER WORKSPACE",management:"SCHOOL MANAGEMENT",student:"STUDENT PORTAL"})[role] || "MARKAZ SCHOOL PORTAL";
        window.markazCurrentRole = role;
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
        document
            .querySelectorAll(".nav-link")
            .forEach((item) => item.classList.remove("active"));

        link.classList.add("active");

        if (window.innerWidth <= 850) {
            closeSidebar();
        }
    });
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

        <button type="button" class="profile-dropdown-item logout" id="topProfileLogoutButton">
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

    if (window.markazCurrentRole !== "admin") {
        window.location.href = "dashboard.html";
        return;
    }

    window.location.href = "settings.html";
});


function applyDashboardRoleAccess(role, permissions = {}) {
    document.querySelectorAll("[data-role-access]").forEach((element) => {
        const requiredRole = element.dataset.roleAccess;
        let allowed = true;

        if (requiredRole === "admin") {
            allowed = role === "admin";
        } else if (requiredRole === "exam_officer") {
            allowed = role === "admin" || role === "exam_officer";
        } else if (requiredRole === "print_results") {
            allowed = permissions.can_print_results === true;
        } else if (requiredRole === "export_results") {
            allowed = permissions.can_export_results === true;
        } else if (requiredRole === "attendance") {
            allowed = ["admin", "exam_officer", "teacher", "management"].includes(role);
        }

        element.style.display = allowed ? "" : "none";
        element.style.visibility = "visible";
    });
    document.body.classList.add("role-access-ready");
}

/* =========================================================
   MARKAZ — DASHBOARD LIVE STATISTICS
   ========================================================= */


function dashboardDonutGradient(values) {
    const total = values.reduce((sum, item) => sum + Math.max(0, Number(item.value) || 0), 0);
    if (!total) return "conic-gradient(#e7eee9 0 100%)";
    let cursor = 0;
    const parts = values.map(item => { const start=cursor; cursor += Math.max(0,Number(item.value)||0)/total*100; return item.color+" "+start.toFixed(2)+"% "+cursor.toFixed(2)+"%"; });
    return "conic-gradient("+parts.join(",")+")";
}

function dashboardRenderClassPie(targetId, classes) {
    const target=document.getElementById(targetId); if(!target)return;
    if(!Array.isArray(classes)||!classes.length){target.innerHTML='<p class="dashboard-chart-empty">No active classes found in this category.</p>';return;}
    const palette=["#17643a","#c9a94b","#4d8fbd","#d47758","#7b6ab2","#45a59a","#a36b91","#8a9b57","#d29a42","#5c7d9d"];
    const totalExpected=classes.reduce((sum,item)=>sum+Math.max(0,Number(item.expected_entries)||0),0);
    const totalCompleted=classes.reduce((sum,item)=>sum+Math.max(0,Number(item.entered_entries)||0),0);
    const segments=classes.map((item,index)=>({value:Math.max(0,Number(item.expected_entries)||0),color:palette[index%palette.length]}));
    const gradient=dashboardDonutGradient(segments);
    const legend=classes.map((item,index)=>{
        const name=String(item.class_name||"Class").replace(/[&<>"\']/g,ch=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","\'":"&#039;"}[ch]));
        const percent=Math.max(0,Math.min(100,Number(item.completion_percentage)||0));
        const expected=Math.max(0,Number(item.expected_entries)||0), completed=Math.max(0,Number(item.entered_entries)||0);
        return '<div class="dashboard-class-pie-row"><span class="dashboard-chart-dot" style="--dot:'+palette[index%palette.length]+'"></span><div class="dashboard-class-pie-meta"><strong>'+name+'</strong><small>'+completed+' / '+expected+' complete entries</small><div class="dashboard-class-bar" role="progressbar" aria-valuenow="'+percent+'" aria-valuemin="0" aria-valuemax="100" aria-label="'+name+' completion"><i style="width:'+percent+'%;--bar:'+palette[index%palette.length]+'"></i></div></div><b>'+percent.toFixed(1)+'%</b></div>';
    }).join("");
    target.innerHTML='<div class="dashboard-pie-summary"><div class="dashboard-pie-visual" role="img" aria-label="'+totalCompleted+' of '+totalExpected+' complete entries across '+classes.length+' classes" style="background:'+gradient+'"><div><strong>'+(totalExpected?Math.round(totalCompleted/totalExpected*100):0)+'%</strong><span>Complete</span></div></div><div class="dashboard-pie-totals"><strong>'+classes.length+'</strong><span>Active classes</span><small>'+totalCompleted+' / '+totalExpected+' entries</small></div></div><div class="dashboard-class-pie-legend">'+legend+'</div>';
}

async function loadDashboardAttendance() {
    const panel=document.getElementById("dashboardAttendancePanel"); if(!panel)return;
    const role=window.markazCurrentRole||""; if(!["admin","exam_officer","management"].includes(role)){panel.hidden=true;return;} panel.hidden=false;
    const date=new Date(), localDate=new Date(date.getTime()-date.getTimezoneOffset()*60000).toISOString().slice(0,10);
    const dateLabel=document.getElementById("dashboardAttendanceDate"); if(dateLabel)dateLabel.textContent=new Intl.DateTimeFormat(undefined,{dateStyle:"full"}).format(date);
    try {
        const response=await fetch("./api/attendance/reports.php?from="+localDate+"&to="+localDate,{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});
        const report=await response.json(); if(!response.ok||!report.success)throw new Error(report.message||"Attendance summary unavailable.");
        const s=report.summary||{}, values=[{label:"Present",value:Number(s.present||0),color:"#21945b"},{label:"Absent",value:Number(s.absent||0),color:"#d65359"},{label:"Late",value:Number(s.late||0),color:"#d8a72c"},{label:"Excused",value:Number(s.excused||0),color:"#7d8b9a"}];
        const total=values.reduce((sum,item)=>sum+item.value,0), donut=document.getElementById("dashboardAttendanceDonut");
        if(donut){donut.style.background=dashboardDonutGradient(values);donut.setAttribute("aria-label",values.map(item=>item.label+" "+item.value).join(", "));}
        const setText=(id,value)=>{const node=document.getElementById(id);if(node)node.textContent=String(value);};
        setText("dashboardAttendanceMarked",total);setText("dashboardAttendancePresent",values[0].value);setText("dashboardAttendanceAbsent",values[1].value);setText("dashboardAttendanceLate",values[2].value);setText("dashboardAttendanceExcused",values[3].value);
        const legend=document.getElementById("dashboardAttendanceLegend");if(legend)legend.innerHTML=values.map(item=>'<span><i style="background:'+item.color+'"></i>'+item.label+': <strong>'+item.value+'</strong></span>').join("");
        for(const [type,targetId] of [["conventional","dashboardConventionalAttendance"],["arabic","dashboardArabicAttendance"]]){
            const rows=(Array.isArray(report.classes)?report.classes:[]).filter(item=>item.class_type===type),target=document.getElementById(targetId);if(!target)continue;
            const categoryValues=[
                {label:"Present",value:rows.reduce((sum,item)=>sum+Number(item.present_count||0),0),color:"#21945b"},
                {label:"Absent",value:rows.reduce((sum,item)=>sum+Number(item.absent_count||0),0),color:"#d65359"},
                {label:"Late",value:rows.reduce((sum,item)=>sum+Number(item.late_count||0),0),color:"#d8a72c"},
                {label:"Excused",value:rows.reduce((sum,item)=>sum+Number(item.excused_count||0),0),color:"#7d8b9a"}
            ];
            const categoryTotal=categoryValues.reduce((sum,item)=>sum+item.value,0);
            const categoryPrefix=type==="conventional"?"dashboardConventional":"dashboardArabic";
            const categoryDonut=document.getElementById(categoryPrefix+"AttendanceDonut");
            if(categoryDonut){categoryDonut.style.background=dashboardDonutGradient(categoryValues);const number=categoryDonut.querySelector("strong");if(number)number.textContent=String(categoryTotal);categoryDonut.setAttribute("aria-label",type+" attendance: "+categoryValues.map(item=>item.label+" "+item.value).join(", "));}
            const categoryLegend=document.getElementById(categoryPrefix+"AttendanceLegend");
            if(categoryLegend)categoryLegend.innerHTML=categoryValues.map(item=>'<span><i style="background:'+item.color+'"></i>'+item.label+' <strong>'+item.value+'</strong></span>').join("");
            const maxMarked=Math.max(1,...rows.map(item=>Number(item.marked_records)||0));
            target.innerHTML=rows.length?rows.map(item=>{
                const params=new URLSearchParams({class_id:String(item.class_id),class_type:type,date:localDate});
                const name=String(item.class_name||"Class").replace(/[&<>"\']/g,ch=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","\'":"&#039;"}[ch]));
                const marked=Number(item.marked_records)||0,present=Number(item.present_count)||0,width=Math.max(0,Math.min(100,marked/maxMarked*100));
                return '<a class="dashboard-attendance-class-row" href="./attendance.html?'+params.toString()+'"><span>'+name+'</span><strong>'+present+' present</strong><div class="dashboard-attendance-bar"><i style="width:'+width+'%"></i></div><small>'+marked+' marked · '+Number(item.absent_count||0)+' absent · '+Number(item.late_count||0)+' late · '+Number(item.excused_count||0)+' excused</small></a>';
            }).join(""):'<p class="dashboard-chart-empty">No saved attendance marks today.</p>';
        }
    } catch(error){if(dateLabel)dateLabel.textContent="Today’s attendance could not be loaded: "+error.message;console.error("Dashboard attendance report failed:",error);}
}
window.setInterval(loadDashboardAttendance, 60000);
document.addEventListener("visibilitychange", () => { if (!document.hidden) loadDashboardAttendance(); });

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

        if (data.role === "teacher") {
            const labels = document.querySelectorAll(".stat-card .stat-label");
            const descriptions = document.querySelectorAll(".stat-card small");
            const values = [
                data.stats.assigned_students ?? 0,
                data.stats.assigned_classes ?? 0,
                data.stats.assigned_subjects ?? 0,
                `${data.stats.completion_percentage ?? 0}%`
            ];
            const names = ["Assigned Students", "Assigned Classes", "Assigned Subjects", "Entry Completion"];
            const notes = [
                "Students in your assigned classes",
                "Classes assigned to you",
                "Subjects assigned to you",
                `${data.stats.completed_entries ?? 0} of ${data.stats.expected_entries ?? 0} complete entries`
            ];

            statCards.forEach((node, index) => node.textContent = values[index]);
            labels.forEach((node, index) => node.textContent = names[index]);
            descriptions.forEach((node, index) => node.textContent = notes[index]);

            const charts = document.querySelector(".dashboard-completion-charts");
            if (charts) charts.style.display = "none";

            const progress = document.querySelector(".result-progress-section");
            if (progress) {
                const complete = document.getElementById("completeStudents");
                const incomplete = document.getElementById("incompleteStudents");
                const noResults = document.getElementById("noResultStudents");
                const entries = document.getElementById("resultEntriesProgress");
                const percent = document.getElementById("resultCompletionPercentage");
                if (complete) complete.textContent = data.stats.completed_entries ?? 0;
                if (incomplete) incomplete.textContent = Math.max(
                    0,
                    Number(data.stats.expected_entries ?? 0) - Number(data.stats.completed_entries ?? 0)
                );
                if (noResults) noResults.textContent = "—";
                if (entries) entries.textContent =
                    `${data.stats.completed_entries ?? 0} / ${data.stats.expected_entries ?? 0}`;
                if (percent) percent.textContent = `${data.stats.completion_percentage ?? 0}%`;
            }

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
                `${data.stats.completed_results ?? 0} / ${data.stats.expected_results ?? 0}`;
        }

        if (resultCompletionPercentage) {
            resultCompletionPercentage.textContent =
                `${data.stats.completion_percentage ?? 0}%`;
        }

        const classGroups = data.stats.class_progress_by_type || {};
        dashboardRenderClassPie("dashboardConventionalPie", classGroups.conventional || []);
        dashboardRenderClassPie("dashboardArabicPie", classGroups.arabic || []);
        const overall = Number(data.stats.completion_percentage ?? 0);
        const donut = document.getElementById("dashboardCompletionDonut");
        const donutValue = document.getElementById("dashboardCompletionDonutValue");
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
