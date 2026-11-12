(() => {
    "use strict";
    const form = document.getElementById("resetPasswordForm");
    if (!form) return;
    const button = document.getElementById("resetPasswordButton");
    const message = document.getElementById("resetPasswordMessage");
    const token = new URLSearchParams(window.location.hash.slice(1)).get("token") || "";
    form.addEventListener("submit", async event => {
        event.preventDefault();
        if (!token) {
            message.className = "form-message is-visible is-error";
            message.textContent = "This password reset link is invalid or has expired.";
            return;
        }
        const password = document.getElementById("password").value;
        const confirmation = document.getElementById("passwordConfirmation").value;
        if (password !== confirmation) {
            message.className = "form-message is-visible is-error";
            message.textContent = "The password confirmation does not match.";
            document.getElementById("passwordConfirmation").focus();
            return;
        }
        button.disabled = true;
        button.setAttribute("aria-busy", "true");
        button.querySelector("span").textContent = "Updating...";
        message.className = "form-message";
        try {
            const response = await fetch("api/auth/reset-password.php", {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/json", "Accept": "application/json" },
                body: JSON.stringify({ token, password, password_confirmation: confirmation })
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || "Unable to reset password.");
            message.className = "form-message is-visible is-success";
            message.textContent = data.message;
            form.reset();
            window.setTimeout(() => { window.location.href = "login.html?open=1"; }, 1800);
        } catch (error) {
            message.className = "form-message is-visible is-error";
            message.textContent = error.message || "Unable to connect to the server.";
        } finally {
            button.disabled = false;
            button.removeAttribute("aria-busy");
            button.querySelector("span").textContent = "Reset Password";
        }
    });
})();