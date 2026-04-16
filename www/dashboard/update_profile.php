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
$dbuser = getenv('DB_USER_NAME');
$dbpass = getenv('DB_PASSWORD');

try {
    $pdo = new PDO($dsn, $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection error.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php?p=settings');
    exit;
}

$allowedIcons = array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9'));
$pfIcon = (string) ($_POST['icon'] ?? 'a');
if (!in_array($pfIcon, $allowedIcons, true)) {
    $pfIcon = 'a';
}

$pfBgColor = ltrim((string) ($_POST['bg_color'] ?? '000000'), '#');
$pfIconColor = ltrim((string) ($_POST['icon_color'] ?? 'ffffff'), '#');
$region = (int) ($_POST['region'] ?? 0);

if (!preg_match('/^[0-9a-fA-F]{6}$/', $pfBgColor)) {
    $pfBgColor = '000000';
}

if (!preg_match('/^[0-9a-fA-F]{6}$/', $pfIconColor)) {
    $pfIconColor = 'ffffff';
}

if (!isset($_COOKIE['watchmatch_auth_token'])) {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM ' . getenv('DB_TABLE_U') . ' WHERE auth_token = ?');
$stmt->execute([$_COOKIE['watchmatch_auth_token']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->prepare('UPDATE ' . getenv('DB_TABLE_U') . ' SET profil_icon = ?, icon_color = ?, icon_bg_color = ?, region_id = ? WHERE id = ?');
$stmt->execute([$pfIcon, strtolower($pfIconColor), strtolower($pfBgColor), $region, $user['id']]);

header('Location: ../dashboard.php?p=settings');
exit;
?>
