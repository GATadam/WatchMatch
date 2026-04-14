<?php
require __DIR__ . '/bootstrap.php';

$currentUserId = apiRequireAuthenticatedUserId($pdo);
$usersTable = getenv('DB_TABLE_U');
$query = trim((string) apiRequestValue('query', ''));

if ($query === '') {
    apiJsonResponse([
        'success' => true,
        'results' => [],
    ]);
}

$stmt = $pdo->prepare("
    SELECT id, username, profil_icon, icon_color, icon_bg_color
    FROM {$usersTable}
    WHERE id <> ?
      AND username LIKE ?
    ORDER BY username
    LIMIT 20
");
$stmt->execute([$currentUserId, '%' . $query . '%']);

apiJsonResponse([
    'success' => true,
    'results' => $stmt->fetchAll(),
]);
?>
