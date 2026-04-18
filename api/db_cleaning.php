<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/match_online_common.php';

apiRequireAuthenticatedUserId($pdo);

$usersTable = getenv('DB_TABLE_U') ?: 'Users';
$friendsTable = getenv('DB_TABLE_F') ?: 'Friends';
$watchedMoviesTable = 'Watched_movies';

function dbCleaningColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
    $stmt->execute([$column]);
    return (bool) $stmt->fetch();
}

function dbCleaningEnsureUsersCreatedAt(PDO $pdo, string $usersTable): bool
{
    if (dbCleaningColumnExists($pdo, $usersTable, 'created_at')) {
        return true;
    }

    try {
        $pdo->exec(
            "ALTER TABLE {$usersTable}
             ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
             AFTER icon_bg_color"
        );
    } catch (Throwable $throwable) {
        return false;
    }

    return dbCleaningColumnExists($pdo, $usersTable, 'created_at');
}

function dbCleaningDeleteUser(PDO $pdo, int $userId, string $usersTable, string $friendsTable, string $watchedMoviesTable): void
{
    $roomIds = [];
    $stmt = $pdo->prepare('SELECT id FROM Match_online_rooms WHERE host_user_id = ? OR guest_user_id = ?');
    $stmt->execute([$userId, $userId]);
    foreach ($stmt->fetchAll() as $row) {
        $roomIds[] = (int) $row['id'];
    }

    if ($roomIds) {
        $placeholders = implode(',', array_fill(0, count($roomIds), '?'));

        $stmt = $pdo->prepare("DELETE FROM Match_online_room_providers WHERE room_id IN ({$placeholders})");
        $stmt->execute($roomIds);

        $stmt = $pdo->prepare("DELETE FROM Match_online_room_swipes WHERE room_id IN ({$placeholders})");
        $stmt->execute($roomIds);

        $stmt = $pdo->prepare("DELETE FROM Match_online_rooms WHERE id IN ({$placeholders})");
        $stmt->execute($roomIds);
    }

    $stmt = $pdo->prepare('DELETE FROM Match_online_room_swipes WHERE user_id = ?');
    $stmt->execute([$userId]);

    $stmt = $pdo->prepare('DELETE FROM Blocked_users WHERE user_id_blocker = ? OR user_id_blocked = ?');
    $stmt->execute([$userId, $userId]);

    $stmt = $pdo->prepare("DELETE FROM {$friendsTable} WHERE user_id_1 = ? OR user_id_2 = ?");
    $stmt->execute([$userId, $userId]);

    $stmt = $pdo->prepare("DELETE FROM {$watchedMoviesTable} WHERE user_id = ?");
    $stmt->execute([$userId]);

    $stmt = $pdo->prepare("DELETE FROM {$usersTable} WHERE id = ?");
    $stmt->execute([$userId]);
}

function dbCleaningFinalizeExpiredRoom(PDO $pdo, array $room, string $friendsTable): void
{
    $roomId = (int) $room['id'];
    $hostUserId = (int) $room['host_user_id'];
    $guestUserId = !empty($room['guest_user_id']) ? (int) $room['guest_user_id'] : 0;
    $matchedMovieId = !empty($room['matched_movie_id']) ? (int) $room['matched_movie_id'] : 0;

    if ($room['status'] === 'matched' && $guestUserId > 0 && $matchedMovieId > 0) {
        matchOnlineMarkMovieWatched($pdo, $hostUserId, $matchedMovieId);
        matchOnlineMarkMovieWatched($pdo, $guestUserId, $matchedMovieId);

        $stmt = $pdo->prepare(
            "
            UPDATE {$friendsTable}
            SET num_of_movies_watched = num_of_movies_watched + 1
            WHERE (user_id_1 = ? AND user_id_2 = ?)
               OR (user_id_1 = ? AND user_id_2 = ?)
            "
        );
        $stmt->execute([$hostUserId, $guestUserId, $guestUserId, $hostUserId]);

        $stmt = $pdo->prepare(
            "
            UPDATE Match_online_rooms
            SET status = 'closed',
                host_decision = 'lets_watch',
                guest_decision = 'lets_watch'
            WHERE id = ?
            "
        );
        $stmt->execute([$roomId]);
        return;
    }

    $stmt = $pdo->prepare(
        "
        UPDATE Match_online_rooms
        SET status = 'closed',
            host_decision = NULL,
            guest_decision = NULL
        WHERE id = ?
        "
    );
    $stmt->execute([$roomId]);
}

$usersCreatedAtReady = dbCleaningEnsureUsersCreatedAt($pdo, $usersTable);
$closedRooms = 0;
$deletedUsers = 0;
$skippedUserCleanup = false;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "
        SELECT id, status, host_user_id, guest_user_id, matched_movie_id
        FROM Match_online_rooms
        WHERE status IN ('waiting', 'active', 'matched')
          AND created_at < (CURRENT_TIMESTAMP - INTERVAL 30 MINUTE)
        "
    );
    $stmt->execute();
    $expiredRooms = $stmt->fetchAll();

    foreach ($expiredRooms as $room) {
        dbCleaningFinalizeExpiredRoom($pdo, $room, $friendsTable);
        $closedRooms++;
    }

    if ($usersCreatedAtReady) {
        $stmt = $pdo->prepare(
            "
            SELECT id
            FROM {$usersTable}
            WHERE email_verified = 0
              AND created_at < (CURRENT_TIMESTAMP - INTERVAL 24 HOUR)
            "
        );
        $stmt->execute();
        $userIds = array_map(
            static fn(array $row): int => (int) $row['id'],
            $stmt->fetchAll()
        );

        foreach ($userIds as $userId) {
            dbCleaningDeleteUser($pdo, $userId, $usersTable, $friendsTable, $watchedMoviesTable);
            $deletedUsers++;
        }
    } else {
        $skippedUserCleanup = true;
    }

    $pdo->commit();
} catch (Throwable $throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    apiJsonResponse([
        'success' => false,
        'message' => 'Could not complete database cleaning.',
    ], 500);
}

$message = 'Database cleaning finished.';
if ($skippedUserCleanup) {
    $message = 'Room cleanup finished, but unverified-user cleanup was skipped because the user timestamp could not be ensured.';
}

apiJsonResponse([
    'success' => true,
    'message' => $message,
    'closed_rooms' => $closedRooms,
    'deleted_unverified_users' => $deletedUsers,
    'users_created_at_ready' => $usersCreatedAtReady,
]);
