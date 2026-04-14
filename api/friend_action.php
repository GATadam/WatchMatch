<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJsonResponse([
        'success' => false,
        'message' => 'Invalid request method.',
    ], 405);
}

$currentUserId = apiRequireAuthenticatedUserId($pdo);
$friendsTable = getenv('DB_TABLE_F');
$action = trim((string) apiRequestValue('friend_action', ''));
$targetUserId = (int) apiRequestValue('target_user_id', 0);

if ($targetUserId <= 0 || $targetUserId === $currentUserId) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Invalid target user.',
    ], 400);
}

try {
    switch ($action) {
        case 'send_request':
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM {$friendsTable}
                WHERE user_id_1 = ? AND user_id_2 = ?
            ");
            $stmt->execute([$currentUserId, $targetUserId]);

            if ((int) $stmt->fetchColumn() > 0) {
                apiJsonResponse([
                    'success' => false,
                    'message' => 'A friend request already exists for this user.',
                ], 409);
            }

            $stmt = $pdo->prepare("
                INSERT INTO {$friendsTable} (user_id_1, user_id_2)
                VALUES (?, ?)
            ");
            $stmt->execute([$currentUserId, $targetUserId]);

            apiJsonResponse([
                'success' => true,
                'message' => 'Friend request sent.',
            ]);
            break;

        case 'accept_request':
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM {$friendsTable}
                WHERE user_id_1 = ? AND user_id_2 = ?
            ");
            $stmt->execute([$targetUserId, $currentUserId]);
            $incomingExists = (int) $stmt->fetchColumn() > 0;

            if (!$incomingExists) {
                apiJsonResponse([
                    'success' => false,
                    'message' => 'This friend request is no longer available.',
                ], 404);
            }

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM {$friendsTable}
                WHERE user_id_1 = ? AND user_id_2 = ?
            ");
            $stmt->execute([$currentUserId, $targetUserId]);
            $acceptedAlready = (int) $stmt->fetchColumn() > 0;

            if (!$acceptedAlready) {
                $stmt = $pdo->prepare("
                    INSERT INTO {$friendsTable} (user_id_1, user_id_2)
                    VALUES (?, ?)
                ");
                $stmt->execute([$currentUserId, $targetUserId]);
            }

            apiJsonResponse([
                'success' => true,
                'message' => 'Friend request accepted.',
            ]);
            break;

        case 'decline_request':
            $stmt = $pdo->prepare("
                DELETE FROM {$friendsTable}
                WHERE user_id_1 = ? AND user_id_2 = ?
            ");
            $stmt->execute([$targetUserId, $currentUserId]);

            apiJsonResponse([
                'success' => true,
                'message' => 'Friend request declined.',
            ]);
            break;

        case 'cancel_request':
            $stmt = $pdo->prepare("
                DELETE FROM {$friendsTable}
                WHERE user_id_1 = ? AND user_id_2 = ?
            ");
            $stmt->execute([$currentUserId, $targetUserId]);

            apiJsonResponse([
                'success' => true,
                'message' => 'Outgoing friend request cancelled.',
            ]);
            break;

        case 'remove_friend':
            $stmt = $pdo->prepare("
                DELETE FROM {$friendsTable}
                WHERE (user_id_1 = ? AND user_id_2 = ?)
                   OR (user_id_1 = ? AND user_id_2 = ?)
            ");
            $stmt->execute([$currentUserId, $targetUserId, $targetUserId, $currentUserId]);

            apiJsonResponse([
                'success' => true,
                'message' => 'Friend removed.',
            ]);
            break;

        default:
            apiJsonResponse([
                'success' => false,
                'message' => 'Unknown friend action.',
            ], 400);
    }
} catch (PDOException $e) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Could not update friend data right now.',
    ], 500);
}
?>
