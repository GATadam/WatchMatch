<?php
require __DIR__ . '/includes/app_bootstrap.php';
require __DIR__ . '/includes/auth_feedback.php';
require __DIR__ . '/includes/mail_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

function renderRegisterPage(string $title, string $message, string $tone = 'info', int $statusCode = 200, string $detail = ''): void
{
    watchmatchRenderAuthFeedbackPage([
        'tone' => $tone,
        'eyebrow' => $tone === 'success' ? 'Registration complete' : 'Registration update',
        'title' => $title,
        'message' => $message,
        'detail' => $detail,
        'status_code' => $statusCode,
        'actions' => [
            ['label' => 'Go to login', 'href' => 'login.php'],
            ['label' => 'Back to home', 'href' => 'index.php', 'variant' => 'secondary'],
        ],
    ]);
}

watchmatchLoadEnv();
$email = trim((string) ($_POST['email'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$plainPassword = (string) ($_POST['password'] ?? '');
$region = (int) ($_POST['region'] ?? 0);
$usersTable = getenv('DB_TABLE_U') ?: 'Users';

try {
    $pdo = watchmatchCreatePdo();
} catch (Throwable $throwable) {
    renderRegisterPage(
        'Database connection failed',
        'Watchmatch could not reach the database right now.',
        'error',
        500,
        'Please try again in a little while.'
    );
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    renderRegisterPage('Invalid email address', 'Please go back and enter a valid email address.', 'error', 400);
}

if ($username === '' || $plainPassword === '' || $region <= 0) {
    renderRegisterPage(
        'Missing registration data',
        'Please fill in every required field before creating your account.',
        'error',
        400
    );
}

if (mb_strlen($plainPassword) < 8) {
    renderRegisterPage(
        'Password too short',
        'Use a password with at least 8 characters before creating your account.',
        'error',
        400
    );
}

$password = password_hash($plainPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM {$usersTable} WHERE email = ?");
$stmt->execute([$email]);
if ((int) $stmt->fetchColumn() > 0) {
    renderRegisterPage('Email already registered', 'An account with this email already exists.', 'error', 409);
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM {$usersTable} WHERE username = ?");
$stmt->execute([$username]);
if ((int) $stmt->fetchColumn() > 0) {
    renderRegisterPage('Username already taken', 'Please choose another username and try again.', 'error', 409);
}

do {
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$usersTable} WHERE verification_token = ?");
    $stmt->execute([$token]);
} while ((int) $stmt->fetchColumn() > 0);

$verificationUrl = 'https://kosmicdoom.com/watchmatch/verify_email.php?token=' . urlencode($token);
$safeVerificationUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');
$to = $email;
$subject = 'Verify your Watchmatch account';
$textMessage = "Welcome to Watchmatch!\r\n\r\n"
    . "Verify your email to finish setting up your account:\r\n"
    . $verificationUrl . "\r\n\r\n"
    . "If you did not create this account, you can ignore this email.\r\n";
$htmlMessage = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your Watchmatch account</title>
</head>
<body style="margin:0;padding:0;background-color:#050b14;color:#f8fafc;font-family:Montserrat,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:
        radial-gradient(circle at top left, rgba(42,198,217,0.16), transparent 28%),
        radial-gradient(circle at bottom right, rgba(37,99,235,0.14), transparent 26%),
        linear-gradient(180deg, #050b14 0%, #081221 100%);
        background-color:#050b14;">
        <tr>
            <td style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;border:1px solid rgba(148,163,184,0.16);border-radius:28px;overflow:hidden;background-color:#0b1220;background-image:radial-gradient(circle at top right, rgba(42,198,217,0.1), transparent 24%), linear-gradient(180deg, rgba(11,18,32,0.98), rgba(10,18,32,0.92));box-shadow:0 24px 60px rgba(2,6,23,0.45);">
                    <tr>
                        <td style="padding:32px 32px 24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;">
                                <tr>
                                    <td style="padding-bottom:20px;">
                                        <img src="https://www.kosmicdoom.com/watchmatch_media/logo.png" alt="Watchmatch" style="display:block;width:64px;height:auto;">
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom:12px;color:#2ac6d9;font-size:12px;font-weight:800;letter-spacing:0.16em;text-transform:uppercase;">Welcome to Watchmatch</td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom:16px;color:#f8fafc;font-size:34px;line-height:1.08;font-weight:800;">Verify your email to finish setting up your account.</td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom:20px;color:#b8c7d8;font-size:16px;line-height:1.7;">
                                        Thanks for joining Watchmatch. Confirm your email address to unlock the dashboard, friends, and online movie matching experience.
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom:24px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" valign="middle" bgcolor="#2ac6d9" style="border-radius:999px;background:linear-gradient(135deg,#2ac6d9,#1da8b9);">
                                                    <a href="' . $safeVerificationUrl . '" style="display:inline-block;padding:16px 28px;color:#031018;font-size:16px;line-height:1;font-weight:800;text-decoration:none;font-family:Montserrat,Arial,sans-serif;">Verify email</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 20px;border-radius:20px;background-color:rgba(15,23,42,0.72);color:#94a3b8;font-size:14px;line-height:1.7;">
                                        If the button does not work, open this link in your browser:<br>
                                        <a href="' . $safeVerificationUrl . '" style="color:#8fe7f2;text-decoration:none;word-break:break-word;">' . $safeVerificationUrl . '</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO {$usersTable} (email, verification_token, email_verified, username, password_hash, region_id, profil_icon, icon_color, icon_bg_color)
         VALUES (?, ?, 0, ?, ?, ?, 'a', 'ffffff', '000000')"
    );
    $stmt->execute([$email, $token, $username, $password, $region]);

    if (!watchmatchSendMultipartMail($to, $subject, $textMessage, $htmlMessage)) {
        $pdo->rollBack();
        renderRegisterPage(
            'Could not send verification email',
            'Your account was not created because the verification email could not be sent.',
            'error',
            500,
            'Please try again in a moment.'
        );
    }

    $pdo->commit();

    renderRegisterPage(
        'Check your inbox',
        'Your account has been created successfully. Please verify your email before logging in.',
        'success',
        200,
        'We sent the confirmation link to ' . $email . '. If you do not see it, check your spam folder too.'
    );
} catch (Throwable $throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    renderRegisterPage(
        'Registration failed',
        'Watchmatch could not create your account right now.',
        'error',
        500,
        'Please try again later.'
    );
}
