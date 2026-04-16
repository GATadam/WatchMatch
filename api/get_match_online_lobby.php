<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/match_online_common.php';

$currentUserId = apiRequireAuthenticatedUserId($pdo);
$region = matchOnlineGetUserRegion($pdo, $currentUserId);

if (!$region) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Could not determine your region.',
    ], 404);
}

$currentRoom = matchOnlineFindOpenRoomForUser($pdo, $currentUserId);

apiJsonResponse([
    'success' => true,
    'region' => [
        'id' => (int) $region['id'],
        'name' => $region['name'],
        'iso_code' => $region['iso_code'],
    ],
    'providers' => array_map(
        static function (array $provider): array {
            return [
                'id' => (int) $provider['id'],
                'name' => $provider['name'],
                'logo' => $provider['logo'],
            ];
        },
        matchOnlineGetUniqueProvidersForRegion($pdo, (int) $region['id'])
    ),
    'joinable_rooms' => matchOnlineGetJoinableFriendRooms($pdo, $currentUserId),
    'current_room' => $currentRoom ? matchOnlineHydrateRoomPayload($pdo, $currentRoom, $currentUserId) : null,
]);
?>
