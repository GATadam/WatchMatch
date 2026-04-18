<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare('SELECT id, username, password_hash, email_verified FROM ' . getenv('DB_TABLE_U') . ' WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if (!$user['email_verified']) {
            die('Please verify your email before logging in.');
        }

        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];

        do {
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . getenv('DB_TABLE_U') . ' WHERE auth_token = ?');
            $stmt->execute([$token]);
        } while ($stmt->fetchColumn() > 0);

        setcookie('watchmatch_auth_token', $token, time() + 60 * 60 * 24 * 30, '/', '', true, true);
        $stmt = $pdo->prepare('UPDATE ' . getenv('DB_TABLE_U') . ' SET auth_token = ? WHERE id = ?');
        $stmt->execute([$token, $user['id']]);

        header('Location: dashboard.php?login_cleanup=1');
        exit;
    }

    echo 'Invalid username or password.';
}
?>
