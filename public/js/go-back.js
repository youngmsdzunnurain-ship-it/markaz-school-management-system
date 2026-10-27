
(function () {
    "use strict";

    function sameSiteReferrerExists() {
        try {
            if (!document.referrer) return false;
            const previous = new URL(document.referrer);
            return previous.origin === window.location.origin &&
                   previous.href !== window.location.href;
        } catch (_) {
            return false;
        }
    }

    window.markazGoBack = function (fallback) {
        if (sameSiteReferrerExists() && window.history.length > 1) {
            window.history.back();
            return;
        }
        window.location.href = fallback || "/dashboard.html";
    };

    function addBackButton() {
        const path = window.location.pathname;
        let header = null;
        let fallback = "/dashboard.html";

        if (path.endsWith("/classes.html")) {
            header = document.querySelector(".classes-topbar");
        } else if (path.endsWith("/students.html")) {
            header = document.querySelector(".students-topbar");
        } else if (path.endsWith("/subjects/index.html")) {
            header = document.querySelector(".subjects-page > header");
        }

        if (!header) return;

        const content = header.querySelector(":scope > div");
        if (!content || header.querySelector("a[data-go-back]")) return;

        const link = document.createElement("a");
        link.href = fallback;
        link.className = "markaz-go-back";
        link.setAttribute("data-go-back", "");
        link.textContent = "← Go Back";
        content.insertBefore(link, content.firstChild);
    }

    document.addEventListener("click", function (event) {
        const link = event.target.closest("a[data-go-back]");
        if (!link) return;

        event.preventDefault();
        window.markazGoBack(link.getAttribute("href") || "/dashboard.html");
    });

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", addBackButton);
    } else {
        addBackButton();
    }
})();
