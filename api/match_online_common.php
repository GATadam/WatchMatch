<?php

function matchOnlineWatchedMoviesTable(): string
{
    return 'Watched_movies';
}

function matchOnlineNormalizeIdList($value): array
{
    if (is_string($value)) {
        $value = explode(',', $value);
    }

    if (!is_array($value)) {
        return [];
    }

    $ids = [];
    foreach ($value as $item) {
        $id = (int) $item;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

function matchOnlineFetchAssoc(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function matchOnlineFetchAll(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function matchOnlineGetWatchedMovieMap(PDO $pdo, int $userId): array
{
    $rows = matchOnlineFetchAll(
        $pdo,
        "SELECT movie_id FROM " . matchOnlineWatchedMoviesTable() . " WHERE user_id = ?",
        [$userId]
    );

    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['movie_id']] = true;
    }

    return $map;
}

function matchOnlineMarkMovieWatched(PDO $pdo, int $userId, int $movieId): void
{
    $stmt = $pdo->prepare(
        "
        INSERT INTO " . matchOnlineWatchedMoviesTable() . " (user_id, movie_id, rating)
        SELECT ?, ?, NULL
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1
            FROM " . matchOnlineWatchedMoviesTable() . "
            WHERE user_id = ? AND movie_id = ?
            LIMIT 1
        )
        "
    );
    $stmt->execute([$userId, $movieId, $userId, $movieId]);
}

function matchOnlineGetUserRegion(PDO $pdo, int $userId): ?array
{
    $usersTable = getenv('DB_TABLE_U');
    $regionsTable = getenv('DB_TABLE_R');

    return matchOnlineFetchAssoc(
        $pdo,
        "
        SELECT r.id, r.name, r.iso_code
        FROM {$usersTable} u
        INNER JOIN {$regionsTable} r
            ON r.id = u.region_id
        WHERE u.id = ?
        ",
        [$userId]
    );
}

function matchOnlineGetUniqueProvidersForRegion(PDO $pdo, int $regionId): array
{
    $providersTable = getenv('DB_TABLE_P');
    $w2wTable = getenv('DB_TABLE_W2W');

    return matchOnlineFetchAll(
        $pdo,
        "
        SELECT MIN(p.id) AS id, p.name, MIN(p.logo) AS logo
        FROM {$providersTable} p
        INNER JOIN {$w2wTable} w
            ON w.provider_id = p.id
        WHERE w.regio_id = ?
        GROUP BY p.name
        ORDER BY p.name
        ",
        [$regionId]
    );
}

function matchOnlineGetRoomProviders(PDO $pdo, int $roomId): array
{
    return matchOnlineFetchAll(
        $pdo,
        "
        SELECT rp.provider_id AS id, p.name, p.logo
        FROM Match_online_room_providers rp
        INNER JOIN " . getenv('DB_TABLE_P') . " p
            ON p.id = rp.provider_id
        WHERE rp.room_id = ?
        ORDER BY p.name
        ",
        [$roomId]
    );
}

function matchOnlineExpandProviderIdsByName(PDO $pdo, array $providerIds): array
{
    if (!$providerIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($providerIds), '?'));
    $providerNames = matchOnlineFetchAll(
        $pdo,
        "
        SELECT DISTINCT name
        FROM " . getenv('DB_TABLE_P') . "
        WHERE id IN ({$placeholders})
        ",
        array_values($providerIds)
    );

    if (!$providerNames) {
        return [];
    }

    $names = array_map(static fn(array $row): string => $row['name'], $providerNames);
    $namePlaceholders = implode(',', array_fill(0, count($names), '?'));
    $rows = matchOnlineFetchAll(
        $pdo,
        "
        SELECT DISTINCT id
        FROM " . getenv('DB_TABLE_P') . "
        WHERE name IN ({$namePlaceholders})
        ",
        $names
    );

    return array_map(static fn(array $row): int => (int) $row['id'], $rows);
}

