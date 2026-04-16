<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/match_online_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJsonResponse([
        'success' => false,
        'message' => 'Invalid request method.',
    ], 405);
}

$currentUserId = apiRequireAuthenticatedUserId($pdo);
$roomId = (int) apiRequestValue('room_id', 0);
$decision = trim((string) apiRequestValue('decision', ''));

if ($roomId <= 0 || !in_array($decision, ['keep_swiping', 'lets_watch'], true)) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Invalid room decision.',
    ], 400);
}

$room = matchOnlineRequireRoomMembership($pdo, $roomId, $currentUserId);
if ($room['status'] !== 'matched' || empty($room['matched_movie_id']) || empty($room['guest_user_id'])) {
    apiJsonResponse([
        'success' => false,
        'message' => 'There is no active match to decide on.',
    ], 409);
}

$isHost = (int) $room['host_user_id'] === $currentUserId;
$selfColumn = $isHost ? 'host_decision' : 'guest_decision';

$message = 'Decision saved.';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "UPDATE Match_online_rooms SET {$selfColumn} = ? WHERE id = ?"
    );
    $stmt->execute([$decision, $roomId]);

    $room = matchOnlineGetRoomById($pdo, $roomId);

    if (!empty($room['host_decision']) && !empty($room['guest_decision'])) {
        if ($room['host_decision'] === $room['guest_decision']) {
            if ($room['host_decision'] === 'keep_swiping') {
                $stmt = $pdo->prepare(
                    "
                    UPDATE Match_online_rooms
                    SET status = 'active',
                        matched_movie_id = NULL,
                        host_decision = NULL,
                        guest_decision = NULL
                    WHERE id = ?
                    "
                );
                $stmt->execute([$roomId]);
                $message = 'Both of you want to keep swiping.';
            } else {
                matchOnlineMarkMovieWatched($pdo, (int) $room['host_user_id'], (int) $room['matched_movie_id']);
                matchOnlineMarkMovieWatched($pdo, (int) $room['guest_user_id'], (int) $room['matched_movie_id']);

                $stmt = $pdo->prepare(
                    "
                    UPDATE " . getenv('DB_TABLE_F') . "
                    SET num_of_movies_watched = num_of_movies_watched + 1
                    WHERE (user_id_1 = ? AND user_id_2 = ?)
                       OR (user_id_1 = ? AND user_id_2 = ?)
                    "
                );
                $stmt->execute([
                    (int) $room['host_user_id'],
                    (int) $room['guest_user_id'],
                    (int) $room['guest_user_id'],
                    (int) $room['host_user_id'],
                ]);

                $stmt = $pdo->prepare(
                    "
                    UPDATE Match_online_rooms
                    SET status = 'closed'
                    WHERE id = ?
                    "
                );
                $stmt->execute([$roomId]);
                $message = 'Enjoy your movie!';
            }
        } else {
            $stmt = $pdo->prepare(
                "
                UPDATE Match_online_rooms
                SET host_decision = NULL,
                    guest_decision = NULL
                WHERE id = ?
                "
            );
            $stmt->execute([$roomId]);
            $message = 'The choices did not match. Please decide together.';
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    apiJsonResponse([
        'success' => false,
        'message' => 'Could not save room decision right now.',
    ], 500);
}

$updatedRoom = matchOnlineGetRoomById($pdo, $roomId);

apiJsonResponse([
    'success' => true,
    'message' => $message,
    'room' => matchOnlineHydrateRoomPayload($pdo, $updatedRoom, $currentUserId),
]);
?>
