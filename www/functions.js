const tabLogin = document.getElementById('tab_login');
const tabRegister = document.getElementById('tab_register');
const loginPanel = document.getElementById('login');
const registerPanel = document.getElementById('register');

const emailLogin = document.getElementById('email_login');
const passwordLogin = document.getElementById('password_login');
const passwordRegister = document.getElementById('password_register');

passwordLogin.style.width = emailLogin.offsetWidth + "px";
passwordRegister.style.width = emailLogin.offsetWidth + "px";

// a grid miatt kell a méret beállítás, mert a képet is egy gridelemnek venné

const eyeIcons = document.querySelectorAll('.eye-icon');
eyeIcons.forEach(icon => {
    icon.addEventListener('click', () => {
        let passwordInput = icon.previousElementSibling;
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.src = 'https://www.kosmicdoom.com/watchmatch_media/hide_pw.svg';
        } else {
            passwordInput.type = 'password';
            icon.src = 'https://www.kosmicdoom.com/watchmatch_media/show_pw.svg';
        }
    });
});

tabLogin.addEventListener('click', () => {
    tabLogin.classList.add('active');
    tabRegister.classList.remove('active');
    loginPanel.classList.add('active');
    registerPanel.classList.remove('active');
});

tabRegister.addEventListener('click', () => {
    tabRegister.classList.add('active');
    tabLogin.classList.remove('active');
    registerPanel.classList.add('active');
    loginPanel.classList.remove('active');
});

document.addEventListener("DOMContentLoaded", function () {
    const registerForm = document.querySelector('#register form');
    registerForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const username = document.getElementById('username_register').value.trim();
        const email = document.getElementById('email_register').value.trim();

        const usernameResponse = await fetch('check_username.php?username=' + encodeURIComponent(username));
        const usernameData = await usernameResponse.json();

        if (usernameData.exists) {
            alert('This username is already taken!');
            return;
        }

        const emailResponse = await fetch('check_email.php?email=' + encodeURIComponent(email));
        const emailData = await emailResponse.json();

        if (emailData.exists) {
            alert('This email is already registered!');
            return;
        }

        registerForm.submit();
    });
});