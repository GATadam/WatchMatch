document.addEventListener('DOMContentLoaded', () => {
    const tabLogin = document.getElementById('tab_login');
    const tabRegister = document.getElementById('tab_register');
    const loginPanel = document.getElementById('login');
    const registerPanel = document.getElementById('register');
    const regionSelect = document.getElementById('region_register');
    const eyeIcons = document.querySelectorAll('.eye-icon');
    const registerForm = document.querySelector('#register form');
    const loginForm = document.querySelector('#login form');

    function activateTab(view) {
        const showLogin = view === 'login';
        tabLogin.classList.toggle('active', showLogin);
        tabRegister.classList.toggle('active', !showLogin);
        loginPanel.classList.toggle('active', showLogin);
        registerPanel.classList.toggle('active', !showLogin);
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

    loadRegions();
});
