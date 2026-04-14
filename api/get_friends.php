<?php
require __DIR__ . '/bootstrap.php';

$currentUserId = apiRequireAuthenticatedUserId($pdo);
$usersTable = getenv('DB_TABLE_U');
$friendsTable = getenv('DB_TABLE_F');

$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.username, u.profil_icon, u.icon_color, u.icon_bg_color
    FROM {$friendsTable} f
    INNER JOIN {$friendsTable} f_back
        ON f_back.user_id_1 = f.user_id_2
       AND f_back.user_id_2 = f.user_id_1
    INNER JOIN {$usersTable} u
        ON u.id = f.user_id_2
    WHERE f.user_id_1 = ?
    ORDER BY u.username
");
$stmt->execute([$currentUserId]);

apiJsonResponse([
    'success' => true,
    'friends' => $stmt->fetchAll(),
]);
?>
