(() => {
    "use strict";

    const PUBLIC_PAGES = new Set([
        "home.html", "about.html", "academics.html", "admissions.html",
        "facilities.html", "gallery.html", "faq.html", "contact.html"
    ]);

    const pageName = location.pathname.split("/").filter(Boolean).pop() || "home.html";
    if (!PUBLIC_PAGES.has(pageName)) return;

    const root = document.documentElement;
    let statusTimer = null;
    let statusNode = null;

    function getStatusNode() {
        if (statusNode) return statusNode;
        statusNode = document.createElement("div");
        statusNode.className = "markaz-network-status";
        statusNode.setAttribute("role", "status");
        statusNode.setAttribute("aria-live", "polite");
        statusNode.innerHTML = '<span class="markaz-network-status__dot" aria-hidden="true"></span><span class="markaz-network-status__message"></span>';
        document.body.appendChild(statusNode);
        return statusNode;
    }

    function showStatus(state, message, duration = 0) {
        const node = getStatusNode();
        node.dataset.state = state;
        node.querySelector(".markaz-network-status__message").textContent = message;
        node.classList.add("is-visible");
        window.clearTimeout(statusTimer);
        if (duration > 0) {
            statusTimer = window.setTimeout(() => node.classList.remove("is-visible"), duration);
        }
    }

    function hideStatus(delay = 0) {
        window.clearTimeout(statusTimer);
        statusTimer = window.setTimeout(() => {
            if (statusNode) statusNode.classList.remove("is-visible");
        }, delay);
    }

    function finishPageSkeleton() {
        root.classList.add("markaz-page-ready");
        const skeleton = document.getElementById("markaz-page-loading");
        if (skeleton) {
            skeleton.setAttribute("aria-hidden", "true");
            window.setTimeout(() => skeleton.remove(), 260);
        }
    }

    function setupImages() {
        const images = Array.from(document.images);
        images.forEach((img, index) => {
            if (!img.hasAttribute("loading") && index > 1) img.loading = "lazy";
            if (!img.hasAttribute("decoding")) img.decoding = "async";

            if (img.complete) {
                img.classList.add(img.naturalWidth > 0 ? "markaz-image-loaded" : "markaz-image-error");
                return;
            }

            img.classList.add("markaz-image-loading");
            img.addEventListener("load", () => {
                img.classList.remove("markaz-image-loading", "markaz-image-error");
                img.classList.add("markaz-image-loaded");
            }, { once: true });
            img.addEventListener("error", () => {
                img.classList.remove("markaz-image-loading", "markaz-image-loaded");
                img.classList.add("markaz-image-error");
            }, { once: true });
        });
    }

    function retryFailedImages() {
        document.querySelectorAll("img.markaz-image-error:not([srcset])").forEach(img => {
            const source = img.getAttribute("src");
            if (!source) return;
            img.classList.remove("markaz-image-error", "markaz-image-loaded");
            img.classList.add("markaz-image-loading");
            img.addEventListener("load", () => {
                img.classList.remove("markaz-image-loading", "markaz-image-error");
                img.classList.add("markaz-image-loaded");
            }, { once: true });
            img.addEventListener("error", () => {
                img.classList.remove("markaz-image-loading", "markaz-image-loaded");
                img.classList.add("markaz-image-error");
            }, { once: true });
            img.src = source;
        });
    }

    function updateConnectionStatus() {
        if (!navigator.onLine) {
            showStatus("offline", "You're offline. Some content may be unavailable; we'll reconnect automatically.");
            return;
        }

        const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        const slow = connection && (
            connection.saveData ||
            ["slow-2g", "2g"].includes(connection.effectiveType) ||
            (Number(connection.downlink) > 0 && Number(connection.downlink) < 0.7)
        );

        if (slow) {
            showStatus("slow", "Your connection is slow. MARKAZ is loading content carefully.");
        } else if (statusNode && statusNode.dataset.state === "offline") {
            showStatus("online", "Connection restored. You are back online.", 3500);
        } else {
            hideStatus(500);
        }
    }

    window.addEventListener("offline", () => {
        showStatus("offline", "You're offline. Previously loaded content remains on this page.");
    });

    window.addEventListener("online", () => {
        retryFailedImages();
        showStatus("online", "Connection restored. You are back online.", 4000);
        window.setTimeout(updateConnectionStatus, 4100);
    });

    const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    if (connection && typeof connection.addEventListener === "function") {
        connection.addEventListener("change", updateConnectionStatus);
    }

    function registerOfflineSupport() {
        if (!("serviceWorker" in navigator)) return;
        if (location.protocol !== "https:" && location.hostname !== "localhost" && location.hostname !== "127.0.0.1") return;

        navigator.serviceWorker.register("./sw.js", { scope: "./" }).catch(error => {
            console.warn("MARKAZ public offline support could not be registered:", error);
        });
    }

    function initialize() {
        setupImages();
        updateConnectionStatus();
        registerOfflineSupport();
        finishPageSkeleton();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    } else {
        initialize();
    }

    window.addEventListener("pageshow", event => {
        if (event.persisted) {
            finishPageSkeleton();
            updateConnectionStatus();
        }
    });
})();