function matchOnlineHydrateRoomPayload(PDO $pdo, array $room, int $currentUserId): array
{
    $providers = matchOnlineGetRoomProviders($pdo, (int) $room['id']);
    $isHost = (int) $room['host_user_id'] === $currentUserId;

    $payload = [
        'id' => (int) $room['id'],
        'status' => $room['status'],
        'region' => [
            'id' => (int) $room['region_id'],
            'name' => $room['region_name'],
            'iso_code' => $room['region_iso_code'],
        ],
        'host' => [
            'id' => (int) $room['host_user_id'],
            'username' => $room['host_username'],
            'profil_icon' => $room['host_profil_icon'],
            'icon_color' => $room['host_icon_color'],
            'icon_bg_color' => $room['host_icon_bg_color'],
        ],
        'guest' => $room['guest_user_id'] ? [
            'id' => (int) $room['guest_user_id'],
            'username' => $room['guest_username'],
            'profil_icon' => $room['guest_profil_icon'],
            'icon_color' => $room['guest_icon_color'],
            'icon_bg_color' => $room['guest_icon_bg_color'],
        ] : null,
        'providers' => array_map(
            static function (array $provider): array {
                return [
                    'id' => (int) $provider['id'],
                    'name' => $provider['name'],
                    'logo' => $provider['logo'],
                ];
            },
            $providers
        ),
        'role' => $isHost ? 'host' : 'guest',
        'self_decision' => $isHost ? $room['host_decision'] : $room['guest_decision'],
        'other_decision' => $isHost ? $room['guest_decision'] : $room['host_decision'],
        'matched_movie' => null,
        'current_movies' => [],
        'created_at' => $room['created_at'],
        'updated_at' => $room['updated_at'],
    ];

    if (!empty($room['matched_movie_id'])) {
        $matchedMovie = matchOnlineGetMovieById($pdo, (int) $room['matched_movie_id']);
        if ($matchedMovie) {
            $matchedMovie['providers'] = array_map(
                static function (array $provider): array {
                    return [
                        'id' => (int) $provider['id'],
                        'name' => $provider['name'],
                        'logo' => $provider['logo'],
                    ];
                },
                matchOnlineGetMovieProvidersForRoom($pdo, (int) $room['matched_movie_id'], $room)
            );
        }
        $payload['matched_movie'] = $matchedMovie;
    }

    if ($room['status'] === 'active' && !empty($room['guest_user_id'])) {
        $payload['current_movies'] = matchOnlineGetNextMovies($pdo, $room, $currentUserId, 3);
    }

    return $payload;
}

function matchOnlineGetRoomById(PDO $pdo, int $roomId): ?array
{
    return matchOnlineFetchAssoc(
        $pdo,
        "
        SELECT
            r.*,
            reg.name AS region_name,
            reg.iso_code AS region_iso_code,
            host.username AS host_username,
            host.profil_icon AS host_profil_icon,
            host.icon_color AS host_icon_color,
            host.icon_bg_color AS host_icon_bg_color,
            guest.username AS guest_username,
            guest.profil_icon AS guest_profil_icon,
            guest.icon_color AS guest_icon_color,
            guest.icon_bg_color AS guest_icon_bg_color
        FROM Match_online_rooms r
        INNER JOIN " . getenv('DB_TABLE_R') . " reg
            ON reg.id = r.region_id
        INNER JOIN " . getenv('DB_TABLE_U') . " host
            ON host.id = r.host_user_id
        LEFT JOIN " . getenv('DB_TABLE_U') . " guest
            ON guest.id = r.guest_user_id
        WHERE r.id = ?
        ",
        [$roomId]
    );
}

function matchOnlineFindOpenRoomForUser(PDO $pdo, int $userId): ?array
{
    $room = matchOnlineFetchAssoc(
        $pdo,
        "
        SELECT id
        FROM Match_online_rooms
        WHERE status IN ('waiting', 'active')
          AND (host_user_id = ? OR guest_user_id = ?)
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
        ",
        [$userId, $userId]
    );

    if (!$room) {
        return null;
    }

    return matchOnlineGetRoomById($pdo, (int) $room['id']);
}

function matchOnlineRequireRoomMembership(PDO $pdo, int $roomId, int $userId): array
{
    $room = matchOnlineGetRoomById($pdo, $roomId);
    if (!$room) {
        apiJsonResponse([
            'success' => false,
            'message' => 'Room not found.',
        ], 404);
    }

    if ((int) $room['host_user_id'] !== $userId && (int) $room['guest_user_id'] !== $userId) {
        apiJsonResponse([
            'success' => false,
            'message' => 'You are not a member of this room.',
        ], 403);
    }

    return $room;
}

function matchOnlineGetOtherUserId(array $room, int $userId): ?int
{
    if ((int) $room['host_user_id'] === $userId) {
        return !empty($room['guest_user_id']) ? (int) $room['guest_user_id'] : null;
    }

    return (int) $room['host_user_id'];
}

