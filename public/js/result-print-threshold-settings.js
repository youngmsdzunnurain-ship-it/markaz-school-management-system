(() => {
    "use strict";
    const form = document.getElementById("resultPrintThresholdForm");
    const select = document.getElementById("resultPrintThreshold");
    const message = document.getElementById("resultPrintThresholdMessage");
    const button = document.getElementById("saveResultPrintThresholdButton");
    if (!form || !select || !message || !button) return;

    async function request(method = "GET", threshold) {
        const response = await fetch("./api/settings/result-print-threshold.php", {
            method,
            credentials: "same-origin",
            cache: "no-store",
            headers: { "Content-Type": "application/json", "Accept": "application/json" },
            ...(method === "POST" ? { body: JSON.stringify({ threshold: Number(threshold) }) } : {})
        });
        const raw = await response.text();
        let data;
        try { data = JSON.parse(raw); }
        catch { throw new Error("The server returned an invalid response (HTTP " + response.status + ")."); }
        if (!response.ok || !data.success) throw new Error(data.message || "Unable to save print eligibility threshold.");
        return data;
    }

    request().then(data => {
        select.value = String(data.threshold);
        message.textContent = "Current minimum result-entry completion: " + data.threshold + "%.";
    }).catch(error => {
        message.textContent = error.message;
        message.className = "form-message error";
    });

    form.addEventListener("submit", async event => {
        event.preventDefault();
        button.disabled = true;
        button.textContent = "Saving…";
        message.className = "form-message";
        try {
            const data = await request("POST", select.value);
            message.textContent = data.message + " Minimum: " + data.threshold + "%.";
            message.className = "form-message success";
        } catch (error) {
            message.textContent = error.message;
            message.className = "form-message error";
        } finally {
            button.disabled = false;
            button.textContent = "Save Eligibility Threshold";
        }
    });
})();
