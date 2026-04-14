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
$usersTable = getenv('DB_TABLE_U');

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection error.");
}

if (!isset($_GET['token']) || trim($_GET['token']) === '') {
    echo "Invalid verification link.";
    exit;
}

$token = trim($_GET['token']);

$stmt = $pdo->prepare("SELECT id, email_verified FROM " . $usersTable . " WHERE verification_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    echo "Invalid or expired verification link.";
    exit;
}

if ($user['email_verified']) {
    echo "Your email is already verified.";
    exit;
}

$stmt = $pdo->prepare("UPDATE " . $usersTable . " SET email_verified = 1, verification_token = NULL WHERE id = ?");
$stmt->execute([$user['id']]);

echo "Your email has been successfully verified. You can now log in.";
?>
