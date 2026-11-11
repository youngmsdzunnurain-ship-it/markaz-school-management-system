(async () => {
  "use strict";

  document.documentElement.classList.add("settings-page-pending");
  const page = document.querySelector(".settings-page");
  if (!page || document.getElementById("settingsCategoryNav")) return;

  try {
    const accountResult = window.markazSettingsAccountPromise
      ? await window.markazSettingsAccountPromise
      : await fetch("./api/auth/me.php", {
          credentials: "same-origin",
          cache: "no-store",
          headers: { Accept: "application/json" }
        }).then(async response => ({ok:response.ok, data:await response.json()}));
    const account = accountResult.data;
    if (!accountResult.ok || !account?.success || !account.admin) {
      window.location.replace("login.html");
      return;
    }

    const role = account.admin.role;
    if (!["admin", "exam_officer", "management", "teacher"].includes(role)) {
      window.location.replace("dashboard.html");
      return;
    }

    const profile = page.querySelector(
      ':scope > section.settings-card:not(.academic-period-settings-card):not(.result-footer-settings-card):not(.memorization-settings-card):not(.markaz-account-management-card):not(.markaz-role-permissions-card):not(.markaz-locked-accounts-card):not(.markaz-account-devices-card):not(#websiteImageManager):not(#markazSocialSettings):not(#markazSupportSettings)'
    );
    const allCategories = [
      { id:"profile", label:"Profile Information", icon:"fa-user-circle", description:"Manage your profile information and account details.", elements:profile?[profile]:[] },
      { id:"account-management", label:"Account Management", icon:"fa-users-cog", description:"Create and manage staff and student accounts, teacher assignments, and individual permissions.", elements:[page.querySelector(".markaz-account-management-card")].filter(Boolean) },
      { id:"role-access", label:"Role Access & Result Permissions", icon:"fa-user-shield", description:"Configure portal access and role-wide result printing and export permissions.", elements:[page.querySelector(".markaz-role-permissions-card")].filter(Boolean) },
      { id:"locked-accounts", label:"Locked Accounts", icon:"fa-lock", description:"Review and manage accounts locked by login security controls.", elements:[page.querySelector(".markaz-locked-accounts-card")].filter(Boolean) },
      { id:"account-devices", label:"Account Device Activity", icon:"fa-laptop", description:"Review staff and student device activity, usage time and recognised device access.", elements:[page.querySelector("#markazAccountDevices")].filter(Boolean) },
      { id:"result-release", label:"Student Result Release Controls", icon:"fa-toggle-on", description:"Control when students can view results for each academic term.", elements:[page.querySelector("#markazResultRelease")].filter(Boolean) },
      { id:"academic-archive", label:"Academic Records & Term Archive", icon:"fa-box-archive", description:"Finalize completed terms, protect historical results, and review released academic records.", elements:[page.querySelector(".academic-archive-settings-card")].filter(Boolean) },
      { id:"academic", label:"Academic Session & Term Settings", icon:"fa-calendar-alt", description:"Choose the active academic session and term, and set the next-term resumption date.", elements:[page.querySelector(".academic-period-settings-card")].filter(Boolean) },
      { id:"results", label:"Result Footer Signatures & Date", icon:"fa-file-signature", description:"Manage the class teacher and director signatures and report date printed on student results.", elements:[page.querySelector(".result-footer-settings-card")].filter(Boolean) },
      { id:"result-print-threshold", label:"Result Print Eligibility", icon:"fa-clipboard-check", description:"Set the minimum result-entry completion required before a student's result can be printed.", elements:[page.querySelector(".result-print-threshold-card")].filter(Boolean) },
      { id:"memorization", label:"Arabic Class Memorization Settings", icon:"fa-book-open", description:"Set memorization portion defaults for each Arabic class.", elements:[page.querySelector(".memorization-settings-card")].filter(Boolean) },
      { id:"images", label:"Website Image Manager", icon:"fa-images", description:"Manage the website images available through the existing image manager.", elements:[page.querySelector("#websiteImageManager")].filter(Boolean) },
      { id:"social", label:"Website Social Media Links", icon:"fa-share-alt", description:"Update the official social-media profile links displayed on the public website.", elements:[page.querySelector("#markazSocialSettings")].filter(Boolean) },
      { id:"support", label:"Emergency Contact & System Support", icon:"fa-life-ring", description:"Manage the WhatsApp contact for technical support and system emergencies.", elements:[page.querySelector("#markazSupportSettings")].filter(Boolean) }
    ].filter(category => category.elements.length);

    const allowedIds = role === "admin"
      ? new Set(allCategories.map(category => category.id))
      : role === "exam_officer"
        ? new Set(["profile", "result-release", "academic-archive", "academic", "results", "memorization"])
        : (role === "management" || role === "teacher")
          ? new Set(["profile"])
          : new Set();
    const categories = allCategories.filter(category => allowedIds.has(category.id));
    allCategories.forEach(category => category.elements.forEach(element => { element.hidden = true; }));


    const nav = document.createElement("nav");
    nav.id = "settingsCategoryNav";
    nav.className = "settings-category-nav";
    nav.setAttribute("aria-label", "Settings categories");
    const header = page.querySelector(".settings-header");
    if (header) header.insertAdjacentElement("afterend", nav);
    else page.prepend(nav);

    const heading = document.createElement("h2");
    heading.className = "settings-category-heading";
    heading.id = "settingsActiveCategoryHeading";
    nav.insertAdjacentElement("beforebegin", heading);
    const description = document.createElement("p");
    description.className = "settings-category-description";
    description.id = "settingsActiveCategoryDescription";
    heading.insertAdjacentElement("afterend", description);

    const buttons = new Map();
    function activate(categoryId) {
      const selected = categories.find(category => category.id === categoryId) || categories[0];
      if (!selected) return;
      categories.forEach(category => {
        const active = category.id === selected.id;
        category.elements.forEach(element => { element.hidden = !active; });
        buttons.get(category.id)?.setAttribute("aria-pressed", String(active));
      });
      heading.textContent = selected.label;
      description.textContent = selected.description;
    }

    categories.forEach(category => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "settings-category-button";
      button.setAttribute("aria-pressed", "false");
      button.innerHTML = '<i class="fas ' + category.icon + '" aria-hidden="true"></i><span></span>';
      button.querySelector("span").textContent = category.label;
      button.addEventListener("click", () => activate(category.id));
      nav.appendChild(button);
      buttons.set(category.id, button);
    });

    activate("profile");
    document.documentElement.classList.remove("settings-page-pending");
  } catch (_) {
    window.location.replace("login.html");
  }
})();
