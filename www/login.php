<?php
$envPath = '/web/htdocs/www.kosmicdoom.com/home/.env';
if (file_exists($envPath)) {
    $lines = file($envPath);
    foreach ($lines as $line) {
        if (trim($line) && strpos($line, '=') !== false) {
            putenv(trim($line));
        }
    }
}
$dsn = 'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4';
$user = getenv('DB_USER_NAME');
$pass = getenv('DB_PASSWORD');

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection error.');
}

if (isset($_COOKIE['watchmatch_auth_token'])) {
    $stmt = $pdo->prepare('SELECT id FROM ' . getenv('DB_TABLE_U') . ' WHERE auth_token = ?');
    $stmt->execute([$_COOKIE['watchmatch_auth_token']]);
    $existingUser = $stmt->fetch();
    if ($existingUser) {
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WATCHMATCH - Login</title>
    <link rel="icon" type="image/x-icon" href="https://www.kosmicdoom.com/watchmatch_media/logo.png">
    <link rel="stylesheet" href="styles/main_style.css">
    <link rel="stylesheet" href="styles/login_style.css">
    <script src="login_reg_front.js" defer></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
</head>
<body class="auth_body auth_body_compact">
    <main class="auth_shell auth_shell_compact">
        <section class="auth_card auth_card_compact">
            <a class="auth_brand auth_brand_compact" href="index.php">
                <img src="https://www.kosmicdoom.com/watchmatch_media/logo.png" alt="Watchmatch">
                <span>WATCHMATCH</span>
            </a>

            <div class="login_tabs">
                <button id="tab_login" class="tab active" type="button">Login</button>
                <button id="tab_register" class="tab" type="button">Create Account</button>
            </div>

            <div id="login" class="panel active">
                <div class="auth_panel_intro">
                    <h2>Welcome back</h2>
                    <p class="hint">Sign in to continue.</p>
                </div>

                <form method="POST" action="login_submit.php">
                    <div class="login_field">
                        <label for="username_login">Username</label>
                        <input id="username_login" name="username" type="text" autocomplete="username" required>
                    </div>

                    <div class="login_field">
                        <label for="password_login">Password</label>
                        <div class="password_container">
                            <input id="password_login" name="password" type="password" minlength="8" autocomplete="current-password" required>
                            <img src="https://www.kosmicdoom.com/watchmatch_media/show_pw.svg" alt="Show password" class="eye-icon">
                        </div>
                    </div>

                    <button type="submit" class="btn">Login</button>
                </form>
            </div>

            <div id="register" class="panel">
                <div class="auth_panel_intro">
                    <h2>Create your account</h2>
                    <p class="hint">After registration, you will receive a confirmation link by email.</p>
                </div>

                <form method="POST" action="register.php">
                    <div class="login_field">
                        <label for="username_register">Username</label>
                        <input id="username_register" name="username" type="text" autocomplete="username" required>
                    </div>

                    <div class="login_field">
                        <label for="email_register">Email</label>
                        <input id="email_register" name="email" type="email" autocomplete="email" required>
                    </div>

                    <div class="login_field">
                        <label for="password_register">Password</label>
                        <div class="password_container">
                            <input id="password_register" name="password" type="password" minlength="8" autocomplete="new-password" required>
                            <img src="https://www.kosmicdoom.com/watchmatch_media/show_pw.svg" alt="Show password" class="eye-icon">
                        </div>
                        <p class="hint">Use at least 8 characters.</p>
                    </div>

                    <div class="login_field">
                        <label for="region_register">Choose your watch region <span id="region_info">You can change this later</span></label>
                        <select id="region_register" name="region" required></select>
                    </div>

                    <button type="submit" class="btn">Register</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
