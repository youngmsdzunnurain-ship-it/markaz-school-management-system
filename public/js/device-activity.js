(() => {
  "use strict";

  const endpoint = "/api/settings/account-device-activity.php";
  const storageKey = "markaz-device-activity-session";
  let sessionId = "";

  try {
    sessionId = sessionStorage.getItem(storageKey) || "";
    if (!/^[a-f0-9-]{36}$/i.test(sessionId)) {
      sessionId = typeof crypto.randomUUID === "function"
        ? crypto.randomUUID()
        : "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, char => {
            const value = Math.random() * 16 | 0;
            return (char === "x" ? value : (value & 3 | 8)).toString(16);
          });
      sessionStorage.setItem(storageKey, sessionId);
    }
  } catch {
    sessionId = typeof crypto.randomUUID === "function" ? crypto.randomUUID() : "";
  }

  if (!sessionId) return;

  let lastRequestAt = 0;
  let lastInteractionAt = Date.now();
  let paused = document.visibilityState === "hidden";

  function payload(action) {
    return JSON.stringify({ action, session_id: sessionId });
  }

  async function heartbeat() {
    if (paused || document.visibilityState === "hidden") return;
    if (Date.now() - lastInteractionAt > 60000) {
      pause();
      return;
    }
    const now = Date.now();
    if (now - lastRequestAt < 5000) return;
    lastRequestAt = now;
    try {
      await fetch(endpoint, {
        method: "POST",
        credentials: "same-origin",
        cache: "no-store",
        keepalive: true,
        headers: { "Content-Type": "application/json", "Accept": "application/json" },
        body: payload("heartbeat")
      });
    } catch {
      // A temporary network failure is retried by the next visible heartbeat.
    }
  }

  function pause() {
    if (paused) return;
    paused = true;
    const body = new Blob([payload("pause")], { type: "application/json" });
    if (!navigator.sendBeacon || !navigator.sendBeacon(endpoint, body)) {
      fetch(endpoint, {
        method: "POST",
        credentials: "same-origin",
        keepalive: true,
        headers: { "Content-Type": "application/json", "Accept": "application/json" },
        body: payload("pause")
      }).catch(() => {});
    }
  }

  function resume() {
    if (document.visibilityState === "hidden") return;
    lastInteractionAt = Date.now();
    paused = false;
    heartbeat();
  }

  function markInteraction() {
    lastInteractionAt = Date.now();
    if (!paused && document.visibilityState !== "hidden") return;
    resume();
  }

  ["pointerdown", "keydown", "touchstart", "scroll"].forEach(eventName => {
    document.addEventListener(eventName, markInteraction, { passive: true });
  });
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "hidden") pause();
    else resume();
  });
  window.addEventListener("pagehide", pause);
  window.addEventListener("pageshow", resume);
  window.setInterval(heartbeat, 30000);
  resume();
})();
