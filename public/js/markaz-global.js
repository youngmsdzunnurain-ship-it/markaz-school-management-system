document.addEventListener("DOMContentLoaded", async () => {
    const page = location.pathname.split("/").pop() || "home.html";
    const publicPages = ["home.html", "about.html", "academics.html", "admissions.html"];

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
