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
        jsonResponse(400, ['success' => false, 'error' => 'Request body is required.']);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        jsonResponse(400, ['success' => false, 'error' => 'Invalid JSON body.']);
    }

    return $decoded;
}

function parsePositiveIntegerField(array $payload, string $field): int
{
    if (!array_key_exists($field, $payload) || filter_var($payload[$field], FILTER_VALIDATE_INT) === false) {
        jsonResponse(400, ['success' => false, 'error' => sprintf('%s must be a positive integer.', $field)]);
    }

    $value = (int) $payload[$field];
    if ($value <= 0) {
        jsonResponse(400, ['success' => false, 'error' => sprintf('%s must be a positive integer.', $field)]);
    }

    return $value;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonResponse(405, ['success' => false, 'error' => 'Method not allowed. Use POST.']);
}

try {
    $pdo = getDatabaseConnection();
    initializeDatabase($pdo);

    $payload = parseJsonBody();
    $screenId = parsePositiveIntegerField($payload, 'screen_id');
    $name = trim((string) ($payload['name'] ?? ''));

    if ($name === '') {
        jsonResponse(400, ['success' => false, 'error' => 'name must not be empty.']);
    }

    $existsStmt = $pdo->prepare('SELECT id FROM screens WHERE id = :id LIMIT 1');
    $existsStmt->execute([':id' => $screenId]);
    if ($existsStmt->fetchColumn() === false) {
        jsonResponse(400, ['success' => false, 'error' => 'screen_id does not exist.']);
    }

    $updateStmt = $pdo->prepare(
        'UPDATE screens
         SET name = :name, updated_at = :updated_at
         WHERE id = :id'
    );
    $updateStmt->execute([
        ':name' => $name,
        ':updated_at' => date('c'),
        ':id' => $screenId,
    ]);

    $screenStmt = $pdo->prepare('SELECT id, name, created_at, updated_at FROM screens WHERE id = :id LIMIT 1');
    $screenStmt->execute([':id' => $screenId]);
    $screen = $screenStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($screen)) {
        jsonResponse(500, ['success' => false, 'error' => 'Screen was updated but could not be fetched.']);
    }

    jsonResponse(200, ['success' => true, 'screen' => $screen]);
} catch (Throwable $exception) {
    jsonResponse(500, ['success' => false, 'error' => 'Internal server error.']);
}
