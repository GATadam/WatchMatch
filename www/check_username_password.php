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
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection error.");
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT password_hash FROM Users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        echo json_encode(["valid" => true]);
    } else {
        echo json_encode(["valid" => false]);
    }
}
?>