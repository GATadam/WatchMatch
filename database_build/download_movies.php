<?php

echo "Start time: " . date('Y-m-d H:i:s') . "\n";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

set_time_limit(0);


if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env');
    foreach ($lines as $line) {
        if (trim($line) && strpos($line, '=') !== false) {
            putenv(trim($line));
        }
    }
}

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

$stmt = $pdo->prepare("SELECT tmdb_id FROM " . getenv('DB_TABLE_P'));
$stmt->execute();
$prov_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("SELECT iso_code FROM " . getenv('DB_TABLE_R'));
$stmt->execute();
$reg_codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
$moviesDir = __DIR__ . '/movies';
array_map('unlink', glob("$moviesDir/*.*"));

//egy txt file-ba írd bele a prov_ids-t és a reg_codes-t a php file mellé
file_put_contents('ids.txt', "Provider IDs:\n" . implode("\n", $prov_ids) . "\n\nRegion Codes:\n" . implode("\n", $reg_codes));

var_dump($prov_ids);
var_dump($reg_codes);
foreach ($prov_ids as $prov_id) {
    foreach ($reg_codes as $reg_code) {
        $num = 0;
        echo "Provider ID: " . $prov_id . ", Region Code: " . $reg_code . "\n";
        $curl = curl_init();
        curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.themoviedb.org/3/discover/movie?include_adult=false&include_video=false&language=en-US&page=1&sort_by=popularity.desc&watch_region=" . $reg_code . "&with_watch_providers=" . $prov_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . getenv('TMDB_API_KEY'),
            "accept: application/json"
        ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        $data = json_decode($response, true);
        $total_pages = $data['total_pages'] ?? 0;

        for ($i=1; $i < $total_pages + 1; $i++) { 
            $curl = curl_init();
            curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.themoviedb.org/3/discover/movie?include_adult=false&include_video=false&language=en-US&page=" . $i . "&sort_by=popularity.desc&watch_region=" . $reg_code . "&with_watch_providers=" . $prov_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . getenv('TMDB_API_KEY'),
                "accept: application/json"
            ],
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                echo "cURL Error #:" . $err . "\n" . $reg_code . "\n" . $prov_id . "\n";
            } else {
                file_put_contents($moviesDir . '/movies_' . $reg_code . '_' . $prov_id . '_' . str_pad($num, 4, '0', STR_PAD_LEFT) . '.json', $response);
            }
            $num++;
            usleep(1000);
        }
        // miután lefutott a belső for ciklus, az összes json file-t ami az adott prov_id és reg_id nevével van ellátva, azokat összesítsük 1 json file-ba "prov_id"_"reg_id"_all.json néven
        $allMovies = [];
        foreach (glob($moviesDir . '/movies_' . $reg_code . '_' . $prov_id . '_*.json') as $file) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            $allMovies = array_merge($allMovies, $data['results'] ?? []);
        }
        file_put_contents($moviesDir . '/movies_all_' . $reg_code . '_' . $prov_id . '.json', json_encode(['results' => $allMovies], JSON_PRETTY_PRINT));
        //töröld az egyes json file-okat
        foreach (glob($moviesDir . '/movies_' . $reg_code . '_' . $prov_id . '_*.json') as $file) {
            unlink($file);
        }
    }
}

echo "Finish time: " . date('Y-m-d H:i:s') . "\n";
?>