function matchOnlineGetMovieById(PDO $pdo, int $movieId): ?array
{
    $movie = matchOnlineFetchAssoc(
        $pdo,
        "
        SELECT id, tmdb_id, title, popularity, picture
        FROM " . getenv('DB_TABLE_M') . "
        WHERE id = ?
        ",
        [$movieId]
    );

    if (!$movie) {
        return null;
    }

    $movie['id'] = (int) $movie['id'];
    $movie['tmdb_id'] = (int) $movie['tmdb_id'];
    $movie['popularity'] = (float) $movie['popularity'];

    return $movie;
}

function matchOnlineGetMovieProvidersForRoom(PDO $pdo, int $movieId, array $room): array
{
    $roomProviders = matchOnlineGetRoomProviders($pdo, (int) $room['id']);
    if (!$roomProviders) {
        return [];
    }

    $providerIds = array_map(static fn(array $provider): int => (int) $provider['id'], $roomProviders);
    $providerIds = matchOnlineExpandProviderIdsByName($pdo, $providerIds);
    if (!$providerIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($providerIds), '?'));
    $params = array_merge([$movieId, (int) $room['region_id']], $providerIds);

    return matchOnlineFetchAll(
        $pdo,
        "
        SELECT MIN(p.id) AS id, p.name, MIN(p.logo) AS logo
        FROM " . getenv('DB_TABLE_W2W') . " w
        INNER JOIN " . getenv('DB_TABLE_P') . " p
            ON p.id = w.provider_id
        WHERE w.movie_id = ?
          AND w.regio_id = ?
          AND w.provider_id IN ({$placeholders})
        GROUP BY p.name
        ORDER BY p.name
        ",
        $params
    );
}

function matchOnlineGetRankedCandidateMovies(PDO $pdo, array $room, int $userId, ?int $otherUserId, array $excludeMovieIds = []): array
{
    $providers = matchOnlineGetRoomProviders($pdo, (int) $room['id']);
    if (!$providers) {
        return [];
    }

    $providerIds = array_map(static fn(array $provider): int => (int) $provider['id'], $providers);
    $providerIds = matchOnlineExpandProviderIdsByName($pdo, $providerIds);
    if (!$providerIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($providerIds), '?'));

    $params = array_merge([(int) $room['region_id']], $providerIds);
    $movies = matchOnlineFetchAll(
        $pdo,
        "
        SELECT DISTINCT m.id, m.tmdb_id, m.title, m.popularity, m.picture
        FROM " . getenv('DB_TABLE_M') . " m
        INNER JOIN " . getenv('DB_TABLE_W2W') . " w
            ON w.movie_id = m.id
        WHERE w.regio_id = ?
          AND w.provider_id IN ({$placeholders})
        ORDER BY m.popularity DESC, m.id ASC
        ",
        $params
    );

    if (!$otherUserId) {
        return [];
    }

    $selfMap = matchOnlineGetWatchedMovieMap($pdo, $userId);
    $otherMap = matchOnlineGetWatchedMovieMap($pdo, $otherUserId);

    $eligible = [];
    foreach ($movies as $movie) {
        $movieId = (int) $movie['id'];

        $selfWatched = isset($selfMap[$movieId]);
        $otherWatched = isset($otherMap[$movieId]);

        $inclusionChance = 1.0;
        if ($selfWatched && $otherWatched) {
            $inclusionChance = 0.10;
        } elseif ($selfWatched xor $otherWatched) {
            $inclusionChance = 0.50;
        }

        $poolRoll = hexdec(substr(hash('sha256', (string) $room['id'] . ':pool:' . (string) $movieId), 0, 8)) / 0xFFFFFFFF;
        if ($poolRoll > $inclusionChance) {
            continue;
        }

        $movie['id'] = $movieId;
        $movie['tmdb_id'] = (int) $movie['tmdb_id'];
        $movie['popularity'] = (float) $movie['popularity'];
        $eligible[] = $movie;
    }

    $batches = array_chunk($eligible, 100);
    $result = [];

    $sortFn = static function (array $a, array $b): int {
        if ($a['_shuffle'] !== $b['_shuffle']) {
            return $a['_shuffle'] <=> $b['_shuffle'];
        }
        if ($a['popularity'] !== $b['popularity']) {
            return $b['popularity'] <=> $a['popularity'];
        }
        return $a['id'] <=> $b['id'];
    };

    foreach ($batches as $batch) {
        foreach ($batch as &$m) {
            $m['_shuffle'] = hexdec(substr(hash('sha256', (string) $room['id'] . ':user:' . (string) $userId . ':' . (string) $m['id']), 0, 8));
        }
        unset($m);

        usort($batch, $sortFn);

        foreach ($batch as $movie) {
            if (isset($excludeMovieIds[$movie['id']])) {
                continue;
            }
            unset($movie['_shuffle']);
            $result[] = $movie;
        }
    }

    return $result;
}

