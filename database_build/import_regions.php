<?php

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
  CURLOPT_URL => "https://api.themoviedb.org/3/watch/providers/regions?language=en-US",
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

//file_put_contents('regions.json', $response);
if ($err) {
  die("cURL Error #:" . $err);
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
    die("Database connection failed: " . $e->getMessage() . ". " . getenv('DB_PORT'));
}

$stmt_check = $pdo->prepare("SELECT COUNT(*) FROM " . getenv('DB_TABLE_R') . " WHERE iso_code = ?");
$stmt_insert = $pdo->prepare("INSERT INTO " . getenv('DB_TABLE_R') . " (iso_code, name) VALUES (?, ?)");

foreach ($data['results'] as $country) {
    $iso_code = $country['iso_3166_1'];
    $name = $country['english_name'];

    $stmt_check->execute([$iso_code]);
    $exists = $stmt_check->fetchColumn();

    if ($exists == 0) {
        $stmt_insert->execute([$iso_code, $name]);
    }
}


echo "Sikeres importálás!";
?>
