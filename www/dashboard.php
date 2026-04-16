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

if (!isset($_COOKIE['watchmatch_auth_token'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM ' . getenv('DB_TABLE_U') . ' WHERE auth_token = ?');
$stmt->execute([$_COOKIE['watchmatch_auth_token']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT username, profil_icon, icon_color, icon_bg_color FROM ' . getenv('DB_TABLE_U') . ' WHERE id = ?');
$stmt->execute([$user['id']]);
$userData = $stmt->fetch();

if (!$userData) {
    header('Location: login.php');
    exit;
}

$username = htmlspecialchars($userData['username'], ENT_QUOTES, 'UTF-8');
$profile_icon = htmlspecialchars($userData['profil_icon'], ENT_QUOTES, 'UTF-8');
$profile_icon_color = htmlspecialchars($userData['icon_color'], ENT_QUOTES, 'UTF-8');
$profile_icon_bg_color = htmlspecialchars($userData['icon_bg_color'], ENT_QUOTES, 'UTF-8');

$allowedPages = ['landing_page', 'settings', 'friends', 'match_online'];
$page = $_GET['p'] ?? 'landing_page';
if (!in_array($page, $allowedPages, true)) {
    $page = 'landing_page';
}

$pageTitles = [
    'landing_page' => 'Home',
    'settings' => 'Profile',
    'friends' => 'Friends',
    'match_online' => 'Match Online',
];

$navigationItems = [
    'landing_page' => 'Home',
    'settings' => 'Profile',
    'friends' => 'Friends',
    'match_online' => 'Match Online',
];
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
    <link rel="stylesheet" href="fonts/be_happy_font.css">
</head>
<body class="dashboard_body">
    <div class="dashboard_shell">
        <header class="dashboard_topbar">
            <button
                type="button"
                id="dashboard_menu_toggle"
                class="dashboard_menu_toggle"
                aria-expanded="false"
                aria-controls="dashboard_sidebar"
                aria-label="Open navigation"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>

            <a class="dashboard_topbar_brand" href="?p=landing_page">
                <img src="https://www.kosmicdoom.com/watchmatch_media/logo.png" alt="Watchmatch">
                <span>WATCHMATCH</span>
            </a>

            <p class="dashboard_topbar_title"><?php echo htmlspecialchars($pageTitles[$page], ENT_QUOTES, 'UTF-8'); ?></p>
        </header>

        <div id="dashboard_backdrop" class="dashboard_backdrop" hidden></div>

        <div class="dashboard_layout">
            <aside id="dashboard_sidebar" class="dashboard_sidebar" aria-label="Dashboard navigation">
                <div class="dashboard_sidebar_inner">
                    <a class="dashboard_brand" href="?p=landing_page">
                        <div class="dashboard_brand_mark">
                            <img src="https://www.kosmicdoom.com/watchmatch_media/logo.png" alt="Watchmatch">
                        </div>
                        <div>
                            <p class="dashboard_brand_label">Watchmatch</p>
                            <h1>Dashboard</h1>
                        </div>
                    </a>

                    <div class="dashboard_user_card">
                        <span id="profile_icon" style="color: #<?php echo $profile_icon_color; ?>; background-color: #<?php echo $profile_icon_bg_color; ?>;"><?php echo $profile_icon; ?></span>
                        <div>
                            <p class="dashboard_user_label">Signed in as</p>
                            <p id="username"><?php echo $username; ?></p>
                        </div>
                    </div>

                    <nav class="dashboard_nav">
                        <?php foreach ($navigationItems as $slug => $label): ?>
                            <a href="?p=<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $page === $slug ? 'is-active' : ''; ?>">
                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>

                    <div class="dashboard_sidebar_footer">
                        <a class="dashboard_logout" href="logout.php">Logout</a>
                        <div class="dashboard_logo_badge">
                            <img src="https://www.kosmicdoom.com/watchmatch_media/logo.png" alt="Watchmatch">
                        </div>
                    </div>
                </div>
            </aside>

            <main class="dashboard_main">
                <?php include 'dashboard/' . $page . '.php'; ?>
            </main>
        </div>
    </div>

    <script>
    (() => {
        const menuToggle = document.getElementById('dashboard_menu_toggle');
        const sidebar = document.getElementById('dashboard_sidebar');
        const backdrop = document.getElementById('dashboard_backdrop');
        const navLinks = Array.from(document.querySelectorAll('.dashboard_nav a'));
        const mobileBreakpoint = 960;

        function isMobileLayout() {
            return window.innerWidth <= mobileBreakpoint;
        }

        function openMenu() {
            if (!isMobileLayout()) {
                return;
            }

            sidebar.classList.add('is-open');
            backdrop.hidden = false;
            menuToggle.setAttribute('aria-expanded', 'true');
            document.body.classList.add('dashboard_menu_open');
        }

        function closeMenu() {
            sidebar.classList.remove('is-open');
            backdrop.hidden = true;
            menuToggle.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('dashboard_menu_open');
        }

        menuToggle.addEventListener('click', () => {
            if (sidebar.classList.contains('is-open')) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        backdrop.addEventListener('click', closeMenu);

        navLinks.forEach((link) => {
            link.addEventListener('click', () => {
                if (isMobileLayout()) {
                    closeMenu();
                }
            });
        });

        window.addEventListener('resize', () => {
            if (!isMobileLayout()) {
                closeMenu();
            }
        });

        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
                closeMenu();
            }
        });
    })();
    </script>
</body>
</html>
