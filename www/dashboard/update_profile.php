<?php
file_put_contents('debug.txt', print_r($_POST, true), FILE_APPEND);
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
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection error.");
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pf_icon = $_POST['icon'];
    $pf_bg_color = ltrim($_POST['bg_color'], '#');
    $pf_icon_color = ltrim($_POST['icon_color'], '#');
    $region = $_POST['region'];
    if (isset($_COOKIE['watchmatch_auth_token'])) {
        $stmt = $pdo->prepare("SELECT id FROM " . getenv('DB_TABLE_U') . " WHERE auth_token = ?");
        $stmt->execute([$_COOKIE['watchmatch_auth_token']]);
        $user = $stmt->fetch();
    }
    file_put_contents('debug.txt', print_r($user, true));

    $stmt = $pdo->prepare("UPDATE " . getenv('DB_TABLE_U') . " SET profil_icon = ?, icon_color = ?, icon_bg_color = ?, region_id = ? WHERE id = ?");
    $stmt->execute([$pf_icon, $pf_icon_color, $pf_bg_color, $region, $user['id']]);

    header("Location: ../dashboard.php?p=settings");
    exit;
}
?>