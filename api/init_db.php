<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function initializeDatabase(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = ON;');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS screens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            created_at TEXT,
            updated_at TEXT
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS panels (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            screen_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_archive INTEGER NOT NULL DEFAULT 0,
            created_at TEXT,
            updated_at TEXT,
            FOREIGN KEY (screen_id) REFERENCES screens(id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS modules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            panel_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            title TEXT NOT NULL,
            position_x INTEGER,
            position_y INTEGER,
            width INTEGER,
            height INTEGER,
            z_index INTEGER NOT NULL DEFAULT 0,
            is_archived INTEGER DEFAULT 0,
            created_at TEXT,
            updated_at TEXT,
            FOREIGN KEY (panel_id) REFERENCES panels(id)
        )'
    );

    $screensCount = (int) $pdo->query('SELECT COUNT(*) FROM screens')->fetchColumn();
    if ($screensCount === 0) {
        $now = date('c');

        $insertScreen = $pdo->prepare(
            'INSERT INTO screens (name, created_at, updated_at) VALUES (:name, :created_at, :updated_at)'
        );
        $insertScreen->execute([
            ':name' => 'Pantalla 1',
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $screenId = (int) $pdo->lastInsertId();
        $insertPanel = $pdo->prepare(
            'INSERT INTO panels (screen_id, name, sort_order, is_archive, created_at, updated_at)
             VALUES (:screen_id, :name, :sort_order, :is_archive, :created_at, :updated_at)'
        );

        $insertPanel->execute([
            ':screen_id' => $screenId,
            ':name' => 'Panel 1',
            ':sort_order' => 0,
            ':is_archive' => 0,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $insertPanel->execute([
            ':screen_id' => $screenId,
            ':name' => 'Archivo',
            ':sort_order' => 999,
            ':is_archive' => 1,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }
}

$pdo = getDatabaseConnection();
initializeDatabase($pdo);

if (basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok']);
}
