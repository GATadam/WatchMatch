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
        $stmt = $pdo->prepare("SELECT username, profil_icon, icon_color, icon_bg_color FROM " . getenv('DB_TABLE_U') . " WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch();
        if ($userData) {
            $username = htmlspecialchars($userData['username'], ENT_QUOTES, 'UTF-8');
            $profile_icon = htmlspecialchars($userData['profil_icon'], ENT_QUOTES, 'UTF-8');
            $profile_icon_color = htmlspecialchars($userData['icon_color'], ENT_QUOTES, 'UTF-8');
            $profile_icon_bg_color = htmlspecialchars($userData['icon_bg_color'], ENT_QUOTES, 'UTF-8');
        } else {
            header("Location: login.html");
            exit;
        }
    }
} else {
    header("Location: login.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WATCHMATCH - Dashboard</title>
    <link rel="icon" type="image/x-icon" href="https://www.kosmicdoom.com/watchmatch_media/logo.png">
    <link rel="stylesheet" href="styles/main_style.css">
    <link rel="stylesheet" href="styles/dashboard_style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <!-- betűtípus az ikonokhoz, most ez egy placeholder -->
    <link rel="stylesheet" href="fonts/be_happy_font.css">
</head>
<body>
    <div class='header'>
        <h1>WATCHMATCH</h1>
    </div>
    <div id="side_menu">
        <table>
            <tr>
                <th><span id="profile_icon" style="color: #<?php echo $profile_icon_color; ?> !important; background-color: #<?php echo $profile_icon_bg_color; ?> !important;"><?php echo $profile_icon; ?></span></th>
            </tr>
            <tr>
                <td id="username"><?php echo $username; ?></td>
            </tr>
            <tr>
                <td><a href="?p=settings">Profile</a></td>
            </tr>
            <tr>
                <td><a href="?p=friends">Friends</a></td>
            </tr>
            <tr>
                <td><a href="?p=match_local">Match locally</a></td>
            </tr>
            <tr>
                <td><a href="?p=match_online">Match online</a></td>
            </tr>
            <tr>
                <td id="logout"><a href="logout.php">Logout</a></td>
            </tr>
        </table>
    </div>

    <?php
    $page = $_GET['p'];
    $allowed_pages = ['settings', 'friends', 'match_local', 'match_online', 'landing_page'];
    if (in_array($page, $allowed_pages)) {
        include('dashboard/' . $page . '.php');
    } else {
        // TODO
        //include('dashboard/landing_page.php');
    }
    ?>

    <div id="logo">
        <img src="https://www.kosmicdoom.com/watchmatch_media/logo.png" alt="Watchmatch">
    </div>
</body>
</html>