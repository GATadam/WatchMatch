<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/app_bootstrap.php';

session_name('watchmatch_admin');
session_start();

watchmatchLoadEnv();

$adminUser = (string) getenv('ADMIN_USER');
$adminPassword = (string) getenv('ADMIN_PASSWORD');
$usersTable = getenv('DB_TABLE_U') ?: 'Users';
$regionsTable = getenv('DB_TABLE_R') ?: 'Regions';
$moviesTable = getenv('DB_TABLE_M') ?: 'Movies';
$providersTable = getenv('DB_TABLE_P') ?: 'Providers';
$w2wTable = getenv('DB_TABLE_W2W') ?: 'Where_to_watch';
$friendsTable = getenv('DB_TABLE_F') ?: 'Friends';
$watchedMoviesTable = 'Watched_movies';
$matchOnlineSwipesTable = 'Match_online_room_swipes';

function adminRedirect(string $query = ''): void
{
    $location = 'index.php';
    if ($query !== '') {
        $location .= '?' . $query;
    }

    header('Location: ' . $location);
    exit;
}

function adminFlash(?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['admin_flash'] = [
            'message' => $message,
            'type' => $type,
        ];
        return null;
    }

    if (!isset($_SESSION['admin_flash'])) {
        return null;
    }

    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
    return is_array($flash) ? $flash : null;
}

function adminIsAuthenticated(): bool
{
    return !empty($_SESSION['watchmatch_admin_authenticated']);
}

