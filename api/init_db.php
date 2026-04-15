<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$pdo = getDatabaseConnection();
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

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => 'ok']);
