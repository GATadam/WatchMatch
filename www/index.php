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

if (isset($_COOKIE['watchmatch_auth_token'])) {
    $stmt = $pdo->prepare("SELECT id FROM " . getenv('DB_TABLE_U') . " WHERE auth_token = ?");
    $stmt->execute([$_COOKIE['watchmatch_auth_token']]);
    $user = $stmt->fetch();
    if ($user) {
        header("Location: dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WATCHMATCH</title>
    <link rel="icon" type="image/x-icon" href="https://www.kosmicdoom.com/watchmatch_media/logo.png">
    <link rel="stylesheet" href="styles/main_style.css">
    <link rel="stylesheet" href="styles/index_style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet"> 
</head>
<body>
    <div class='header'>
        <h1>WATCHMATCH</h1>

        <div class='brands'>
            A <a href="https://github.com/GATadam" target='_blank'>GATadam</a> x <a href="http://www.kosmicdoom.com/" target='_blank'>kosmicdoom</a> project
        </div>
        <p>Using</p>
        <div class='tmdb_logo_style'>
            <a href="https://www.themoviedb.org/" target='_blank'><img id="tmdb-logo" src="https://www.themoviedb.org/assets/2/v4/logos/v2/blue_square_2-d537fb228cf3ded904ef09b136fe3fec72548ebc1fea3fbbd1ad9e36364db38b.svg" alt=""></a>
        </div>

        <div class='login_btn_container'>
            <button type="submit" class="btn" onclick="location.href='login.html';">Login</button>
        </div>    
    </div>
    <div id="logo">
        <img src="https://www.kosmicdoom.com/watchmatch_media/logo.png" alt="Watchmatch">
    </div>
</body>
</html>