<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/init_db.php';

header('Content-Type: application/json; charset=utf-8');

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function parseJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Request body is required.',
        ]);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Invalid JSON body.',
        ]);
    }

    return $decoded;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonResponse(405, [
        'success' => false,
        'error' => 'Method not allowed. Use POST.',
    ]);
}

try {
    $pdo = getDatabaseConnection();
    initializeDatabase($pdo);

    $payload = parseJsonBody();
    $name = trim((string) ($payload['name'] ?? ''));

    if ($name === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'name must not be empty.',
        ]);
    }

    $pdo->beginTransaction();

    $now = date('c');

    $insertScreenStmt = $pdo->prepare(
        'INSERT INTO screens (name, created_at, updated_at) VALUES (:name, :created_at, :updated_at)'
    );
    $insertScreenStmt->execute([
        ':name' => $name,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    $screenId = (int) $pdo->lastInsertId();

    $insertPanelStmt = $pdo->prepare(
        'INSERT INTO panels (screen_id, name, sort_order, is_archive, created_at, updated_at)
         VALUES (:screen_id, :name, :sort_order, :is_archive, :created_at, :updated_at)'
    );

    $insertPanelStmt->execute([
        ':screen_id' => $screenId,
        ':name' => 'Panel 1',
        ':sort_order' => 0,
        ':is_archive' => 0,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    $insertPanelStmt->execute([
        ':screen_id' => $screenId,
        ':name' => 'Archivo',
        ':sort_order' => 999,
        ':is_archive' => 1,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    $screenStmt = $pdo->prepare('SELECT id, name, created_at, updated_at FROM screens WHERE id = :id LIMIT 1');
    $screenStmt->execute([':id' => $screenId]);
    $screen = $screenStmt->fetch(PDO::FETCH_ASSOC);

    $panelsStmt = $pdo->prepare(
        'SELECT id, screen_id, name, sort_order, is_archive, created_at, updated_at
         FROM panels
         WHERE screen_id = :screen_id
         ORDER BY sort_order ASC, id ASC'
    );
    $panelsStmt->execute([':screen_id' => $screenId]);
    $panels = $panelsStmt->fetchAll(PDO::FETCH_ASSOC);

    $pdo->commit();

    if (!is_array($screen) || count($panels) !== 2) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'Screen or panels were created but could not be fetched.',
        ]);
    }

    jsonResponse(201, [
        'success' => true,
        'screen' => $screen,
        'panels' => $panels,
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(500, [
        'success' => false,
        'error' => 'Internal server error.',
    ]);
}
