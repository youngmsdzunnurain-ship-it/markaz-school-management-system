/* Shared login footer and role-aware WhatsApp support */
(() => {
  "use strict";

  const card = document.querySelector(".login-card, .role-login-card");
  const page = document.querySelector(".login-page, .role-login-page");
  if (!card || !page) return;

  document.querySelectorAll(".markaz-shared-login-footer").forEach(el => el.remove());
  card.querySelectorAll(".bottom-home, .back-home, .role-footer").forEach(el => el.remove());

  const heading = card.querySelector(".login-heading h2, .role-heading h2, h2");
  const title = `${document.title || ""} ${heading?.textContent || ""}`.toLowerCase();

  let role = "User";
  if (title.includes("exam officer")) role = "Exam Officer";
  else if (title.includes("management")) role = "Management";
  else if (title.includes("teacher")) role = "Teacher";
  else if (title.includes("student")) role = "Student";
  else if (title.includes("admin")) role = "Admin";

  // Home link belongs inside the card, directly after the login form.
  const home = document.createElement("a");
  home.className = "back-home";
  home.href = "home.html";
  home.innerHTML = '<i class="fa-solid fa-house" aria-hidden="true"></i><span>Back to Home</span>';
  home.style.cssText = "display:flex;justify-content:center;align-items:center;gap:7px;margin:16px auto 0;text-align:center;text-decoration:none;color:#176b45;font-size:14px;font-weight:600;";

  const form = card.querySelector("form");
  if (form) form.insertAdjacentElement("afterend", home);
  else card.appendChild(home);

  const footer = document.createElement("footer");
  footer.className = "markaz-shared-login-footer";

  const supportLine = document.createElement("div");
  supportLine.className = "support-line";
  supportLine.append(document.createTextNode("Having issues? Contact "));

  const support = document.createElement("a");
  support.className = "support-whatsapp";
  support.target = "_blank";
  support.rel = "noopener noreferrer";
  support.innerHTML = '<i class="fa-brands fa-whatsapp" aria-hidden="true"></i><span>MARKAZ Support</span>';
  supportLine.appendChild(support);

  const copyright = document.createElement("small");
  copyright.className = "markaz-login-copyright";
  copyright.textContent = "© 2026 MARKAZ. All Rights Reserved.";

  const fallback = "2347048657955";
  const message = `Hello MARKAZ Support, I am trying to log in as ${role} and I am having trouble accessing my account. Please help me.`;

  const setSupportNumber = value => {
    let digits = String(value || "").replace(/\D/g, "");
    if (digits.startsWith("0")) digits = "234" + digits.slice(1);
    else if (digits.startsWith("2340")) digits = "234" + digits.slice(4);
    if (digits.length < 10 || digits.length > 15) digits = fallback;
    support.href = `https://wa.me/${digits}?text=${encodeURIComponent(message)}`;
  };

  setSupportNumber(fallback);
  fetch("api/settings/support-contact.php", { credentials: "same-origin" })
    .then(response => response.ok ? response.json() : null)
    .then(data => {
      if (data?.success && data?.whatsapp_number) setSupportNumber(data.whatsapp_number);
    })
    .catch(() => {});

  footer.append(supportLine, copyright);
  page.appendChild(footer);
})();