<?php

declare(strict_types=1);

function getDatabaseConnection(): PDO
{
    // Environment overrides allow isolated development databases without
    // changing the production database name or committing local credentials.
    $env = static function (string $key, string $default = ''): string {
        $value = getenv($key);
        if ($value === false || $value === '') {
            $value = $_ENV[$key] ?? null;
        }
        return is_string($value) && $value !== '' ? $value : $default;
    };

    $host = $env('DB_HOST', '127.0.0.1');
    $port = $env('DB_PORT', '3306');
    $database = $env('DB_NAME', 'markaz_result_system');
    $username = $env('DB_USER', 'root');
    $password = $env('DB_PASSWORD', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
