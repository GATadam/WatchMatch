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
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection error.');
}

if (isset($_COOKIE['watchmatch_auth_token'])) {
    $stmt = $pdo->prepare('SELECT id FROM ' . getenv('DB_TABLE_U') . ' WHERE auth_token = ?');
    $stmt->execute([$_COOKIE['watchmatch_auth_token']]);
    $user = $stmt->fetch();
    if ($user) {
        header('Location: dashboard.php');
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
<body class="landing_body">
    <main class="landing_shell">
        <header class="landing_topbar">
            <a class="landing_brand" href="index.php">
                <img src="https://www.kosmicdoom.com/watchmatch_media/logo.png" alt="Watchmatch">
                <span>WATCHMATCH</span>
            </a>
        </header>

        <section class="landing_hero">
            <div class="landing_copy">
                <p class="landing_kicker">Shared movie planning</p>
                <h1>Stop asking "what should we watch?" and actually land on something together.</h1>
                <p class="landing_text">
                    Watchmatch helps friends compare providers, swipe through movies, and turn indecision into a real shared plan for the evening.
                </p>

                <div class="landing_actions">
                    <a class="btn landing_primary_action" href="login.php">Get Started</a>
                </div>

                <div class="landing_credits">
                    <span>A <a href="https://github.com/GATadam" target="_blank" rel="noreferrer">GATadam</a> x <a href="http://www.kosmicdoom.com/" target="_blank" rel="noreferrer">kosmicdoom</a> project</span>
                </div>
            </div>

            <div class="landing_showcase">
                <article class="landing_showcase_card">
                    <p class="landing_card_label">What makes it useful</p>
                    <h2>One flow from provider choice to final match.</h2>
                    <ul class="landing_feature_list">
                        <li>Choose one or more streaming providers from your region.</li>
                        <li>Open a room with a friend and swipe independently.</li>
                        <li>See the first real shared like and decide what to watch.</li>
                    </ul>
                </article>

                <article class="landing_showcase_card landing_showcase_card_accent">
                    <p class="landing_card_label">Powered by</p>
                    <div class="landing_tmdb_row">
                        <img id="tmdb-logo" src="https://www.themoviedb.org/assets/2/v4/logos/v2/blue_square_2-d537fb228cf3ded904ef09b136fe3fec72548ebc1fea3fbbd1ad9e36364db38b.svg" alt="TMDB">
                        <div>
                            <h3>TMDB data</h3>
                            <p>Movie discovery and metadata are based on The Movie Database.</p>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section class="landing_grid">
            <article class="landing_panel">
                <p class="landing_card_label">Friends first</p>
                <h2>Invite someone you already know</h2>
                <p>Rooms are built around your confirmed friends, so the experience stays focused on real shared plans, not random matchmaking.</p>
            </article>

            <article class="landing_panel">
                <p class="landing_card_label">Provider aware</p>
                <h2>Only see films that make sense</h2>
                <p>Selections are filtered by region and provider, so the match you find is something you can actually start watching.</p>
            </article>

            <article class="landing_panel">
                <p class="landing_card_label">Made for every screen</p>
                <h2>Plan from desktop or mobile</h2>
                <p>The refreshed interface is designed to stay clear and comfortable whether you are browsing on a laptop or swiping on a phone.</p>
            </article>
        </section>
    </main>
</body>
</html>
