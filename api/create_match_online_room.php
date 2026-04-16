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
$existingRoom = matchOnlineFindOpenRoomForUser($pdo, $currentUserId);
if ($existingRoom) {
    apiJsonResponse([
        'success' => true,
        'message' => 'You already have an open room.',
        'room' => matchOnlineHydrateRoomPayload($pdo, $existingRoom, $currentUserId),
    ]);
}

$region = matchOnlineGetUserRegion($pdo, $currentUserId);
if (!$region) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Could not determine your region.',
    ], 404);
}

$providerIds = matchOnlineNormalizeIdList(apiRequestValue('provider_ids', []));
if (!$providerIds) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Select at least one provider.',
    ], 400);
}

$availableProviders = matchOnlineGetUniqueProvidersForRegion($pdo, (int) $region['id']);
$availableProviderIds = [];
foreach ($availableProviders as $provider) {
    $availableProviderIds[(int) $provider['id']] = true;
}

$validatedProviderIds = [];
foreach ($providerIds as $providerId) {
    if (isset($availableProviderIds[$providerId])) {
        $validatedProviderIds[$providerId] = $providerId;
    }
}

if (!$validatedProviderIds) {
    apiJsonResponse([
        'success' => false,
        'message' => 'None of the selected providers are valid for your region.',
    ], 400);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "
        INSERT INTO Match_online_rooms
            (host_user_id, guest_user_id, region_id, status, matched_movie_id, host_decision, guest_decision)
        VALUES
            (?, NULL, ?, 'waiting', NULL, NULL, NULL)
        "
    );
    $stmt->execute([$currentUserId, (int) $region['id']]);
    $roomId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "
        INSERT INTO Match_online_room_providers (room_id, provider_id)
        VALUES (?, ?)
        "
    );
    foreach ($validatedProviderIds as $providerId) {
        $stmt->execute([$roomId, $providerId]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    apiJsonResponse([
        'success' => false,
        'message' => 'Could not create room right now.',
    ], 500);
}

$room = matchOnlineGetRoomById($pdo, $roomId);

apiJsonResponse([
    'success' => true,
    'message' => 'Room created successfully.',
    'room' => matchOnlineHydrateRoomPayload($pdo, $room, $currentUserId),
]);
?>
