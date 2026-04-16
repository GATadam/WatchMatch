<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/match_online_common.php';

$currentUserId = apiRequireAuthenticatedUserId($pdo);
$roomId = (int) apiRequestValue('room_id', 0);
if ($roomId <= 0) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Invalid room.',
    ], 400);
}

$room = matchOnlineRequireRoomMembership($pdo, $roomId, $currentUserId);

apiJsonResponse([
    'success' => true,
    'room' => matchOnlineHydrateRoomPayload($pdo, $room, $currentUserId),
]);
?>
