<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/database.php';

loadEnv(dirname(__DIR__, 2) . '/.env');

define('APP_NAME', $_ENV['APP_NAME'] ?? 'MARKAZ Result Management System');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');

date_default_timezone_set('Africa/Lagos');

function db(): PDO
{
    static $connection = null;

    if ($connection === null) {
        $connection = getDatabaseConnection();
    }

    return $connection;
}
