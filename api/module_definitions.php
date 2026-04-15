<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/init_db.php';

$pdo = getDatabaseConnection();
initializeDatabase($pdo);

$moduleDefinitions = $pdo->query(
    'SELECT id, code, name, html_path, default_width, default_height, is_native, created_at, updated_at
     FROM module_definitions
     ORDER BY name ASC'
)->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($moduleDefinitions);
