<?php
header('Content-Type: application/json');
$envPath = '/web/htdocs/www.kosmicdoom.com/home/.env';
if (file_exists($envPath)) {
    $lines = file($envPath);
    foreach ($lines as $line) {
        if (trim($line) && strpos($line, '=') !== false) {
            putenv(trim($line));
        }
    }
}
if (!isset($_GET['email']) || trim($_GET['email']) === '') {
    echo json_encode(['exists' => false, 'error' => 'No email provided']);
    exit;
}

$email = trim($_GET['email']);

$dsn = 'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4';
$user = getenv('DB_USER_NAME');
$pass = getenv('DB_PASSWORD');

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(['exists' => false, 'error' => 'Database connection error']);
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . getenv('DB_TABLE_USERS') . " WHERE email = ?");
$stmt->execute([$email]);
$exists = $stmt->fetchColumn() > 0;

echo json_encode(['exists' => $exists]);
?>