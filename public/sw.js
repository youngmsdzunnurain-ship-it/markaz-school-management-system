"use strict";

const CACHE_VERSION = "markaz-public-v1";
const PAGE_CACHE = CACHE_VERSION + "-pages";
const ASSET_CACHE = CACHE_VERSION + "-assets";

const PUBLIC_PAGES = new Set([
    "./home.html", "./about.html", "./academics.html", "./admissions.html",
    "./facilities.html", "./gallery.html", "./faq.html", "./contact.html",
    "./offline.html"
]);

const CORE_ASSETS = [
    "./css/home.css",
    "./css/public-pages.css",
    "./css/public-interactions.css",
    "./css/website-image-polish.css",
    "./css/public-design-refinement.css",
    "./css/about-card-polish.css",
    "./css/gallery-alignment.css",
    "./css/public-resilience.css",
    "./js/home.js",
    "./js/markaz-global.js",
    "./js/website-image-overrides.js",
    "./js/social-link-overrides.js",
    "./js/public-resilience.js",
    "./assets/images/markaz-logo.jpg"
];

function isApprovedPublicPage(url) {
    if (url.origin !== self.location.origin) return false;
    const path = url.pathname.split("/").filter(Boolean).pop() || "home.html";
    return PUBLIC_PAGES.has("./" + path);
}

function isApprovedPublicAsset(url) {
    if (url.origin !== self.location.origin) return false;
    if (url.pathname.includes("/api/")) return false;
    if (/\/(?:dashboard|login|student|teacher|management|exam-officer|settings|attendance|print-results)/i.test(url.pathname)) return false;

    return /\/css\/[^/]+\.css$/i.test(url.pathname) ||
        /\/js\/(?:home|markaz-global|website-image-overrides|social-link-overrides|public-resilience)\.js$/i.test(url.pathname) ||
        /\/assets\/images\/(?:[a-z0-9._-]+\/)*[a-z0-9._-]+\.(?:avif|webp|png|jpe?g|gif|svg)$/i.test(url.pathname);
}

async function cacheResponse(cacheName, request, response) {
    if (response && response.ok && response.type === "basic") {
        const cache = await caches.open(cacheName);
        await cache.put(request, response.clone());
    }
}

async function fetchWithTimeout(request, timeoutMs = 12000) {
    let timeoutId;
    const timeout = new Promise((_, reject) => {
        timeoutId = setTimeout(() => reject(new Error("Public request timed out")), timeoutMs);
    });
    try {
        return await Promise.race([fetch(request), timeout]);
    } finally {
        clearTimeout(timeoutId);
    }
}

self.addEventListener("install", event => {
    event.waitUntil((async () => {
        const pages = await caches.open(PAGE_CACHE);
        await pages.addAll(Array.from(PUBLIC_PAGES));
        const assets = await caches.open(ASSET_CACHE);
        await assets.addAll(CORE_ASSETS);
        await self.skipWaiting();
    })());
});

self.addEventListener("activate", event => {
    event.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names.filter(name => name.startsWith("markaz-public-") &&
            name !== PAGE_CACHE && name !== ASSET_CACHE).map(name => caches.delete(name)));
        await self.clients.claim();
    })());
});

self.addEventListener("fetch", event => {
    const request = event.request;
    if (request.method !== "GET" ||
        request.headers.has("authorization") ||
        request.headers.has("range")) return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin || url.pathname.includes("/api/")) return;

    if (request.mode === "navigate") {
        if (!isApprovedPublicPage(url)) return;
        event.respondWith((async () => {
            const cache = await caches.open(PAGE_CACHE);
            try {
                const response = await fetchWithTimeout(request);
                await cacheResponse(PAGE_CACHE, request, response);
                return response;
            } catch (error) {
                const cached = await cache.match(request);
                if (cached) return cached;
                const offline = await cache.match(new URL("./offline.html", self.registration.scope).href);
                if (offline) return offline;
                return new Response("You are offline. Please reconnect and try again.", {
                    status: 503,
                    headers: { "Content-Type": "text/plain; charset=utf-8" }
                });
            }
        })());
        return;
    }

    /* The shared footer loader fetches home.html as a normal same-origin request. */
    if (isApprovedPublicPage(url)) {
        const refresh = fetch(request).then(response => {
            if (response.ok && response.type === "basic") {
                return cacheResponse(PAGE_CACHE, request, response);
            }
            return null;
        }).catch(() => null);
        event.waitUntil(refresh);
        event.respondWith((async () => {
            const cache = await caches.open(PAGE_CACHE);
            return (await cache.match(request)) || (await refresh) ||
                new Response("", { status: 503, statusText: "Public page unavailable offline" });
        })());
        return;
    }

    if (!isApprovedPublicAsset(url)) return;

    event.respondWith((async () => {
        const cache = await caches.open(ASSET_CACHE);
        const cached = await cache.match(request);
        const network = fetch(request).then(response => {
            if (response.ok && response.type === "basic") {
                cache.put(request, response.clone());
            }
            return response;
        }).catch(() => null);

        if (cached) {
            event.waitUntil(network);
            return cached;
        }

        const response = await network;
        if (response) return response;
        return new Response("", { status: 504, statusText: "Asset unavailable offline" });
    })());
});
