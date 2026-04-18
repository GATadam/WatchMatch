<?php

function watchmatchLoadEnv(): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $candidates = [
        '/web/htdocs/www.kosmicdoom.com/home/.env',
        dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env',
    ];

    foreach ($candidates as $path) {
        if (!is_string($path) || $path === '' || !is_file($path)) {
            continue;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            continue;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                continue;
            }

            putenv($trimmed);
        }

        $loaded = true;
        return;
    }
}

function watchmatchCreatePdo(): PDO
{
    watchmatchLoadEnv();

    $dsn = 'mysql:host=' . getenv('DB_HOST');
    $port = trim((string) getenv('DB_PORT'));
    if ($port !== '') {
        $dsn .= ';port=' . $port;
    }
    $dsn .= ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4';

    return new PDO(
        $dsn,
        getenv('DB_USER_NAME'),
        getenv('DB_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function watchmatchResolveMediaUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }

    if (str_starts_with($value, '//')) {
        return 'https:' . $value;
    }

    if (str_starts_with($value, '/')) {
        return 'https://kosmicdoom.com' . $value;
    }

    return 'https://kosmicdoom.com/' . ltrim($value, './');
}
