(async () => {
  "use strict";

  const page = document.querySelector(".settings-page");
  if (!page || document.getElementById("settingsCategoryNav")) return;

  const findSections = () => {
    const profile = page.querySelector(
      ':scope > section.settings-card:not(.academic-period-settings-card):not(.result-footer-settings-card):not(.memorization-settings-card):not(.markaz-account-management-card):not(.markaz-role-permissions-card):not(.markaz-locked-accounts-card):not(.markaz-account-devices-card):not(#websiteImageManager):not(#markazSocialSettings):not(#markazSupportSettings)'
    );

    return [
      {
        id: "profile",
        label: "Profile Information",
        icon: "fa-user-circle",
        description: "Manage your profile information and account details.",
        elements: profile ? [profile] : []
      },
      {
        id: "account-management",
        label: "Account Management",
        icon: "fa-users-cog",
        description: "Create and manage staff and student accounts, teacher assignments, and individual permissions.",
        elements: [page.querySelector(".markaz-account-management-card")].filter(Boolean)
      },
      {
        id: "role-access",
        label: "Role Access & Result Permissions",
        icon: "fa-user-shield",
        description: "Configure portal access and role-wide result printing and export permissions.",
        elements: [page.querySelector(".markaz-role-permissions-card")].filter(Boolean)
      },
      {
        id: "locked-accounts",
        label: "Locked Accounts",
        icon: "fa-lock",
        description: "Review and manage accounts locked by login security controls.",
        elements: [page.querySelector(".markaz-locked-accounts-card")].filter(Boolean)
      },
      {
        id: "account-devices",
        label: "Account Devices",
        icon: "fa-laptop",
        description: "Review staff and student device activity, usage time and recognised device access.",
        elements: [page.querySelector("#markazAccountDevices")].filter(Boolean)
      },
      {
        id: "result-release",
        label: "Academic Results Release",
        icon: "fa-toggle-on",
        description: "Control when students can view results for each academic term.",
        elements: [page.querySelector("#markazResultRelease")].filter(Boolean)
      },
      {
        id: "academic-archive",
        label: "Academic Records & Term Archive",
        icon: "fa-box-archive",
        description: "Finalize completed terms, protect historical results, and review released academic records.",
        elements: [page.querySelector(".academic-archive-settings-card")].filter(Boolean)
      },
      {
        id: "academic",
        label: "Academic Settings",
        icon: "fa-calendar-alt",
        description: "Set the current academic session, term, and next-term resumption date.",
        elements: [page.querySelector(".academic-period-settings-card")].filter(Boolean)
      },
      {
        id: "results",
        label: "Results & Reports",
        icon: "fa-file-signature",
        description: "Manage the signatures and report-date information printed on results.",
        elements: [page.querySelector(".result-footer-settings-card")].filter(Boolean)
      },
      {
        id: "result-print-threshold",
        label: "Print Eligibility Threshold",
        icon: "fa-clipboard-check",
        description: "Set the minimum result-entry completion required before a student's result can be printed.",
        elements: [page.querySelector(".result-print-threshold-card")].filter(Boolean)
      },
      {
        id: "memorization",
        label: "Qur’an Memorization",
        icon: "fa-book-open",
        description: "Manage the existing Qur’an memorization settings and class defaults.",
        elements: [page.querySelector(".memorization-settings-card")].filter(Boolean)
      },
      {
        id: "images",
        label: "Website & Images",
        icon: "fa-images",
        description: "Manage the website images available through the existing image manager.",
        elements: [page.querySelector("#websiteImageManager")].filter(Boolean)
      },
      {
        id: "social",
        label: "Website & Social Links",
        icon: "fa-share-alt",
        description: "Update the official social-media profile URLs shown on the public website.",
        elements: [page.querySelector("#markazSocialSettings")].filter(Boolean)
      },
      {
        id: "support",
        label: "System Support & Emergency Contact",
        icon: "fa-life-ring",
        description: "Manage the WhatsApp contact for technical help and system-management emergencies.",
        elements: [page.querySelector("#markazSupportSettings")].filter(Boolean)
      }
    ].filter(category => category.elements.length);
  };

  const allCategories = findSections();
  if (!allCategories.length) return;

  let currentRole = "admin";
  try {
    const response = await fetch("./api/auth/me.php", {
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json" }
    });
    const account = await response.json();
    if (!response.ok || !account.success || !account.admin) {
      window.location.replace("login.html");
      return;
    }
    currentRole = account.admin.role;
    if (!["admin", "exam_officer"].includes(currentRole)) { window.location.replace("dashboard.html"); return; }
  } catch {
    window.location.replace("login.html");
    return;
  }

  const isExamOfficer = currentRole === "exam_officer";
  const examOfficerCategoryIds = new Set([
    "profile", "result-release", "academic-archive", "academic", "results", "memorization"
  ]);
  const categories = isExamOfficer
    ? allCategories.filter(category => examOfficerCategoryIds.has(category.id))
    : allCategories;

  if (isExamOfficer) {
    page.querySelectorAll(":scope > section.settings-card").forEach(section => {
      section.hidden = true;
    });
    const resetCard = page.querySelector(".academic-reset-settings-card");
    if (resetCard) resetCard.hidden = true;
  }

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

    categories.forEach(category => {
      const isActive = category.id === selected.id;

      category.elements.forEach(element => {
        element.hidden = !isActive;
      });

      const button = buttons.get(category.id);
      if (button) button.setAttribute("aria-pressed", String(isActive));
    });

    heading.textContent = selected.label;
    description.textContent = selected.description;
  }

  categories.forEach(category => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "settings-category-button";
    button.setAttribute("aria-pressed", "false");
    button.innerHTML = `<i class="fas ${category.icon}" aria-hidden="true"></i><span></span>`;
    button.querySelector("span").textContent = category.label;
    button.addEventListener("click", () => activate(category.id));
    nav.appendChild(button);
    buttons.set(category.id, button);
  });

  activate("profile");
  if (isExamOfficer) {
    const releaseLabel = page.querySelector("#markazResultRelease .settings-label");
    if (releaseLabel) releaseLabel.textContent = "ACADEMIC RESULTS";
    const categoryLabels = { "academic-archive": "Academic & Term Archive", results: "Result & Reports", memorization: "Core Memorization" };
    categories.forEach(category => { if (categoryLabels[category.id]) { category.label = categoryLabels[category.id]; const button = buttons.get(category.id); if (button) button.querySelector("span").textContent = category.label; } });
  }
  document.documentElement.classList.remove("settings-page-pending");
})();
