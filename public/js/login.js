const loginForm = document.getElementById('loginForm');
const passwordInput = document.getElementById('password');
const togglePassword = document.getElementById('togglePassword');
const loginMessage = document.getElementById('loginMessage');
const loginButton = document.getElementById('loginBtn') || document.getElementById('loginButton');
let adminLockoutTimer = null;

togglePassword.addEventListener('click', () => {
    const isPassword = passwordInput.type === 'password';

    passwordInput.type = isPassword ? 'text' : 'password';

    togglePassword.innerHTML = isPassword
        ? '<svg class="icon" aria-hidden="true"><use href="#i-eye-off"></use></svg>'
        : '<svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg>';

    togglePassword.setAttribute(
        'aria-label',
        isPassword ? 'Hide password' : 'Show password'
    );
});

loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (window.MarkazAuthUI) window.MarkazAuthUI.clearMessage(loginForm);
    else { loginMessage.className = 'form-message login-message'; loginMessage.textContent = ''; }

    const email = document.getElementById('email').value.trim();
    const password = passwordInput.value;

    if (!email || !password) {
        if (window.MarkazAuthUI) window.MarkazAuthUI.showMessage('Please enter your email and password.', 'error', loginForm);
        else { loginMessage.className = 'form-message is-visible is-error login-message'; loginMessage.textContent = 'Please enter your email and password.'; }
        return;
    }

    loginButton.disabled = true;
    loginButton.innerHTML =
        '<span>Signing in...</span><svg class="icon" aria-hidden="true"><use href="#i-arrow"></use></svg>';

    try {
        const response = await fetch('api/auth/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                email: email,
                password: password,
                expected_role: 'admin'
            })
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
            const error = new Error(data.message || 'Unable to sign in.');
            error.loginData = data;
            throw error;
        }

        if (window.MarkazAuthUI) window.MarkazAuthUI.showMessage('Login successful. Welcome to MARKAZ.', 'success', loginForm);
        else { loginMessage.className = 'form-message is-visible is-success login-message'; loginMessage.textContent = 'Login successful. Welcome to MARKAZ.'; }

        window.location.href = 'dashboard.html';

    } catch (error) {
        const loginPage = document.querySelector('.login-page, .mk-auth');

        if (loginPage) {
            loginPage.classList.remove('login-success');
            loginPage.classList.remove('login-failed');
        }

        /*
         * Wrong login reaction:
         * the student reacts briefly instead of walking to MARKAZ.
         */
        if (loginPage) {
            void loginPage.offsetWidth;
            loginPage.classList.add('login-failed');
        }

        const lockout = error.loginData;
        if (lockout?.locked && Number(lockout.remaining_seconds) > 0) {
            if (adminLockoutTimer) clearInterval(adminLockoutTimer);
            let remaining = Math.floor(Number(lockout.remaining_seconds));
            loginButton.disabled = true;
            loginButton.innerHTML = '<span>Account Locked</span>';
            const showCountdown = () => {
                const days = Math.floor(remaining / 86400), hours = Math.floor((remaining % 86400) / 3600);
                const minutes = Math.floor((remaining % 3600) / 60), seconds = remaining % 60;
                const text = `Admin account locked. Time remaining: ${days}d ${hours}h ${minutes}m ${seconds}s.`;
                if (window.MarkazAuthUI) window.MarkazAuthUI.showMessage(text, 'error', loginForm);
                else { loginMessage.className = 'form-message is-visible is-error login-message'; loginMessage.textContent = text; }
                if (remaining <= 0) {
                    clearInterval(adminLockoutTimer); adminLockoutTimer = null;
                    loginButton.disabled = false;
                    loginButton.innerHTML = '<span>Sign In</span><svg class="icon" aria-hidden="true"><use href="#i-arrow"></use></svg>';
                    if (window.MarkazAuthUI) window.MarkazAuthUI.showMessage('The lockout period has ended. The server will verify your next sign-in.', 'success', loginForm);
                    return;
                }
                remaining -= 1;
            };
            showCountdown(); adminLockoutTimer = setInterval(showCountdown, 1000);
        } else {
            const errorMessage = error.message || 'Something went wrong.';
            if (window.MarkazAuthUI) window.MarkazAuthUI.showMessage(errorMessage, 'error', loginForm);
            else { loginMessage.className = 'form-message is-visible is-error login-message'; loginMessage.textContent = errorMessage; }
            loginButton.disabled = false;
            loginButton.innerHTML = '<span>Sign In</span><svg class="icon" aria-hidden="true"><use href="#i-arrow"></use></svg>';
        }
}
});
