(() => {
    "use strict";
    const form = document.getElementById("resetPasswordForm");
    if (!form) return;
    const button = document.getElementById("resetPasswordButton");
    const message = document.getElementById("resetPasswordMessage");
    const token = new URLSearchParams(window.location.search).get("token") || "";
    form.addEventListener("submit", async event => {
        event.preventDefault();
        if (!token) {
            message.className = "login-message error";
            message.textContent = "This password reset link is invalid or has expired.";
            return;
        }
        button.disabled = true;
        button.textContent = "Updating...";
        message.className = "login-message";
        try {
            const response = await fetch("api/auth/reset-password.php", {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/json", "Accept": "application/json" },
                body: JSON.stringify({
                    token,
                    password: document.getElementById("password").value,
                    password_confirmation: document.getElementById("passwordConfirmation").value
                })
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || "Unable to reset password.");
            message.className = "login-message success";
            message.textContent = data.message;
            form.reset();
            window.setTimeout(() => { window.location.href = "login.html"; }, 1800);
        } catch (error) {
            message.className = "login-message error";
            message.textContent = error.message || "Unable to connect to the server.";
        } finally {
            button.disabled = false;
            button.textContent = "Reset Password";
        }
    });
})();
