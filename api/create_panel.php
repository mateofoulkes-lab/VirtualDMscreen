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
    $screenId = filter_var($payload['screen_id'] ?? null, FILTER_VALIDATE_INT);
    $name = trim((string) ($payload['name'] ?? ''));

    if ($screenId === false || (int) $screenId <= 0) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'screen_id must be a positive integer.',
        ]);
    }

    if ($name === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'name must not be empty.',
        ]);
    }

    $screenExistsStmt = $pdo->prepare('SELECT id FROM screens WHERE id = :id LIMIT 1');
    $screenExistsStmt->execute([':id' => $screenId]);
    if ($screenExistsStmt->fetchColumn() === false) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'screen_id does not exist.',
        ]);
    }

    $sortOrderStmt = $pdo->prepare(
        'SELECT COALESCE(MAX(sort_order), -1) AS max_sort
         FROM panels
         WHERE screen_id = :screen_id
           AND is_archive = 0'
    );
    $sortOrderStmt->execute([':screen_id' => $screenId]);
    $nextSortOrder = ((int) $sortOrderStmt->fetchColumn()) + 1;

    $now = date('c');

    $insertStmt = $pdo->prepare(
        'INSERT INTO panels (screen_id, name, sort_order, is_archive, created_at, updated_at)
         VALUES (:screen_id, :name, :sort_order, :is_archive, :created_at, :updated_at)'
    );

    $insertStmt->execute([
        ':screen_id' => (int) $screenId,
        ':name' => $name,
        ':sort_order' => $nextSortOrder,
        ':is_archive' => 0,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    $panelId = (int) $pdo->lastInsertId();

    $panelStmt = $pdo->prepare(
        'SELECT id, screen_id, name, sort_order, is_archive, created_at, updated_at
         FROM panels
         WHERE id = :id
         LIMIT 1'
    );
    $panelStmt->execute([':id' => $panelId]);
    $panel = $panelStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($panel)) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'Panel was created but could not be fetched.',
        ]);
    }

    jsonResponse(201, [
        'success' => true,
        'panel' => $panel,
    ]);
} catch (Throwable $exception) {
    jsonResponse(500, [
        'success' => false,
        'error' => 'Internal server error.',
    ]);
}
