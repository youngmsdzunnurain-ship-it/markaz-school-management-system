"use strict";

const CACHE_VERSION = "markaz-public-v3";
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
    "./css/public-animations.css",
    "./js/home.js",
    "./js/markaz-global.js",
    "./js/website-image-overrides.js",
    "./js/social-link-overrides.js",
    "./js/public-resilience.js",
    "./js/public-animations.js",
    "./assets/images/markaz-logo.jpg"
];

function getScopeRelativePath(url) {
    const scopePath = new URL(self.registration.scope).pathname;
    if (!url.pathname.startsWith(scopePath)) return null;
    return url.pathname.slice(scopePath.length);
}

function isApprovedPublicPage(url) {
    if (url.origin !== self.location.origin) return false;
    const relativePath = getScopeRelativePath(url);
    if (relativePath === null) return false;
    return relativePath !== "" && PUBLIC_PAGES.has("./" + relativePath);
}

function isApprovedPublicAsset(url) {
    if (url.origin !== self.location.origin || url.pathname.includes("/api/")) return false;
    const relativePath = getScopeRelativePath(url);
    if (!relativePath) return false;

    if (CORE_ASSETS.includes("./" + relativePath)) return true;
    return /^assets\/images\/(?:[a-z0-9._-]+\/)*[a-z0-9._-]+\.(?:avif|webp|png|jpe?g|gif|svg)$/i.test(relativePath);
}

async function notifyPublicClients(state, message) {
    const clients = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
    clients.forEach(client => client.postMessage({
        type: "MARKAZ_NETWORK_STATUS",
        state,
        message
    }));
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
    // Do not block activation on downloading every public page and asset.
    event.waitUntil(self.skipWaiting());
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

    // Let document navigations use the browser's normal fast navigation path.
    // The worker only caches public assets and never delays page transitions.
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

    const assetCachePromise = caches.open(ASSET_CACHE);
    const network = assetCachePromise.then(cache => fetch(request).then(response => {
        if (response.ok && response.type === "basic") {
            cache.put(request, response.clone());
        }
        return response;
    })).catch(async () => {
        await notifyPublicClients("offline", "A network request failed. Showing saved content where available.");
        return null;
    });

    /* Serve a cached asset immediately, while refreshing it in the background. */
    event.waitUntil(network);
    event.respondWith((async () => {
        const cache = await assetCachePromise;
        const cached = await cache.match(request);
        if (cached) return cached;

        const response = await network;
        if (response) return response;
        return new Response("", { status: 504, statusText: "Asset unavailable offline" });
    })());
});
