<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJsonResponse([
        'success' => false,
        'message' => 'Invalid request method.',
    ], 405);
}

$usersTable = getenv('DB_TABLE_U') ?: 'Users';
$token = trim((string) apiRequestValue('token', ''));
$email = trim((string) apiRequestValue('email', ''));
$password = (string) apiRequestValue('password', '');

if ($token === '' || $email === '' || $password === '') {
    apiJsonResponse([
        'success' => false,
        'message' => 'Please fill in every required field.',
    ], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Please enter a valid email address.',
    ], 400);
}

if (mb_strlen($password) < 8) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Your new password must contain at least 8 characters.',
    ], 400);
}

$stmt = $pdo->prepare("SELECT id FROM {$usersTable} WHERE verification_token = ? AND email = ? LIMIT 1");
$stmt->execute([$token, $email]);
$user = $stmt->fetch();

if (!$user) {
    apiJsonResponse([
        'success' => false,
        'message' => 'The reset link is invalid, expired, or does not match this email address.',
    ], 404);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare(
        "UPDATE {$usersTable}
         SET password_hash = ?, auth_token = NULL, verification_token = NULL
         WHERE id = ?"
    );
    $stmt->execute([$passwordHash, (int) $user['id']]);
} catch (Throwable $throwable) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Could not update the password right now.',
    ], 500);
}

apiJsonResponse([
    'success' => true,
    'message' => 'Your password has been updated successfully. You can now log in.',
]);
