/*
 * MARKAZ Global UI
 * Adds the standard application footer to pages
 * that do not already have their own report footer.
 */

document.addEventListener("DOMContentLoaded", () => {
    const isPrintReport =
        document.body.classList.contains("student-result-page") ||
        document.querySelector(".report-card-footer") ||
        document.querySelector(".report-footer");

    if (isPrintReport) return;

    if (document.querySelector("footer, .markaz-global-footer")) return;

    const footer = document.createElement("footer");

    footer.className = "markaz-global-footer";

    footer.innerHTML = `
        <span class="footer-brand">MARKAZ</span>
        <span class="footer-school">
            Centre for Learning and Memorization of the Glorious Qur’an
        </span>
        <span class="footer-copyright">
            © 2026 MARKAZ. All Rights Reserved.
        </span>
    `;

    document.body.appendChild(footer);
});
