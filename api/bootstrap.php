<?php
header('Content-Type: application/json; charset=UTF-8');

function apiJsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function apiRequestValue(string $key, $default = null)
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

$envPaths = [
    '/web/htdocs/www.kosmicdoom.com/home/.env',
    dirname(__DIR__) . '/.env',
];

foreach ($envPaths as $envPath) {
    if (!file_exists($envPath)) {
        continue;
    }

    $lines = file($envPath);
    foreach ($lines as $line) {
        if (trim($line) !== '' && strpos($line, '=') !== false) {
            putenv(trim($line));
        }
    }

    break;
}

$dsn = 'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4';
$dbUser = getenv('DB_USER_NAME');
$dbPass = getenv('DB_PASSWORD');

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    apiJsonResponse([
        'success' => false,
        'message' => 'Database connection error.',
    ], 500);
}

function apiRequireAuthenticatedUserId(PDO $pdo): int
{
    $authToken = trim((string) apiRequestValue('auth_token', ''));
    if ($authToken === '' && isset($_COOKIE['watchmatch_auth_token'])) {
        $authToken = trim((string) $_COOKIE['watchmatch_auth_token']);
    }

    if ($authToken === '') {
        apiJsonResponse([
            'success' => false,
            'message' => 'Missing auth token.',
        ], 401);
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM ' . getenv('DB_TABLE_U') . ' WHERE auth_token = ?'
    );
    $stmt->execute([$authToken]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        apiJsonResponse([
            'success' => false,
            'message' => 'Invalid auth token.',
        ], 401);
    }

    return (int) $userId;
}
?>
