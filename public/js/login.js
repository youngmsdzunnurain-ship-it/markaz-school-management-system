const loginForm = document.getElementById('loginForm');
const passwordInput = document.getElementById('password');
const togglePassword = document.getElementById('togglePassword');
const loginMessage = document.getElementById('loginMessage');
const loginButton = document.getElementById('loginButton');

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
        '<i class="fa-solid fa-spinner fa-spin"></i><span>Signing in...</span>';

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

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to sign in.');
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

        const errorMessage = error.message || 'Something went wrong.';
        if (window.MarkazAuthUI) window.MarkazAuthUI.showMessage(errorMessage, 'error', loginForm);
        else { loginMessage.className = 'form-message is-visible is-error login-message'; loginMessage.textContent = errorMessage; }

        loginButton.disabled = false;
        loginButton.innerHTML =
            '<span>Sign In</span><i class="fa-solid fa-arrow-right"></i>';
}
});
