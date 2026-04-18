<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/mail_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJsonResponse([
        'success' => false,
        'message' => 'Invalid request method.',
    ], 405);
}

$usersTable = getenv('DB_TABLE_U') ?: 'Users';
$username = trim((string) apiRequestValue('username', ''));

if ($username === '') {
    apiJsonResponse([
        'success' => false,
        'message' => 'Please enter your username first.',
    ], 400);
}

$stmt = $pdo->prepare("SELECT id, email, email_verified FROM {$usersTable} WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

$genericMessage = 'If the username exists, a password reset link has been sent to the connected email address.';

if (!$user || (int) $user['email_verified'] !== 1) {
    apiJsonResponse([
        'success' => true,
        'message' => $genericMessage,
    ]);
}

do {
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$usersTable} WHERE verification_token = ?");
    $stmt->execute([$token]);
} while ((int) $stmt->fetchColumn() > 0);

$resetUrl = 'https://kosmicdoom.com/watchmatch/forgotten_password.php?token=' . urlencode($token);
$safeResetUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
$subject = 'Reset your Watchmatch password';
$textMessage = "A password reset was requested for your Watchmatch account.\r\n\r\n"
    . "Open this link to choose a new password:\r\n"
    . $resetUrl . "\r\n\r\n"
    . "If you did not request this, you can ignore this email.\r\n";
$htmlMessage = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset your Watchmatch password</title>
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
                                    <td style="padding-bottom:12px;color:#2ac6d9;font-size:12px;font-weight:800;letter-spacing:0.16em;text-transform:uppercase;">Password reset</td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom:16px;color:#f8fafc;font-size:34px;line-height:1.08;font-weight:800;">Choose a new password for your Watchmatch account.</td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom:20px;color:#b8c7d8;font-size:16px;line-height:1.7;">
                                        Open the secure reset page below, confirm your account email, and set a new password.
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom:24px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" valign="middle" bgcolor="#2ac6d9" style="border-radius:999px;background:linear-gradient(135deg,#2ac6d9,#1da8b9);">
                                                    <a href="' . $safeResetUrl . '" style="display:inline-block;padding:16px 28px;color:#031018;font-size:16px;line-height:1;font-weight:800;text-decoration:none;font-family:Montserrat,Arial,sans-serif;">Reset password</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 20px;border-radius:20px;background-color:rgba(15,23,42,0.72);color:#94a3b8;font-size:14px;line-height:1.7;">
                                        If the button does not work, open this link in your browser:<br>
                                        <a href="' . $safeResetUrl . '" style="color:#8fe7f2;text-decoration:none;word-break:break-word;">' . $safeResetUrl . '</a>
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

    $stmt = $pdo->prepare("UPDATE {$usersTable} SET auth_token = NULL, verification_token = ? WHERE id = ?");
    $stmt->execute([$token, (int) $user['id']]);

    $mailSent = watchmatchSendMultipartMail(
        (string) $user['email'],
        $subject,
        $textMessage,
        $htmlMessage
    );

    if (!$mailSent) {
        $pdo->rollBack();
        apiJsonResponse([
            'success' => false,
            'message' => 'Could not send the password reset email right now.',
        ], 500);
    }

    $pdo->commit();
} catch (Throwable $throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    apiJsonResponse([
        'success' => false,
        'message' => 'Could not start the password reset process right now.',
    ], 500);
}

apiJsonResponse([
    'success' => true,
    'message' => $genericMessage,
]);
