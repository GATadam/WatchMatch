<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env');
    foreach ($lines as $line) {
        if (trim($line) && strpos($line, '=') !== false) {
            putenv(trim($line));
        }
    }
}

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://api.themoviedb.org/3/watch/providers/movie?language=en-US",
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

//file_put_contents('providers.json', $response);
if ($err) {
  echo "cURL Error #:" . $err;
}


$data = json_decode($response, true);

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

$stmt_check = $pdo->prepare("SELECT COUNT(*) FROM " . getenv('DB_TABLE_P') . " WHERE tmdb_id = ?");
$stmt = $pdo->prepare("INSERT INTO " . getenv('DB_TABLE_P') . " (tmdb_id, name, logo) VALUES (?, ?, ?)");

$providers = [];
if (file_exists('providers.txt')) {
    $lines = file('providers.txt');
    foreach ($lines as $line) {
        $providers[] = trim($line);
    }
}

foreach ($data['results'] as $provider) {
    if (in_array($provider['provider_name'], $providers)) {
        $tmdb_id = $provider['provider_id'];
        $name = $provider['provider_name'];
        $logo = getenv('IMAGE_ORIGINAL_URL') . $provider['logo_path'];

        $stmt_check->execute([$tmdb_id]);
        $exists = $stmt_check->fetchColumn();

        if ($exists == 0) {
            $stmt->execute([$tmdb_id, $name, $logo]);
        }
    }
}

echo "Sikeres importálás!";
?>