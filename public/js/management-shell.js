(() => {
"use strict";
const path = location.pathname;
const isStudentPortal = new URLSearchParams(location.search).get("student_portal") === "1";
let currentRole = "";
const loginPathForRole = role => ({
  teacher: "/teacher-login.html",
  exam_officer: "/exam-officer-login.html",
  management: "/management-login.html",
  student: "/student-login.html"
})[String(role || "").toLowerCase()] || "/login.html";
if (!/\/(students\.html|classes\.html|class\.html|class-subjects\/(index\.html)?|subjects\/(index\.html)?|attendance\.html|school-fees\.html|settings\.html|student-result\.html|print-results\.html)$/.test(path)) return;
if (document.querySelector(".markaz-management-shell")) return;

const header = document.createElement("header");
header.className = "markaz-management-shell";
header.innerHTML = '<button class="markaz-shell-menu" type="button" aria-label="Open management menu" aria-expanded="false"><span aria-hidden="true">☰</span></button><a class="markaz-shell-brand" href="/dashboard.html"><img src="/assets/images/markaz-logo.jpg" alt=""><span>MARKAZ<small>Management</small></span></a><span class="markaz-shell-spacer"></span><button class="markaz-shell-profile" type="button" aria-expanded="false"><span class="admin-profile-avatar"><span id="markazShellInitials">AD</span></span><span class="markaz-shell-profile-copy"><strong id="markazShellName">Administrator</strong><small id="markazShellRole">Management</small></span><span aria-hidden="true">▾</span></button><div class="markaz-shell-profile-menu" hidden><div class="markaz-shell-profile-details"><strong id="markazShellMenuName">Administrator</strong><small id="markazShellEmail"></small></div><a href="/settings.html" data-shell-settings hidden>Profile settings</a><button type="button" id="markazShellLogout">Logout</button></div>';
document.body.prepend(header);

const overlay = document.createElement("div");
overlay.className = "markaz-shell-overlay";
overlay.hidden = true;
const sidebar = document.createElement("aside");
sidebar.className = "markaz-shell-sidebar";
sidebar.innerHTML = '<div class="markaz-shell-sidebar-head"><img src="/assets/images/markaz-logo.jpg" alt=""><strong>MARKAZ</strong><button type="button" aria-label="Close menu">×</button></div><nav><a href="/dashboard.html">⌂　Dashboard</a><a href="/students.html" data-shell-permission="students" hidden>♙　Students</a><a href="/classes.html" data-shell-non-management>▤　Classes</a><a href="/subjects/" data-shell-non-management>▣　Subjects</a><a href="/classes.html" data-shell-non-management>▥　Results</a><a href="/attendance.html" data-shell-permission="attendance" hidden>▦　Attendance</a><a href="/school-fees.html" data-shell-permission="school_fees" hidden>▤　School Fees</a><a href="/print-results.html" data-shell-non-management>▧　Print Results</a><a href="/settings.html" data-shell-settings-link>⚙　Settings</a></nav>';
document.body.prepend(overlay, sidebar);

const menu = header.querySelector(".markaz-shell-menu");
const profile = header.querySelector(".markaz-shell-profile");
const profileMenu = header.querySelector(".markaz-shell-profile-menu");
const close = () => {
  sidebar.classList.remove("open");
  overlay.hidden = true;
  menu.setAttribute("aria-expanded", "false");
};
menu.addEventListener("click", () => {
  const open = !sidebar.classList.contains("open");
  sidebar.classList.toggle("open", open);
  overlay.hidden = !open;
  menu.setAttribute("aria-expanded", String(open));
});
sidebar.querySelector(".markaz-shell-sidebar-head button").addEventListener("click", close);
overlay.addEventListener("click", close);
sidebar.querySelectorAll("a").forEach(link => link.addEventListener("click", close));
profile.addEventListener("click", () => {
  profileMenu.hidden = !profileMenu.hidden;
  profile.setAttribute("aria-expanded", String(!profileMenu.hidden));
});
document.addEventListener("click", event => {
  if (!header.contains(event.target)) {
    profileMenu.hidden = true;
    profile.setAttribute("aria-expanded", "false");
  }
});

const shellAccountPromise = window.markazSettingsAccountPromise
  ? window.markazSettingsAccountPromise
  : fetch("/api/auth/me.php", {credentials: "same-origin", cache: "no-store", headers: {Accept: "application/json"}})
      .then(async response => ({ok:response.ok, data:await response.json()}));

shellAccountPromise
  .then(({ok, data}) => {
    if (!ok || !data?.success || !data.admin) {
      if (isStudentPortal) {
        header.remove();
        overlay.remove();
        sidebar.remove();
        return;
      }
      location.replace(loginPathForRole(currentRole));
      return;
    }
    const account = data.admin;
    currentRole = String(account.role || "").toLowerCase();
    const permissions = account.management_permissions || {};
    const name = account.name || "Administrator";
    const initials = name.trim().split(/\s+/).map(part => part[0]).slice(0, 2).join("").toUpperCase();
    header.querySelector("#markazShellName").textContent = name;
    header.querySelector("#markazShellMenuName").textContent = name;
    header.querySelector("#markazShellInitials").textContent = initials || "AD";
    header.querySelector("#markazShellRole").textContent = currentRole.replaceAll("_", " ");
    header.querySelector("#markazShellEmail").textContent = account.email || "";

    const settingsLink = header.querySelector("[data-shell-settings]");
    const settingsNav = sidebar.querySelector("[data-shell-settings-link]");
    const manager = currentRole === "management";
    const settingsAllowed = ["admin", "exam_officer", "management", "teacher"].includes(currentRole);
    if (settingsLink) settingsLink.hidden = !settingsAllowed;
    if (settingsNav) settingsNav.hidden = !settingsAllowed;

    if (manager) {
      sidebar.querySelectorAll("[data-shell-non-management]").forEach(link => link.hidden = true);
      sidebar.querySelectorAll("[data-shell-permission]").forEach(link => {
        link.hidden = permissions[link.dataset.shellPermission] !== true;
      });
      const required = {
        "/students.html": permissions.students === true,
        "/attendance.html": permissions.attendance === true,
        "/school-fees.html": permissions.school_fees === true,
        "/settings.html": true
      };
      const allowed = required[path] ?? false;
      if (!allowed) {
        location.replace("/dashboard.html");
        return;
      }
    } else if (currentRole === "teacher") {
      sidebar.querySelectorAll("[data-shell-permission]").forEach(link => link.hidden = false);
      const attendanceLink = sidebar.querySelector('[href="/attendance.html"]');
      if (attendanceLink) attendanceLink.hidden = account.can_manage_attendance !== true;
    } else {
      sidebar.querySelectorAll("[data-shell-permission]").forEach(link => link.hidden = false);
    }

    if (account.profile_image) {
      const avatar = header.querySelector(".admin-profile-avatar");
      avatar.replaceChildren();
      const image = document.createElement("img");
      image.src = "/" + String(account.profile_image).replace(/^\/+/, "");
      image.alt = name;
      avatar.appendChild(image);
    }
  })
  .catch(error => console.error("Management profile load failed:", error));

header.querySelector("#markazShellLogout").addEventListener("click", async () => {
  const button = header.querySelector("#markazShellLogout");
  button.disabled = true;
  button.textContent = "Logging out...";
  try {
    const response = await fetch("/api/auth/logout.php", {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      headers: {Accept: "application/json"}
    });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || "Logout failed.");
    currentRole = String(data.role || currentRole || "").toLowerCase();
    location.replace(loginPathForRole(currentRole));
  } catch (error) {
    button.disabled = false;
    button.textContent = "Logout";
    alert(error.message || "Unable to log out.");
  }
});

const activityScript = document.createElement("script");
activityScript.src = "/js/device-activity.js?v=20261109-device-activity";
activityScript.dataset.activityRole = "auto";
activityScript.defer = true;
document.head.appendChild(activityScript);
const aiScript = document.createElement("script");
aiScript.src = "/js/markaz-ai.js?v=20261116-ai1";
aiScript.defer = true;
document.head.appendChild(aiScript);
document.body.classList.add("markaz-management-shell-active");
})();