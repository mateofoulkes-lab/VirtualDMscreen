<?php
declare(strict_types=1);

function getDatabaseConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbDirectory = dirname(__DIR__) . '/db';
    if (!is_dir($dbDirectory)) {
        mkdir($dbDirectory, 0777, true);
    }

    $databasePath = $dbDirectory . '/database.sqlite';
    if (!file_exists($databasePath)) {
        touch($databasePath);
    }

    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}
