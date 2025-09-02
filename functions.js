const tabLogin = document.getElementById('tab-login');
const tabRegister = document.getElementById('tab-register');
const loginPanel = document.getElementById('login');
const registerPanel = document.getElementById('register');

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