function matchOnlineGetNextMovies(PDO $pdo, array $room, int $userId, int $limit = 3): array
{
    $otherUserId = matchOnlineGetOtherUserId($room, $userId);
    if (!$otherUserId) {
        return [];
    }

    $swipedRows = matchOnlineFetchAll(
        $pdo,
        "
        SELECT movie_id
        FROM Match_online_room_swipes
        WHERE room_id = ? AND user_id = ?
        ",
        [(int) $room['id'], $userId]
    );
    $swipedMovieIds = [];
    foreach ($swipedRows as $row) {
        $swipedMovieIds[(int) $row['movie_id']] = true;
    }

    $candidateMovies = matchOnlineGetRankedCandidateMovies($pdo, $room, $userId, $otherUserId, $swipedMovieIds);
    $nextMovies = [];
    foreach ($candidateMovies as $movie) {
        $nextMovies[] = $movie;
        if (count($nextMovies) >= $limit) {
            break;
        }
    }

    return $nextMovies;
}

function matchOnlineIsConfirmedFriend(PDO $pdo, int $userId, int $otherUserId): bool
{
    $friendsTable = getenv('DB_TABLE_F');

    $stmt = $pdo->prepare(
        "
        SELECT COUNT(*)
        FROM {$friendsTable} f1
        INNER JOIN {$friendsTable} f2
            ON f2.user_id_1 = f1.user_id_2
           AND f2.user_id_2 = f1.user_id_1
        WHERE f1.user_id_1 = ?
          AND f1.user_id_2 = ?
        "
    );
    $stmt->execute([$userId, $otherUserId]);

    return (int) $stmt->fetchColumn() > 0;
}

function matchOnlineGetJoinableFriendRooms(PDO $pdo, int $userId): array
{
    $rooms = matchOnlineFetchAll(
        $pdo,
        "
        SELECT
            r.id,
            r.host_user_id,
            r.region_id,
            r.status,
            r.created_at,
            r.updated_at,
            reg.name AS region_name,
            reg.iso_code AS region_iso_code,
            host.username AS host_username,
            host.profil_icon AS host_profil_icon,
            host.icon_color AS host_icon_color,
            host.icon_bg_color AS host_icon_bg_color
        FROM Match_online_rooms r
        INNER JOIN " . getenv('DB_TABLE_U') . " host
            ON host.id = r.host_user_id
        INNER JOIN " . getenv('DB_TABLE_R') . " reg
            ON reg.id = r.region_id
        INNER JOIN " . getenv('DB_TABLE_F') . " f1
            ON f1.user_id_1 = ? AND f1.user_id_2 = r.host_user_id
        INNER JOIN " . getenv('DB_TABLE_F') . " f2
            ON f2.user_id_1 = r.host_user_id AND f2.user_id_2 = ?
        WHERE r.status = 'waiting'
          AND r.guest_user_id IS NULL
          AND r.host_user_id <> ?
        ORDER BY r.updated_at DESC, r.id DESC
        ",
        [$userId, $userId, $userId]
    );

    $result = [];
    foreach ($rooms as $room) {
        $result[] = [
            'id' => (int) $room['id'],
            'status' => $room['status'],
            'region' => [
                'id' => (int) $room['region_id'],
                'name' => $room['region_name'],
                'iso_code' => $room['region_iso_code'],
            ],
            'host' => [
                'id' => (int) $room['host_user_id'],
                'username' => $room['host_username'],
                'profil_icon' => $room['host_profil_icon'],
                'icon_color' => $room['host_icon_color'],
                'icon_bg_color' => $room['host_icon_bg_color'],
            ],
            'providers' => array_map(
                static function (array $provider): array {
                    return [
                        'id' => (int) $provider['id'],
                        'name' => $provider['name'],
                        'logo' => $provider['logo'],
                    ];
                },
                matchOnlineGetRoomProviders($pdo, (int) $room['id'])
            ),
            'created_at' => $room['created_at'],
            'updated_at' => $room['updated_at'],
        ];
    }

    return $result;
}