function adminEnsureCsrf(): string
{
    if (empty($_SESSION['watchmatch_admin_csrf'])) {
        $_SESSION['watchmatch_admin_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['watchmatch_admin_csrf'];
}

function adminVerifyCsrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && isset($_SESSION['watchmatch_admin_csrf'])
        && hash_equals((string) $_SESSION['watchmatch_admin_csrf'], $token);
}

function adminCount(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function adminFetchAll(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function adminFindUser(PDO $pdo, string $usersTable, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT id, username, email FROM {$usersTable} WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function adminDeleteUser(PDO $pdo, string $usersTable, string $friendsTable, string $watchedMoviesTable, int $userId): array
{
    $user = adminFindUser($pdo, $usersTable, $userId);
    if (!$user) {
        return [false, 'The selected user was not found.'];
    }

    $roomIds = adminFetchAll(
        $pdo,
        'SELECT id FROM Match_online_rooms WHERE host_user_id = ? OR guest_user_id = ?',
        [$userId, $userId]
    );
    $roomIds = array_map(static fn(array $row): int => (int) $row['id'], $roomIds);

    try {
        $pdo->beginTransaction();

        if ($roomIds) {
            $placeholders = implode(',', array_fill(0, count($roomIds), '?'));

            $stmt = $pdo->prepare("DELETE FROM Match_online_room_providers WHERE room_id IN ({$placeholders})");
            $stmt->execute($roomIds);

            $stmt = $pdo->prepare("DELETE FROM Match_online_room_swipes WHERE room_id IN ({$placeholders})");
            $stmt->execute($roomIds);

            $stmt = $pdo->prepare("DELETE FROM Match_online_rooms WHERE id IN ({$placeholders})");
            $stmt->execute($roomIds);
        }

        $stmt = $pdo->prepare('DELETE FROM Match_online_room_swipes WHERE user_id = ?');
        $stmt->execute([$userId]);

        $stmt = $pdo->prepare('DELETE FROM Blocked_users WHERE user_id_blocker = ? OR user_id_blocked = ?');
        $stmt->execute([$userId, $userId]);

        $stmt = $pdo->prepare("DELETE FROM {$friendsTable} WHERE user_id_1 = ? OR user_id_2 = ?");
        $stmt->execute([$userId, $userId]);

        $stmt = $pdo->prepare("DELETE FROM {$watchedMoviesTable} WHERE user_id = ?");
        $stmt->execute([$userId]);

        $stmt = $pdo->prepare("DELETE FROM {$usersTable} WHERE id = ?");
        $stmt->execute([$userId]);

        $pdo->commit();
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [false, 'The account could not be deleted right now.'];
    }

    return [true, 'Deleted account: ' . $user['username'] . ' (' . $user['email'] . ').'];
}

function adminPercentageWidth(int $value, int $max): float
{
    if ($max <= 0) {
        return 0.0;
    }

    return max(6.0, min(100.0, ($value / $max) * 100.0));
}

function adminResolveStatusLabel(string $status): string
{
    return match ($status) {
        'waiting' => 'Waiting',
        'active' => 'Active',
        'matched' => 'Matched',
        'closed' => 'Closed',
        default => ucfirst($status),
    };
}

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'admin_login') {
        $submittedUser = trim((string) ($_POST['admin_user'] ?? ''));
        $submittedPassword = (string) ($_POST['admin_password'] ?? '');

        if ($adminUser === '' || $adminPassword === '') {
            $loginError = 'Admin credentials are not configured in the environment.';
        } elseif (hash_equals($adminUser, $submittedUser) && hash_equals($adminPassword, $submittedPassword)) {
            session_regenerate_id(true);
            $_SESSION['watchmatch_admin_authenticated'] = true;
            adminEnsureCsrf();
            adminFlash('Admin session started.', 'success');
            adminRedirect();
        } else {
            $loginError = 'Invalid admin username or password.';
        }
    } elseif (adminIsAuthenticated()) {
        if (!adminVerifyCsrf()) {
            adminFlash('Your admin session token was invalid. Please try again.', 'error');
            adminRedirect();
        }

        if ($action === 'admin_logout') {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            session_name('watchmatch_admin');
            session_start();
            adminFlash('Admin session closed.', 'success');
            adminRedirect();
        }

        if ($action === 'delete_user') {
            try {
                $pdo = watchmatchCreatePdo();
                [$ok, $message] = adminDeleteUser(
                    $pdo,
                    $usersTable,
                    $friendsTable,
                    $watchedMoviesTable,
                    (int) ($_POST['target_user_id'] ?? 0)
                );
                adminFlash($message, $ok ? 'success' : 'error');
            } catch (Throwable $throwable) {
                adminFlash('The account could not be deleted because the database was unavailable.', 'error');
            }

            $query = trim((string) ($_POST['return_query'] ?? ''));
            adminRedirect($query !== '' ? 'q=' . urlencode($query) : '');
        }
    }
}

if (!adminIsAuthenticated()) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WATCHMATCH Admin</title>
    <link rel="icon" type="image/x-icon" href="https://www.kosmicdoom.com/watchmatch_media/logo.png">
    <link rel="stylesheet" href="../styles/main_style.css">
    <link rel="stylesheet" href="../styles/admin_style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
</head>
<body class="admin_body">
    <main class="admin_login_shell">
        <section class="admin_login_card">
            <a class="admin_brand" href="../index.php">
                <img src="https://www.kosmicdoom.com/watchmatch_media/logo.png" alt="Watchmatch">
                <span>WATCHMATCH</span>
            </a>
            <p class="admin_kicker">Admin access</p>
            <h1>Monitor the platform in one place.</h1>
            <p class="admin_text">Use the environment-backed admin credentials to review activity, inspect users, and remove accounts when needed.</p>

            <form method="POST" class="admin_login_form">
                <input type="hidden" name="action" value="admin_login">

                <div class="admin_field">
                    <label for="admin_user">Admin user</label>
                    <input type="text" id="admin_user" name="admin_user" autocomplete="username" required>
                </div>

                <div class="admin_field">
                    <label for="admin_password">Admin password</label>
                    <input type="password" id="admin_password" name="admin_password" autocomplete="current-password" required>
                </div>

                <button type="submit" class="admin_button">Enter admin dashboard</button>
            </form>

            <?php if ($loginError !== ''): ?>
                <div class="admin_notice is-error"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
    <?php
    exit;
}

$flash = adminFlash();
$csrfToken = adminEnsureCsrf();
$searchQuery = trim((string) ($_GET['q'] ?? ''));

try {
    $pdo = watchmatchCreatePdo();
} catch (Throwable $throwable) {
    http_response_code(500);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WATCHMATCH Admin</title>
    <link rel="stylesheet" href="../styles/main_style.css">
    <link rel="stylesheet" href="../styles/admin_style.css">
</head>
<body class="admin_body">
    <main class="admin_login_shell">
        <section class="admin_login_card">
            <p class="admin_kicker">Database unavailable</p>
            <h1>The admin dashboard could not connect right now.</h1>
            <p class="admin_text">Please verify the database settings in the environment and try again.</p>
        </section>
    </main>
</body>
</html>
    <?php
    exit;
}

$userCount = adminCount($pdo, "SELECT COUNT(*) FROM {$usersTable}");
$verifiedUserCount = adminCount($pdo, "SELECT COUNT(*) FROM {$usersTable} WHERE email_verified = 1");
$roomCount = adminCount($pdo, 'SELECT COUNT(*) FROM Match_online_rooms');
$activeRoomCount = adminCount($pdo, "SELECT COUNT(*) FROM Match_online_rooms WHERE status IN ('waiting', 'active', 'matched')");
$watchedEntryCount = adminCount($pdo, "SELECT COUNT(*) FROM {$watchedMoviesTable}");
$confirmedFriendshipCount = adminCount(
    $pdo,
    "
    SELECT COUNT(*)
    FROM {$friendsTable} f1
    INNER JOIN {$friendsTable} f2
        ON f2.user_id_1 = f1.user_id_2
       AND f2.user_id_2 = f1.user_id_1
    WHERE f1.user_id_1 < f1.user_id_2
    "
);

$topMovies = adminFetchAll(
    $pdo,
    "
    SELECT m.id, m.title, m.picture, m.popularity, COUNT(wm.id) AS watch_count
    FROM {$moviesTable} m
    INNER JOIN {$watchedMoviesTable} wm
        ON wm.movie_id = m.id
    GROUP BY m.id, m.title, m.picture, m.popularity
    ORDER BY watch_count DESC, m.popularity DESC, m.id DESC
    LIMIT 6
    "
);

$topDislikedMovies = adminFetchAll(
    $pdo,
    "
    SELECT m.id, m.title, m.picture, m.popularity, COUNT(mors.id) AS dislike_count
    FROM {$moviesTable} m
    INNER JOIN {$matchOnlineSwipesTable} mors
        ON mors.movie_id = m.id
    WHERE mors.swipe = 'left'
    GROUP BY m.id, m.title, m.picture, m.popularity
    ORDER BY dislike_count DESC, m.popularity DESC, m.id DESC
    LIMIT 6
    "
);

$topProviders = adminFetchAll(
    $pdo,
    "
    SELECT MIN(p.logo) AS logo, p.name, COUNT(DISTINCT rp.room_id) AS room_count
    FROM Match_online_room_providers rp
    INNER JOIN {$providersTable} p
        ON p.id = rp.provider_id
    GROUP BY p.name
    ORDER BY room_count DESC, p.name ASC
    LIMIT 8
    "
);

$roomStatuses = adminFetchAll(
    $pdo,
    '
    SELECT status, COUNT(*) AS total
    FROM Match_online_rooms
    GROUP BY status
    ORDER BY total DESC, status ASC
    '
);

$topRegions = adminFetchAll(
    $pdo,
    "
    SELECT r.name, COUNT(*) AS total
    FROM {$usersTable} u
    INNER JOIN {$regionsTable} r
        ON r.id = u.region_id
    GROUP BY r.id, r.name
    ORDER BY total DESC, r.name ASC
    LIMIT 8
    "
);

$searchSql = "
    SELECT
        u.id,
        u.username,
        u.email,
        u.email_verified,
        r.name AS region_name,
        COALESCE(wm.watched_count, 0) AS watched_count,
        COALESCE(rooms.room_count, 0) AS room_count
    FROM {$usersTable} u
    LEFT JOIN {$regionsTable} r
        ON r.id = u.region_id
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS watched_count
        FROM {$watchedMoviesTable}
        GROUP BY user_id
    ) wm
        ON wm.user_id = u.id
    LEFT JOIN (
        SELECT participant.user_id, COUNT(*) AS room_count
        FROM (
            SELECT host_user_id AS user_id FROM Match_online_rooms
            UNION ALL
            SELECT guest_user_id AS user_id FROM Match_online_rooms WHERE guest_user_id IS NOT NULL
        ) participant
        GROUP BY participant.user_id
    ) rooms
        ON rooms.user_id = u.id
