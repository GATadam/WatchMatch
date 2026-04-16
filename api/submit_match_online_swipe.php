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
$movieId = (int) apiRequestValue('movie_id', 0);
$swipe = trim((string) apiRequestValue('swipe', ''));

if ($roomId <= 0 || $movieId <= 0 || !in_array($swipe, ['left', 'right'], true)) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Invalid swipe payload.',
    ], 400);
}

$room = matchOnlineRequireRoomMembership($pdo, $roomId, $currentUserId);
if ($room['status'] !== 'active' || empty($room['guest_user_id'])) {
    apiJsonResponse([
        'success' => false,
        'message' => 'This room is not ready for swiping.',
    ], 409);
}

$movie = matchOnlineGetMovieById($pdo, $movieId);
if (!$movie) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Movie not found.',
    ], 404);
}

$otherUserId = matchOnlineGetOtherUserId($room, $currentUserId);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "
        INSERT INTO Match_online_room_swipes (room_id, user_id, movie_id, swipe)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE swipe = VALUES(swipe), created_at = CURRENT_TIMESTAMP
        "
    );
    $stmt->execute([$roomId, $currentUserId, $movieId, $swipe]);

    if ($swipe === 'right' && $otherUserId) {
        $stmt = $pdo->prepare(
            "
            SELECT swipe
            FROM Match_online_room_swipes
            WHERE room_id = ? AND user_id = ? AND movie_id = ?
            LIMIT 1
            "
        );
        $stmt->execute([$roomId, $otherUserId, $movieId]);
        $otherSwipe = $stmt->fetchColumn();

        if ($otherSwipe === 'right') {
            $stmt = $pdo->prepare(
                "
                UPDATE Match_online_rooms
                SET status = 'matched',
                    matched_movie_id = ?,
                    host_decision = NULL,
                    guest_decision = NULL
                WHERE id = ?
                "
            );
            $stmt->execute([$movieId, $roomId]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    apiJsonResponse([
        'success' => false,
        'message' => 'Could not save swipe right now.',
    ], 500);
}

$updatedRoom = matchOnlineGetRoomById($pdo, $roomId);
$payload = matchOnlineHydrateRoomPayload($pdo, $updatedRoom, $currentUserId);

apiJsonResponse([
    'success' => true,
    'message' => $payload['status'] === 'matched' ? 'You have a match!' : 'Swipe saved.',
    'room' => $payload,
]);
?>
