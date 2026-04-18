<?php
require __DIR__ . '/includes/app_bootstrap.php';
require __DIR__ . '/includes/auth_feedback.php';

function renderVerifyPage(string $title, string $message, string $tone = 'info', int $statusCode = 200, string $detail = ''): void
{
    watchmatchRenderAuthFeedbackPage([
        'tone' => $tone,
        'eyebrow' => $tone === 'success' ? 'Email verified' : 'Verification update',
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
$usersTable = getenv('DB_TABLE_U') ?: 'Users';

try {
    $pdo = watchmatchCreatePdo();
} catch (Throwable $throwable) {
    renderVerifyPage(
        'Database connection failed',
        'Watchmatch could not verify your email right now.',
        'error',
        500,
        'Please try the link again later.'
    );
}

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '') {
    renderVerifyPage('Invalid verification link', 'The verification link is missing or incomplete.', 'error', 400);
}

$stmt = $pdo->prepare("SELECT id, email_verified FROM {$usersTable} WHERE verification_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    renderVerifyPage(
        'Invalid or expired link',
        'This verification link is no longer valid.',
        'error',
        404,
        'If needed, create a new account or request a fresh verification email later.'
    );
}

if ((int) $user['email_verified'] === 1) {
    renderVerifyPage(
        'Email already verified',
        'This Watchmatch account has already been verified.',
        'info',
        200,
        'You can go straight to the login page.'
    );
}

$stmt = $pdo->prepare("UPDATE {$usersTable} SET email_verified = 1, verification_token = NULL WHERE id = ?");
$stmt->execute([(int) $user['id']]);

renderVerifyPage(
    'Email verified successfully',
    'Your email has been confirmed and your account is ready.',
    'success',
    200,
    'You can now sign in and start planning what to watch together.'
);
