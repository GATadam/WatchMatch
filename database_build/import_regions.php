<?php

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
    "Authorization: Bearer eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiI4OThmYjY0NDkyNjM1ZTlkNjc4ZDg3MjgwMzdhNTVmZSIsIm5iZiI6MTc1MjU5NjI3Ni40MDcsInN1YiI6IjY4NzY3ZjM0YWFkNDlkMGQ5NzljYjIyOCIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.O8gtGIXpvLlrfX3iqxSkQZ4YgnVr_Xp1CspnpZ4vlXA",
    "accept: application/json"
  ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
  die("cURL Error #:" . $err);
}

$data = json_decode($response, true);

$dsn = 'mysql:host=localhost;dbname=b29853;charset=utf8mb4';
$user = 'felhasznalonev';
$pass = 'jelszo';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$stmt = $pdo->prepare("INSERT INTO countries (iso_code, name) VALUES (?, ?)");

foreach ($data['results'] as $country) {
    $iso_code = $country['iso_3166_1'];
    $name = $country['english_name'];
    $stmt->execute([$iso, $english]);
}

echo "Sikeres importálás!";
?>