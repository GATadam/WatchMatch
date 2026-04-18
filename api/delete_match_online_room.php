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

$room = matchOnlineRequireRoomMembership($pdo, $roomId, $currentUserId);

if (!in_array($room['status'], ['waiting', 'active', 'matched'], true)) {
    apiJsonResponse([
        'success' => false,
        'message' => 'This room is already closed.',
    ], 409);
}

try {
    $stmt = $pdo->prepare(
        "
        UPDATE Match_online_rooms
        SET status = 'closed'
        WHERE id = ?
        "
    );
    $stmt->execute([$roomId]);
} catch (Throwable $e) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Could not close room right now.',
    ], 500);
}

apiJsonResponse([
    'success' => true,
    'message' => 'Room closed successfully.',
]);
