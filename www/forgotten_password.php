<?php
require __DIR__ . '/includes/app_bootstrap.php';
require __DIR__ . '/includes/auth_feedback.php';

watchmatchLoadEnv();
$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '') {
    watchmatchRenderAuthFeedbackPage([
        'tone' => 'error',
        'eyebrow' => 'Password reset',
        'title' => 'Invalid reset link',
        'message' => 'This password reset link is missing or incomplete.',
        'detail' => 'Please request a new reset email from the login page.',
        'status_code' => 400,
        'actions' => [
            ['label' => 'Go to login', 'href' => 'login.php'],
            ['label' => 'Back to home', 'href' => 'index.php', 'variant' => 'secondary'],
        ],
    ]);
}

try {
    $pdo = watchmatchCreatePdo();
} catch (Throwable $throwable) {
    watchmatchRenderAuthFeedbackPage([
        'tone' => 'error',
        'eyebrow' => 'Password reset',
        'title' => 'Database connection failed',
        'message' => 'Watchmatch could not open the password reset page right now.',
        'detail' => 'Please try the link again later.',
        'status_code' => 500,
        'actions' => [
            ['label' => 'Go to login', 'href' => 'login.php'],
        ],
    ]);
}

$usersTable = getenv('DB_TABLE_U') ?: 'Users';
$stmt = $pdo->prepare("SELECT id FROM {$usersTable} WHERE verification_token = ? LIMIT 1");
$stmt->execute([$token]);
$resetUser = $stmt->fetch();

if (!$resetUser) {
    watchmatchRenderAuthFeedbackPage([
        'tone' => 'error',
        'eyebrow' => 'Password reset',
        'title' => 'Reset link expired',
        'message' => 'This password reset link is no longer valid.',
        'detail' => 'Please request a fresh reset email from the login page.',
        'status_code' => 404,
        'actions' => [
            ['label' => 'Go to login', 'href' => 'login.php'],
            ['label' => 'Back to home', 'href' => 'index.php', 'variant' => 'secondary'],
        ],
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WATCHMATCH - Reset Password</title>
    <link rel="icon" type="image/x-icon" href="https://www.kosmicdoom.com/watchmatch_media/logo.png">
    <link rel="stylesheet" href="styles/main_style.css">
    <link rel="stylesheet" href="styles/login_style.css">
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

            <div class="auth_panel_intro">
                <p class="auth_kicker">Password reset</p>
                <h2>Choose a new password</h2>
                <p class="hint">For extra protection, confirm the email address connected to your account before saving the new password.</p>
            </div>

            <form id="forgotten_password_form">
                <input type="hidden" id="forgotten_password_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="login_field">
                    <label for="forgotten_password_email">Account email</label>
                    <input id="forgotten_password_email" name="email" type="email" autocomplete="email" required>
                </div>

                <div class="login_field">
                    <label for="forgotten_password_new">New password</label>
                    <div class="password_container">
                        <input id="forgotten_password_new" name="password" type="password" minlength="8" autocomplete="new-password" required>
                        <img src="https://www.kosmicdoom.com/watchmatch_media/show_pw.svg" alt="Show password" class="eye-icon">
                    </div>
                </div>

                <div class="login_field">
                    <label for="forgotten_password_confirm">Confirm new password</label>
                    <div class="password_container">
                        <input id="forgotten_password_confirm" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>
                        <img src="https://www.kosmicdoom.com/watchmatch_media/show_pw.svg" alt="Show password" class="eye-icon">
                    </div>
                </div>

                <button type="submit" class="btn">Update password</button>
            </form>

            <div id="forgotten_password_feedback" class="auth_inline_feedback" hidden></div>
            <div id="forgotten_password_actions" class="auth_aux_row" hidden>
                <a href="login.php" class="auth_text_link_button">Go to login</a>
            </div>
        </section>
    </main>

    <script>
    (() => {
        const token = document.getElementById('forgotten_password_token').value;
        const form = document.getElementById('forgotten_password_form');
        const feedback = document.getElementById('forgotten_password_feedback');
        const actions = document.getElementById('forgotten_password_actions');
        const eyeIcons = document.querySelectorAll('.eye-icon');
        const apiCandidates = Array.from(new Set([
            'https://kosmicdoom.com/watchmatch_api/reset_password.php',
            window.location.origin + '/watchmatch_api/reset_password.php',
            'https://www.kosmicdoom.com/watchmatch_api/reset_password.php'
        ]));

        function setFeedback(message, type = 'info') {
            if (!message) {
                feedback.hidden = true;
                feedback.textContent = '';
                feedback.className = 'auth_inline_feedback';
                return;
            }

            feedback.hidden = false;
            feedback.textContent = message;
            feedback.className = 'auth_inline_feedback is-' + type;
        }

        async function postReset(url, params) {
            const response = await fetch(url, {
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
                throw new Error(payload.message || 'Could not update the password right now.');
            }

            return payload;
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

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const email = document.getElementById('forgotten_password_email').value.trim();
            const password = document.getElementById('forgotten_password_new').value;
            const confirmPassword = document.getElementById('forgotten_password_confirm').value;

            if (password !== confirmPassword) {
                setFeedback('The two passwords do not match.', 'error');
                return;
            }

            const payload = {
                token,
                email,
                password
            };

            try {
                setFeedback('Updating password...', 'info');

                let result = null;
                let lastError = null;
                for (const endpoint of apiCandidates) {
                    try {
                        result = await postReset(endpoint, payload);
                        break;
                    } catch (error) {
                        lastError = error;
                    }
                }

                if (!result) {
                    throw lastError || new Error('Could not update the password right now.');
                }

                form.hidden = true;
                actions.hidden = false;
                setFeedback(result.message || 'Your password has been updated successfully.', 'success');
            } catch (error) {
                setFeedback(error.message || 'Could not update the password right now.', 'error');
            }
        });
    })();
    </script>
</body>
</html>