";
$searchParams = [];
if ($searchQuery !== '') {
    $searchSql .= ' WHERE u.username LIKE ? OR u.email LIKE ?';
    $searchParams[] = '%' . $searchQuery . '%';
    $searchParams[] = '%' . $searchQuery . '%';
}
$searchSql .= ' ORDER BY u.id DESC LIMIT 40';
$users = adminFetchAll($pdo, $searchSql, $searchParams);

$maxTopMovieCount = 0;
foreach ($topMovies as $row) {
    $maxTopMovieCount = max($maxTopMovieCount, (int) $row['watch_count']);
}

$maxTopDislikedMovieCount = 0;
foreach ($topDislikedMovies as $row) {
    $maxTopDislikedMovieCount = max($maxTopDislikedMovieCount, (int) $row['dislike_count']);
}

$maxProviderRoomCount = 0;
foreach ($topProviders as $row) {
    $maxProviderRoomCount = max($maxProviderRoomCount, (int) $row['room_count']);
}

$maxStatusCount = 0;
foreach ($roomStatuses as $row) {
    $maxStatusCount = max($maxStatusCount, (int) $row['total']);
}

$maxRegionCount = 0;
foreach ($topRegions as $row) {
    $maxRegionCount = max($maxRegionCount, (int) $row['total']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WATCHMATCH Admin</title>
    <link rel="icon" type="image/x-icon" href="https://www.kosmicdoom.com/watchmatch_media/logo.png">
    <link rel="stylesheet" href="../styles/main_style.css">
    <link rel="stylesheet" href="../styles/admin_style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
</head>
<body class="admin_body">
    <main class="admin_shell">
        <header class="admin_header">
            <div>
                <p class="admin_kicker">Admin dashboard</p>
                <h1>Watchmatch platform overview</h1>
            </div>

            <div class="admin_header_actions">
                <a class="admin_button_secondary" href="../index.php">Back to site</a>
                <form method="POST">
                    <input type="hidden" name="action" value="admin_logout">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="admin_button">Logout</button>
                </form>
            </div>
        </header>

        <?php if ($flash): ?>
            <div class="admin_notice <?php echo $flash['type'] === 'error' ? 'is-error' : 'is-success'; ?>">
                <?php echo htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <section class="admin_dashboard">
            <div class="admin_metrics_grid">
                <article class="admin_card">
                    <p class="admin_metric_label">User count</p>
                    <p class="admin_metric_value"><?php echo number_format($userCount); ?></p>
                    <div class="admin_stat_row">
                        <span>Verified</span>
                        <strong><?php echo number_format($verifiedUserCount); ?></strong>
                    </div>
                </article>

                <article class="admin_card">
                    <p class="admin_metric_label">Rooms created</p>
                    <p class="admin_metric_value"><?php echo number_format($roomCount); ?></p>
                    <div class="admin_stat_row">
                        <span>Currently open</span>
                        <strong><?php echo number_format($activeRoomCount); ?></strong>
                    </div>
                </article>

                <article class="admin_card">
                    <p class="admin_metric_label">Watched entries</p>
                    <p class="admin_metric_value"><?php echo number_format($watchedEntryCount); ?></p>
                    <div class="admin_stat_row">
                        <span>Confirmed friendships</span>
                        <strong><?php echo number_format($confirmedFriendshipCount); ?></strong>
                    </div>
                </article>

                <article class="admin_card">
                    <p class="admin_metric_label">Verification rate</p>
                    <p class="admin_metric_value">
                        <?php echo $userCount > 0 ? number_format(($verifiedUserCount / $userCount) * 100, 1) : '0.0'; ?>%
                    </p>
                    <div class="admin_stat_row">
                        <span>Unverified users</span>
                        <strong><?php echo number_format(max(0, $userCount - $verifiedUserCount)); ?></strong>
                    </div>
                </article>
            </div>

            <div class="admin_charts_grid">
                <article class="admin_card">
                    <p class="admin_kicker">Room activity</p>
                    <h2>Room status distribution</h2>
                    <div class="admin_bar_chart">
                        <?php if ($roomStatuses): ?>
                            <?php foreach ($roomStatuses as $status): ?>
                                <?php $count = (int) $status['total']; ?>
                                <div class="admin_bar_row">
                                    <div class="admin_bar_label">
                                        <span><?php echo htmlspecialchars(adminResolveStatusLabel((string) $status['status']), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span><?php echo number_format($count); ?></span>
                                    </div>
                                    <div class="admin_bar_track">
                                        <div class="admin_bar_fill" style="width: <?php echo number_format(adminPercentageWidth($count, $maxStatusCount), 2, '.', ''); ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="admin_text">No rooms have been created yet.</p>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="admin_card">
                    <p class="admin_kicker">Audience</p>
                    <h2>Users by region</h2>
                    <div class="admin_bar_chart">
                        <?php if ($topRegions): ?>
                            <?php foreach ($topRegions as $region): ?>
                                <?php $count = (int) $region['total']; ?>
                                <div class="admin_bar_row">
                                    <div class="admin_bar_label">
                                        <span><?php echo htmlspecialchars((string) $region['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span><?php echo number_format($count); ?></span>
                                    </div>
                                    <div class="admin_bar_track">
                                        <div class="admin_bar_fill" style="width: <?php echo number_format(adminPercentageWidth($count, $maxRegionCount), 2, '.', ''); ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="admin_text">No user region data is available yet.</p>
                        <?php endif; ?>
                    </div>
                </article>
            </div>

            <div class="admin_charts_grid">
                <article class="admin_card">
                    <p class="admin_kicker">Trending films</p>
                    <h2>Most watched movies</h2>
                    <div class="admin_movie_grid">
                        <?php if ($topMovies): ?>
                            <?php foreach ($topMovies as $movie): ?>
                                <article class="admin_movie_card">
                                    <div class="admin_movie_poster">
                                        <?php $posterUrl = watchmatchResolveMediaUrl((string) $movie['picture']); ?>
                                        <?php if ($posterUrl !== ''): ?>
                                            <img src="<?php echo htmlspecialchars($posterUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $movie['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="admin_movie_content">
                                        <h3 class="admin_movie_title"><?php echo htmlspecialchars((string) $movie['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <div class="admin_bar_track">
                                            <div class="admin_bar_fill" style="width: <?php echo number_format(adminPercentageWidth((int) $movie['watch_count'], $maxTopMovieCount), 2, '.', ''); ?>%;"></div>
                                        </div>
                                        <div class="admin_stat_row">
                                            <span>Watch count</span>
                                            <strong><?php echo number_format((int) $movie['watch_count']); ?></strong>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="admin_text">No watched movie data is available yet.</p>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="admin_card">
                    <p class="admin_kicker">Swipe activity</p>
                    <h2>Most disliked movies</h2>
                    <div class="admin_movie_grid">
                        <?php if ($topDislikedMovies): ?>
                            <?php foreach ($topDislikedMovies as $movie): ?>
                                <article class="admin_movie_card">
                                    <div class="admin_movie_poster">
                                        <?php $posterUrl = watchmatchResolveMediaUrl((string) $movie['picture']); ?>
                                        <?php if ($posterUrl !== ''): ?>
                                            <img src="<?php echo htmlspecialchars($posterUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $movie['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="admin_movie_content">
                                        <h3 class="admin_movie_title"><?php echo htmlspecialchars((string) $movie['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <div class="admin_bar_track">
                                            <div class="admin_bar_fill" style="width: <?php echo number_format(adminPercentageWidth((int) $movie['dislike_count'], $maxTopDislikedMovieCount), 2, '.', ''); ?>%;"></div>
                                        </div>
                                        <div class="admin_stat_row">
                                            <span>Left swipes</span>
                                            <strong><?php echo number_format((int) $movie['dislike_count']); ?></strong>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="admin_text">No disliked movie data is available yet.</p>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="admin_card">
                    <p class="admin_kicker">Provider usage</p>
                    <h2>Most selected providers for rooms</h2>
                    <?php if ($topProviders): ?>
                        <?php
                        $providerColorMap = [
                            'netflix' => '#e50914',
                            'disney plus' => '#1d6dce',
                            'disney+' => '#1d6dce',
                            'hbo max' => '#1a1a2e',
                            'hbo' => '#1a1a2e',
                            'max' => '#1a1a2e',
                            'amazon prime video' => '#f0f0f0',
                            'prime video' => '#f0f0f0',
                        ];
                        $fallbackColors = ['#2ac6d9', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16'];
                        $fallbackIndex = 0;

                        $totalRoomCount = 0;
                        foreach ($topProviders as $p) {
                            $totalRoomCount += (int) $p['room_count'];
                        }

                        $providerSlices = [];
                        foreach ($topProviders as $p) {
                            $name = (string) $p['name'];
                            $nameLower = strtolower(trim($name));
                            if (isset($providerColorMap[$nameLower])) {
                                $color = $providerColorMap[$nameLower];
                            } else {
                                $color = $fallbackColors[$fallbackIndex % count($fallbackColors)];
                                $fallbackIndex++;
                            }
                            $providerSlices[] = [
                                'name' => $name,
                                'count' => (int) $p['room_count'],
                                'logo' => (string) $p['logo'],
                                'color' => $color,
                            ];
                        }
                        ?>
                        <?php
                        function adminAdjustHex(string $hex, int $pct): string {
                            $hex = ltrim($hex, '#');
                            if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
                            $r = hexdec(substr($hex,0,2));
                            $g = hexdec(substr($hex,2,2));
                            $b = hexdec(substr($hex,4,2));
                            if ($pct > 0) {
                                $r += (int)((255-$r)*$pct/100);
                                $g += (int)((255-$g)*$pct/100);
                                $b += (int)((255-$b)*$pct/100);
                            } else {
                                $r = (int)($r*(100+$pct)/100);
                                $g = (int)($g*(100+$pct)/100);
                                $b = (int)($b*(100+$pct)/100);
                            }
                            return sprintf('#%02x%02x%02x', max(0,min(255,$r)), max(0,min(255,$g)), max(0,min(255,$b)));
                        }
                        ?>
                        <div class="admin_pie_container">
                            <svg class="admin_pie_svg" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <?php foreach ($providerSlices as $gi => $gs): ?>
                                        <linearGradient id="pie-grad-<?php echo $gi; ?>" x1="0" y1="0" x2="1" y2="1">
                                            <stop offset="0%"   stop-color="<?php echo adminAdjustHex($gs['color'], 25); ?>"/>
                                            <stop offset="100%" stop-color="<?php echo adminAdjustHex($gs['color'], -20); ?>"/>
                                        </linearGradient>
                                    <?php endforeach; ?>
                                </defs>
                                <?php
                                $cumulative = 0;
                                foreach ($providerSlices as $si => $slice):
                                    $fraction = $totalRoomCount > 0 ? $slice['count'] / $totalRoomCount : 0;
                                    $dashArray = $fraction * 314.159;
                                    $dashOffset = -$cumulative * 314.159;
                                    $cumulative += $fraction;
                                ?>
                                    <circle
                                        cx="100" cy="100" r="50"
                                        fill="none"
                                        stroke="url(#pie-grad-<?php echo $si; ?>)"
                                        stroke-width="58"
                                        stroke-dasharray="<?php echo number_format($dashArray, 4, '.', ''); ?> 314.1593"
                                        stroke-dashoffset="<?php echo number_format($dashOffset, 4, '.', ''); ?>"
                                        transform="rotate(-90 100 100)"
                                    />
                                <?php endforeach; ?>
                                <circle cx="100" cy="100" r="80" fill="none" stroke="rgba(42,198,217,0.30)" stroke-width="1.2"/>
                                <circle cx="100" cy="100" r="21" fill="none" stroke="rgba(42,198,217,0.30)" stroke-width="1.2"/>
                            </svg>
                            <div class="admin_pie_legend">
                                <?php foreach ($providerSlices as $slice): ?>
                                    <?php $logoUrl = watchmatchResolveMediaUrl($slice['logo']); ?>
                                    <div class="admin_pie_legend_item">
                                        <span class="admin_pie_legend_dot" style="background: <?php echo htmlspecialchars($slice['color'], ENT_QUOTES, 'UTF-8'); ?>;<?php echo strtolower(trim($slice['color'])) === '#f0f0f0' || strtolower(trim($slice['color'])) === '#ffffff' ? ' border: 1px solid rgba(148,163,184,0.4);' : ''; ?>"></span>
                                        <?php if ($logoUrl !== ''): ?>
                                            <img class="admin_pie_legend_logo" src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($slice['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php else: ?>
                                            <span class="admin_pie_legend_name"><?php echo htmlspecialchars($slice['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                        <span class="admin_pie_legend_count">(<?php echo number_format($slice['count']); ?>)</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="admin_text" style="margin-top:18px;">No provider selections have been recorded yet.</p>
                    <?php endif; ?>
                </article>
            </div>

            <article class="admin_card">
                <div class="admin_users_header">
                    <div>
                        <p class="admin_kicker">User management</p>
                        <h2>Find and review users</h2>
                        <p class="admin_table_meta">Search by username or email, inspect their activity, and remove accounts when needed.</p>
                    </div>

                    <form method="GET" class="admin_search_form">
                        <input
                            type="search"
                            name="q"
                            class="admin_search_input"
                            placeholder="Search username or email"
                            value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                        <button type="submit" class="admin_button">Search</button>
                        <?php if ($searchQuery !== ''): ?>
                            <a class="admin_button_secondary" href="index.php">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="admin_table_wrap">
                    <table class="admin_table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Region</th>
                                <th>Email status</th>
                                <th>Watched</th>
                                <th>Rooms</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users): ?>
                                <?php foreach ($users as $listedUser): ?>
                                    <tr>
                                        <td>
                                            <div class="admin_user_meta">
                                                <strong><?php echo htmlspecialchars((string) $listedUser['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <span><?php echo htmlspecialchars((string) $listedUser['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars((string) ($listedUser['region_name'] ?? 'Unknown region'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <span class="admin_status_pill <?php echo (int) $listedUser['email_verified'] === 1 ? 'is-success' : 'is-muted'; ?>">
                                                <?php echo (int) $listedUser['email_verified'] === 1 ? 'Verified' : 'Pending'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format((int) $listedUser['watched_count']); ?></td>
                                        <td><?php echo number_format((int) $listedUser['room_count']); ?></td>
                                        <td>
                                            <form method="POST" onsubmit="return window.confirm('Delete this account and all related room/friend/watch data?');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="target_user_id" value="<?php echo (int) $listedUser['id']; ?>">
                                                <input type="hidden" name="return_query" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="admin_delete_button">Delete account</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">No users matched this search.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </main>
</body>
</html>
