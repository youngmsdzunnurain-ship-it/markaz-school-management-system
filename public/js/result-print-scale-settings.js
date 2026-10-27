(() => {
    "use strict";
    const form = document.getElementById("resultPrintScaleForm");
    const select = document.getElementById("resultPrintScale");
    const message = document.getElementById("resultPrintScaleMessage");
    const button = document.getElementById("saveResultPrintScaleButton");
    if (!form || !select || !message || !button) return;

    async function request(method = "GET", scale) {
        const response = await fetch("./api/settings/result-print-scale.php", {
            method,
            credentials: "same-origin",
            cache: "no-store",
            headers: { "Content-Type": "application/json", "Accept": "application/json" },
            ...(method === "POST" ? { body: JSON.stringify({ scale: Number(scale) }) } : {})
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to load print scale settings.");
        }
        return data;
    }

    request().then(data => {
        select.value = String(data.scale);
        message.textContent = "Current print scale: " + data.scale + "%.";
    }).catch(error => {
        message.textContent = error.message;
        message.className = "form-message error";
    });

    form.addEventListener("submit", async event => {
        event.preventDefault();
        button.disabled = true;
        button.textContent = "Saving...";
        message.className = "form-message";
        message.textContent = "";
        try {
            const data = await request("POST", select.value);
            message.textContent = data.message + " New prints will use " + data.scale + "%.";
            message.className = "form-message success";
        } catch (error) {
            message.textContent = error.message;
            message.className = "form-message error";
        } finally {
            button.disabled = false;
            button.textContent = "Save Print Scale";
        }
    });
})();
