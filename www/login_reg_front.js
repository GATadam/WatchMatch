document.addEventListener('DOMContentLoaded', () => {
    const tabLogin = document.getElementById('tab_login');
    const tabRegister = document.getElementById('tab_register');
    const loginPanel = document.getElementById('login');
    const registerPanel = document.getElementById('register');
    const regionSelect = document.getElementById('region_register');
    const eyeIcons = document.querySelectorAll('.eye-icon');
    const registerForm = document.querySelector('#register form');
    const loginForm = document.querySelector('#login form');
    const forgotPasswordToggle = document.getElementById('forgot_password_toggle');
    const forgotPasswordPanel = document.getElementById('forgot_password_panel');
    const forgotPasswordForm = document.getElementById('forgot_password_form');
    const forgotPasswordUsername = document.getElementById('forgot_password_username');
    const forgotPasswordFeedback = document.getElementById('forgot_password_feedback');
    const loginUsernameInput = document.getElementById('username_login');
    const apiBaseUrlCandidates = Array.from(new Set([
        'https://kosmicdoom.com/watchmatch_api',
        window.location.origin + '/watchmatch_api',
        'https://www.kosmicdoom.com/watchmatch_api'
    ]));

    function activateTab(view) {
        const showLogin = view === 'login';
        tabLogin.classList.toggle('active', showLogin);
        tabRegister.classList.toggle('active', !showLogin);
        loginPanel.classList.toggle('active', showLogin);
        registerPanel.classList.toggle('active', !showLogin);

        if (!showLogin && forgotPasswordPanel) {
            forgotPasswordPanel.hidden = true;
        }
    }

    function setForgotPasswordFeedback(message, type = 'info') {
        if (!forgotPasswordFeedback) {
            return;
        }

        if (!message) {
            forgotPasswordFeedback.hidden = true;
            forgotPasswordFeedback.textContent = '';
            forgotPasswordFeedback.className = 'auth_inline_feedback';
            return;
        }

        forgotPasswordFeedback.hidden = false;
        forgotPasswordFeedback.textContent = message;
        forgotPasswordFeedback.className = 'auth_inline_feedback is-' + type;
    }

    async function requestResetApi(endpoint, params) {
        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams(params).toString()
        });

        const text = await response.text();
        let payload;
        try {
            payload = JSON.parse(text);
        } catch (error) {
            throw new Error('Invalid API response.');
        }

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'Could not start password reset.');
        }

        return payload;
    }

    async function loadRegions() {
        if (!regionSelect) {
            return;
        }

        regionSelect.innerHTML = '<option value="" disabled selected>Loading regions...</option>';

        try {
            const response = await fetch('get_regions.php');
            const data = await response.json();

            regionSelect.innerHTML = '';
            data.forEach((region) => {
                const option = document.createElement('option');
                option.value = region.id;
                option.textContent = region.name;
                regionSelect.appendChild(option);
            });
        } catch (error) {
            regionSelect.innerHTML = '<option value="" disabled selected>Could not load regions</option>';
        }
    }

    tabLogin.addEventListener('click', () => activateTab('login'));
    tabRegister.addEventListener('click', () => activateTab('register'));

    if (forgotPasswordToggle && forgotPasswordPanel) {
        forgotPasswordToggle.addEventListener('click', () => {
            const shouldShow = forgotPasswordPanel.hidden;
            forgotPasswordPanel.hidden = !shouldShow;

            if (shouldShow && forgotPasswordUsername && loginUsernameInput) {
                forgotPasswordUsername.value = loginUsernameInput.value.trim();
                forgotPasswordUsername.focus();
            }
        });
    }

    eyeIcons.forEach((icon) => {
        icon.addEventListener('click', () => {
            const passwordInput = icon.previousElementSibling;
            if (!passwordInput) {
                return;
            }

            const isHidden = passwordInput.type === 'password';
            passwordInput.type = isHidden ? 'text' : 'password';
            icon.src = isHidden
                ? 'https://www.kosmicdoom.com/watchmatch_media/hide_pw.svg'
                : 'https://www.kosmicdoom.com/watchmatch_media/show_pw.svg';
        });
    });

    if (registerForm) {
        registerForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            try {
                const username = document.getElementById('username_register').value.trim();
                const email = document.getElementById('email_register').value.trim();

                const usernameResponse = await fetch('check_username.php?username=' + encodeURIComponent(username));
                const usernameData = await usernameResponse.json();
                if (usernameData.exists) {
                    alert('This username is already taken.');
                    return;
                }

                const emailResponse = await fetch('check_email.php?email=' + encodeURIComponent(email));
                const emailData = await emailResponse.json();
                if (emailData.exists) {
                    alert('This email is already registered.');
                    return;
                }

                registerForm.submit();
            } catch (error) {
                alert('Could not validate your registration right now.');
            }
        });
    }

    if (loginForm) {
        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            try {
                const formData = new FormData(loginForm);
                const response = await fetch('check_username_password.php', {
                    method: 'POST',
                    body: formData,
                });
                const data = await response.json();

                if (!data.valid) {
                    alert('Invalid username or password.');
                    return;
                }

                loginForm.submit();
            } catch (error) {
                alert('Could not verify your login right now.');
            }
        });
    }

    if (forgotPasswordForm && forgotPasswordUsername) {
        forgotPasswordForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const username = forgotPasswordUsername.value.trim() || (loginUsernameInput ? loginUsernameInput.value.trim() : '');
            if (!username) {
                setForgotPasswordFeedback('Please enter your username first.', 'error');
                return;
            }

            try {
                setForgotPasswordFeedback('Sending reset link...', 'info');

                let result = null;
                let lastError = null;
                for (const baseUrl of apiBaseUrlCandidates) {
                    try {
                        result = await requestResetApi(baseUrl + '/request_password_reset.php', { username });
                        break;
                    } catch (error) {
                        lastError = error;
                    }
                }

                if (!result) {
                    throw lastError || new Error('Could not start password reset.');
                }

                setForgotPasswordFeedback(result.message || 'Password reset email sent.', 'success');
            } catch (error) {
                setForgotPasswordFeedback(error.message || 'Could not start password reset.', 'error');
            }
        });
    }

    loadRegions();
});
