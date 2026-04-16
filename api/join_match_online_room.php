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
if ($roomId <= 0) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Invalid room.',
    ], 400);
}

$existingRoom = matchOnlineFindOpenRoomForUser($pdo, $currentUserId);
if ($existingRoom && (int) $existingRoom['id'] !== $roomId) {
    apiJsonResponse([
        'success' => false,
        'message' => 'You already have an open room.',
    ], 409);
}

$room = matchOnlineGetRoomById($pdo, $roomId);
if (!$room) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Room not found.',
    ], 404);
}

if ((int) $room['host_user_id'] === $currentUserId) {
    apiJsonResponse([
        'success' => false,
        'message' => 'You cannot join your own room.',
    ], 400);
}

if ($room['status'] !== 'waiting' || !empty($room['guest_user_id'])) {
    apiJsonResponse([
        'success' => false,
        'message' => 'This room is no longer available to join.',
    ], 409);
}

if (!matchOnlineIsConfirmedFriend($pdo, $currentUserId, (int) $room['host_user_id'])) {
    apiJsonResponse([
        'success' => false,
        'message' => 'You can only join rooms created by confirmed friends.',
    ], 403);
}

try {
    $stmt = $pdo->prepare(
        "
        UPDATE Match_online_rooms
        SET guest_user_id = ?, status = 'active', host_decision = NULL, guest_decision = NULL
        WHERE id = ?
          AND status = 'waiting'
          AND guest_user_id IS NULL
        "
    );
    $stmt->execute([$currentUserId, $roomId]);

    if ($stmt->rowCount() === 0) {
        apiJsonResponse([
            'success' => false,
            'message' => 'This room is no longer available to join.',
        ], 409);
    }
} catch (Throwable $e) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Could not join room right now.',
    ], 500);
}

$room = matchOnlineGetRoomById($pdo, $roomId);

apiJsonResponse([
    'success' => true,
    'message' => 'Joined room successfully.',
    'room' => matchOnlineHydrateRoomPayload($pdo, $room, $currentUserId),
]);
?>
