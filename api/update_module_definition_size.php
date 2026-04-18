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

function parsePositiveIntegerField(array $data, string $field): int
{
    if (!array_key_exists($field, $data) || filter_var($data[$field], FILTER_VALIDATE_INT) === false) {
        jsonResponse(400, [
            'success' => false,
            'error' => sprintf('%s must be a positive integer.', $field),
        ]);
    }

    $value = (int) $data[$field];
    if ($value <= 0) {
        jsonResponse(400, [
            'success' => false,
            'error' => sprintf('%s must be a positive integer.', $field),
        ]);
    }

    return $value;
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

    $type = trim((string) ($payload['type'] ?? ''));
    $defaultWidth = parsePositiveIntegerField($payload, 'default_width');
    $defaultHeight = parsePositiveIntegerField($payload, 'default_height');

    if ($type === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'type must not be empty.',
        ]);
    }

    $findStmt = $pdo->prepare(
        'SELECT id FROM module_definitions WHERE code = :code LIMIT 1'
    );
    $findStmt->execute([':code' => $type]);
    $definitionId = $findStmt->fetchColumn();

    if ($definitionId === false) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'type does not exist in module_definitions.',
        ]);
    }

    $updateStmt = $pdo->prepare(
        'UPDATE module_definitions
         SET default_width = :default_width,
             default_height = :default_height,
             updated_at = :updated_at
         WHERE id = :id'
    );

    $updateStmt->execute([
        ':default_width' => $defaultWidth,
        ':default_height' => $defaultHeight,
        ':updated_at' => date('c'),
        ':id' => (int) $definitionId,
    ]);

    $selectStmt = $pdo->prepare(
        'SELECT id, code, name, html_path, default_width, default_height, is_native, created_at, updated_at
         FROM module_definitions
         WHERE id = :id
         LIMIT 1'
    );
    $selectStmt->execute([':id' => (int) $definitionId]);
    $moduleDefinition = $selectStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($moduleDefinition)) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'Module definition was updated but could not be fetched.',
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'module_definition' => $moduleDefinition,
    ]);
} catch (Throwable $exception) {
    jsonResponse(500, [
        'success' => false,
        'error' => 'Internal server error.',
    ]);
}
