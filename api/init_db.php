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
            content TEXT DEFAULT \'\',
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

    $moduleColumns = $pdo->query('PRAGMA table_info(modules)')->fetchAll(PDO::FETCH_ASSOC);
    $hasContentColumn = false;
    foreach ($moduleColumns as $column) {
        if (($column['name'] ?? '') === 'content') {
            $hasContentColumn = true;
            break;
        }
    }

    if (!$hasContentColumn) {
        $pdo->exec('ALTER TABLE modules ADD COLUMN content TEXT DEFAULT \'\'');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS module_definitions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            html_path TEXT NOT NULL,
            default_width INTEGER NOT NULL,
            default_height INTEGER NOT NULL,
            is_native INTEGER NOT NULL DEFAULT 1,
            created_at TEXT,
            updated_at TEXT
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

    $moduleDefinitions = [
        [
            'code' => 'encounters',
            'name' => 'Encuentros',
            'html_path' => 'modules/encounters/index.html',
            'default_width' => 520,
            'default_height' => 340,
            'is_native' => 1,
        ],
        [
            'code' => 'dice_roller',
            'name' => 'Tirador de dados',
            'html_path' => 'modules/dice-roller/index.html',
            'default_width' => 360,
            'default_height' => 260,
            'is_native' => 1,
        ],
        [
            'code' => 'notes',
            'name' => 'Notas',
            'html_path' => 'modules/notes/index.html',
            'default_width' => 340,
            'default_height' => 240,
            'is_native' => 1,
        ],
        [
            'code' => 'image_viewer',
            'name' => 'Imagen',
            'html_path' => 'modules/image-viewer/index.html',
            'default_width' => 360,
            'default_height' => 260,
            'is_native' => 1,
        ],
        [
            'code' => 'name_generator_quick',
            'name' => 'Nombres rápidos',
            'html_path' => 'modules/name-generator-quick/index.html',
            'default_width' => 360,
            'default_height' => 260,
            'is_native' => 1,
        ],
        [
            'code' => 'character_editor',
            'name' => 'Editor de personajes',
            'html_path' => 'modules/character-editor/index.html',
            'default_width' => 520,
            'default_height' => 420,
            'is_native' => 1,
        ],
    ];

    $insertDefinition = $pdo->prepare(
        'INSERT INTO module_definitions
         (code, name, html_path, default_width, default_height, is_native, created_at, updated_at)
         VALUES
         (:code, :name, :html_path, :default_width, :default_height, :is_native, :created_at, :updated_at)'
    );

    $findDefinition = $pdo->prepare(
        'SELECT id FROM module_definitions WHERE code = :code LIMIT 1'
    );

    foreach ($moduleDefinitions as $definition) {
        $findDefinition->execute([':code' => $definition['code']]);
        $existingId = $findDefinition->fetchColumn();

        if ($existingId !== false) {
            continue;
        }

        $now = date('c');
        $insertDefinition->execute([
            ':code' => $definition['code'],
            ':name' => $definition['name'],
            ':html_path' => $definition['html_path'],
            ':default_width' => $definition['default_width'],
            ':default_height' => $definition['default_height'],
            ':is_native' => $definition['is_native'],
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
