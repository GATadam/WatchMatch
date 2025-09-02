<?php
$envPath = '/web/htdocs/www.kosmicdoom.com/home/.env';
$user_id = $_GET['user_id']; // szám
$providers = $_GET['providers']; // lista
$two_users = $_GET['two_users'] == "true"; // bool
$other_user = $_GET['other_user']; // szám
$start_page = $_GET['start_page']; // szám mindig meg kell szorozni 500-zal, így kapjuk meg, hogy hányszor volt meghívás, ha első 500-ban nem volt match megkérdezzük, hogy próbálkozzunk-e tovább
if( $user_id=="" || $providers=="" || ($two_users==true && $other_user=="") || $start_page=="" ) {
    die("Missing parameters");
} else {
    if (file_exists($envPath)) {
        $lines = file($envPath);
        foreach ($lines as $line) {
            if (trim($line) && strpos($line, '=') !== false) {
                putenv(trim($line));
            }
        }
    }
    $user_id = intval($user_id);
    $providers = explode(",", $providers);
    $start_page = intval($start_page);
    $dsn = 'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4';
    $user = getenv('DB_USER_NAME');
    $pass = getenv('DB_PASSWORD');
    
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }

    if ($two_users) {
        $other_user = intval($other_user);
        $stmt = $pdo->prepare("SELECT m.*
            FROM Movies m
            JOIN Where_to_watch w2w ON m.id = w2w.movie_id
            JOIN Users u ON w2w.regio_id = u.region_id
            WHERE u.id = " . $user_id . "
            AND ((
                    EXISTS (
                        SELECT 1 FROM Watched_movies wm1
                        WHERE wm1.user_id = u.id AND wm1.movie_id = m.id
                    )
                    AND EXISTS (
                        SELECT 1 FROM Watched_movies wm2
                        WHERE wm2.user_id = " . $other_user . " AND wm2.movie_id = m.id
                    )
                    AND RAND() > 0.9
                )
                OR ((
                        EXISTS (
                            SELECT 1 FROM Watched_movies wm1
                            WHERE wm1.user_id = u.id AND wm1.movie_id = m.id
                        )
                        XOR
                        EXISTS (
                            SELECT 1 FROM Watched_movies wm2
                            WHERE wm2.user_id = " . $other_user . " AND wm2.movie_id = m.id
                        )
                    )
                    AND RAND() > 0.5
                )
                OR (
                    NOT EXISTS (
                        SELECT 1 FROM Watched_movies wm1
                        WHERE wm1.user_id = u.id AND wm1.movie_id = m.id
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM Watched_movies wm2
                        WHERE wm2.user_id = " . $other_user . " AND wm2.movie_id = m.id
                    )
                )
            )
            ORDER BY m.popularity DESC
            LIMIT 500 OFFSET " . $start_page * 500 . ";");
    } else {
        $stmt = $pdo->prepare("SELECT m.*
            FROM Movies m
            JOIN Where_to_watch w2w ON m.id = w2w.movie_id
            JOIN Users u ON w2w.regio_id = u.region_id
            WHERE u.id = " . $user_id . "
            AND (
                NOT EXISTS (
                    SELECT 1 FROM Watched_movies wm
                    WHERE wm.user_id = u.id AND wm.movie_id = m.id
                )
                OR RAND() > 0.5
            )
            ORDER BY m.popularity DESC
            LIMIT 500 OFFSET " . $start_page * 500 . ";");
    }
    $stmt->execute();
    $movies1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $movies2 = [];
    foreach ($movies1 as $movie) {
        $movies2[] = $movie;
    }
    shuffle($movies1);
    shuffle($movies2);
    header('Content-Type: application/json');
    echo json_encode(
        [
            'movies1' => $movies1,
            'movies2' => $movies2
        ]
    ); // 2 adag filmet, ami ugyanazok, tehát ketten is tudják használni
}
?>