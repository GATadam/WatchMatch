<?php
require __DIR__ . '/bootstrap.php';

$currentUserId = apiRequireAuthenticatedUserId($pdo);
$friendsTable = getenv('DB_TABLE_F');

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT f.user_id_1) AS cnt
    FROM {$friendsTable} f
    LEFT JOIN {$friendsTable} f_back
        ON f_back.user_id_1 = f.user_id_2
       AND f_back.user_id_2 = f.user_id_1
    WHERE f.user_id_2 = ?
      AND f_back.user_id_1 IS NULL
");
$stmt->execute([$currentUserId]);
$count = (int) $stmt->fetchColumn();

apiJsonResponse([
    'success' => true,
    'count' => $count,
]);
