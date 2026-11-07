document.addEventListener("DOMContentLoaded", async () => {
    const page = location.pathname.split("/").pop() || "home.html";
    const publicPages = ["home.html", "about.html", "academics.html", "admissions.html", "facilities.html", "gallery.html", "faq.html", "contact.html"];

    if (publicPages.includes(page)) {
        try {
            const response = await fetch("home.html", { cache: "no-store" });
            if (!response.ok) throw new Error("Homepage footer source unavailable.");

            const html = await response.text();
            const parsed = new DOMParser().parseFromString(html, "text/html");
            const sharedFooter = parsed.querySelector("footer.footer");

            if (!sharedFooter) throw new Error("Shared footer not found.");

            const footer = document.importNode(sharedFooter, true);

            footer.querySelectorAll(".footer-contact span").forEach(icon => {
                icon.remove();
            });

            const address = footer.querySelector(".footer-address");
            if (address) {
                address.textContent = address.textContent.replace(/^📍\s*/, "");
            }

            const existing = document.querySelector("footer");
            if (existing) existing.replaceWith(footer);
            else document.body.appendChild(footer);

            const year = footer.querySelector("#currentYear");
            if (year) year.textContent = new Date().getFullYear();

            // Load the same saved social-link values used by the Settings form.
            const socialResponse = await fetch("api/settings/social-links.php", {
                credentials: "same-origin",
                cache: "no-store"
            });
            if (!socialResponse.ok) throw new Error("Saved social links could not be loaded.");
            const socialData = await socialResponse.json();
            if (socialData.success && socialData.links) {
                const platforms = ["facebook", "instagram", "x", "youtube", "tiktok"];
                footer.querySelectorAll(".footer-socials a[aria-label]").forEach(link => {
                    const label = (link.getAttribute("aria-label") || "").toLowerCase();
                    const platform = platforms.find(item => label.startsWith(item));
                    if (!platform) return;
                    const url = String(socialData.links[platform] || "").trim();
                    if (!url) {
                        link.hidden = true;
                        return;
                    }
                    link.href = url;
                    link.target = "_blank";
                    link.rel = "noopener noreferrer";
                    link.hidden = false;
                    link.removeAttribute("aria-label");
                    link.setAttribute("aria-label", platform === "x" ? "X" : platform[0].toUpperCase() + platform.slice(1));
                });
            }
        } catch (error) {
            console.error("Could not load the shared public footer:", error);
        }
        return;
    }

    const isPrintReport =
        document.body.classList.contains("student-result-page") ||
        document.querySelector(".report-card-footer, .report-footer");

    if (isPrintReport || document.querySelector("footer, .markaz-global-footer")) {
        return;
    }

    const footer = document.createElement("footer");
    footer.className = "markaz-global-footer";
    footer.innerHTML = `
        <span class="footer-brand">MARKAZ</span>
        <span class="footer-school">
            Centre for Learning and Memorization of the Glorious Qur’an
        </span>
        <span class="footer-copyright">
            © ${new Date().getFullYear()} MARKAZ. All Rights Reserved.
        </span>
    `;
    document.body.appendChild(footer);
});
