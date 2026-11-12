(() => {
    "use strict";
    const form = document.getElementById("forgotPasswordForm");
    if (!form) return;
    const button = document.getElementById("forgotPasswordButton");
    const message = document.getElementById("forgotPasswordMessage");
    form.addEventListener("submit", async event => {
        event.preventDefault();
        button.disabled = true;
        button.setAttribute("aria-busy", "true");
        button.querySelector("span").textContent = "Sending...";
        message.className = "form-message";
        message.textContent = "";
        try {
            const response = await fetch("api/auth/forgot-password.php", {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/json", "Accept": "application/json" },
                body: JSON.stringify({ email: document.getElementById("email").value.trim() })
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || "Unable to request a password reset.");
            message.className = "form-message is-visible is-success";
            message.textContent = data.message;
        } catch (error) {
            message.className = "form-message is-visible is-error";
            message.textContent = error.message || "Unable to connect to the server.";
        } finally {
            button.disabled = false;
            button.removeAttribute("aria-busy");
            button.querySelector("span").textContent = "Send Reset Link";
        }
    });
})();