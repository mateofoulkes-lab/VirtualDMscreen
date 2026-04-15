<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/init_db.php';

$pdo = getDatabaseConnection();
initializeDatabase($pdo);

$screens = $pdo->query(
    'SELECT id, name, created_at, updated_at FROM screens ORDER BY id ASC'
)->fetchAll(PDO::FETCH_ASSOC);

$panels = $pdo->query(
    'SELECT id, screen_id, name, sort_order, is_archive, created_at, updated_at
     FROM panels
     ORDER BY screen_id ASC, sort_order ASC, id ASC'
)->fetchAll(PDO::FETCH_ASSOC);

$modules = $pdo->query(
    'SELECT id, panel_id, type, title, position_x, position_y, width, height, z_index, is_archived, created_at, updated_at
     FROM modules
     ORDER BY panel_id ASC, z_index ASC, id ASC'
)->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'screens' => $screens,
    'panels' => $panels,
    'modules' => $modules,
]);
