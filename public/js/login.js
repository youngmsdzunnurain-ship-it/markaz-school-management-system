const loginForm = document.getElementById('loginForm');
const passwordInput = document.getElementById('password');
const togglePassword = document.getElementById('togglePassword');
const loginMessage = document.getElementById('loginMessage');
const loginButton = document.getElementById('loginButton');

togglePassword.addEventListener('click', () => {
    const isPassword = passwordInput.type === 'password';

    passwordInput.type = isPassword ? 'text' : 'password';

    togglePassword.innerHTML = isPassword
        ? '<i class="fa-solid fa-eye-slash"></i>'
        : '<i class="fa-solid fa-eye"></i>';

    togglePassword.setAttribute(
        'aria-label',
        isPassword ? 'Hide password' : 'Show password'
    );
});

loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    loginMessage.className = 'login-message';
    loginMessage.textContent = '';

    const email = document.getElementById('email').value.trim();
    const password = passwordInput.value;

    if (!email || !password) {
        loginMessage.className = 'login-message error';
        loginMessage.textContent = 'Please enter your email and password.';
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
                password: password
            })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to sign in.');
        }

        loginMessage.className = 'login-message success';
        loginMessage.textContent = 'Login successful. Welcome to MARKAZ.';

        window.location.href = 'dashboard.html';

    } catch (error) {
        const loginPage = document.querySelector('.login-page');

        loginPage.classList.remove('login-success');
        loginPage.classList.remove('login-failed');

        /*
         * Wrong login reaction:
         * the student reacts briefly instead of walking to MARKAZ.
         */
        void loginPage.offsetWidth;
        loginPage.classList.add('login-failed');

        loginMessage.className = 'login-message error';
        loginMessage.textContent =
            error.message || 'Something went wrong.';

        loginButton.disabled = false;
        loginButton.innerHTML =
            '<span>Sign In</span><i class="fa-solid fa-arrow-right"></i>';
}
});
