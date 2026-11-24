/* MARKAZ staff chat: the avatar menu in the top bar.
   Shows the signed-in name and role, opens and closes the menu, and signs out. The language and
   Dark mode switches are added to this menu by theme-toggle.js and i18n.js (they sit above Logout). */
(() => {
    "use strict";
    const $ = id => document.getElementById(id);
    const button = $("chatAccountButton");
    const menu = $("chatAccountMenu");
    if (!button || !menu) return;

    const setOpen = open => {
        menu.hidden = !open;
        button.setAttribute("aria-expanded", String(open));
    };
    button.addEventListener("click", event => {
        event.stopPropagation();
        setOpen(menu.hidden);
    });
    document.addEventListener("click", event => {
        if (!menu.hidden && !menu.contains(event.target) && event.target !== button) setOpen(false);
    });
    document.addEventListener("keydown", event => {
        if (event.key === "Escape" && !menu.hidden) { setOpen(false); button.focus(); }
    });

    const initials = name => String(name || "").trim().split(/\s+/).slice(0, 2).map(part => part[0] || "").join("").toUpperCase() || "M";
    const roleLabel = role => ({ admin: "Admin", exam_officer: "Exam Officer", teacher: "Teacher", management: "Management" })[String(role || "").toLowerCase()] || "Staff";

    fetch("api/auth/me.php", { credentials: "same-origin", cache: "no-store" })
        .then(response => response.json())
        .then(data => {
            const person = data && data.admin;
            if (!person) return;
            const name = person.name || "Staff";
            $("chatAccountName").textContent = name;
            $("chatAccountRole").textContent = roleLabel(person.role);
            $("chatAccountInitials").textContent = initials(name);
            $("chatMenuName").textContent = name;
            $("chatMenuEmail").textContent = person.email || "";
        })
        .catch(() => { /* keep the neutral labels */ });

    const logout = $("topProfileLogoutButton");
    if (logout) {
        logout.addEventListener("click", async () => {
            try { await fetch("api/auth/logout.php", { method: "POST", credentials: "same-origin" }); } catch (error) { /* still leave */ }
            location.replace("login.html");
        });
    }
})();
