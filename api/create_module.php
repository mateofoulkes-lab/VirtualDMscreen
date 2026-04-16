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

function parseNumericField(array $data, string $field): int
{
    if (!array_key_exists($field, $data) || !is_numeric($data[$field])) {
        jsonResponse(400, [
            'success' => false,
            'error' => sprintf('%s must be numeric.', $field),
        ]);
    }

    return (int) $data[$field];
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

    $panelId = parsePositiveIntegerField($payload, 'panel_id');
    $type = trim((string) ($payload['type'] ?? ''));
    $title = trim((string) ($payload['title'] ?? ''));
    $positionX = parseNumericField($payload, 'position_x');
    $positionY = parseNumericField($payload, 'position_y');
    $width = parsePositiveIntegerField($payload, 'width');
    $height = parsePositiveIntegerField($payload, 'height');

    if ($type == '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'type must not be empty.',
        ]);
    }

    if ($title === '') {
        jsonResponse(400, [
            'success' => false,
            'error' => 'title must not be empty.',
        ]);
    }

    $panelExistsStmt = $pdo->prepare('SELECT id FROM panels WHERE id = :panel_id LIMIT 1');
    $panelExistsStmt->execute([':panel_id' => $panelId]);
    if ($panelExistsStmt->fetchColumn() === false) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'panel_id does not exist.',
        ]);
    }

    $typeExistsStmt = $pdo->prepare('SELECT code FROM module_definitions WHERE code = :code LIMIT 1');
    $typeExistsStmt->execute([':code' => $type]);
    if ($typeExistsStmt->fetchColumn() === false) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'type does not exist in module_definitions.',
        ]);
    }

    $pdo->beginTransaction();

    $zIndexStmt = $pdo->prepare('SELECT COALESCE(MAX(z_index), 0) AS max_z FROM modules WHERE panel_id = :panel_id');
    $zIndexStmt->execute([':panel_id' => $panelId]);
    $maxZ = (int) $zIndexStmt->fetchColumn();
    $nextZ = $maxZ + 1;

    $now = date('c');

    $insertStmt = $pdo->prepare(
        'INSERT INTO modules
         (panel_id, type, title, content, position_x, position_y, width, height, z_index, is_archived, created_at, updated_at)
         VALUES
         (:panel_id, :type, :title, :content, :position_x, :position_y, :width, :height, :z_index, :is_archived, :created_at, :updated_at)'
    );

    $insertStmt->execute([
        ':panel_id' => $panelId,
        ':type' => $type,
        ':title' => $title,
        ':content' => '',
        ':position_x' => $positionX,
        ':position_y' => $positionY,
        ':width' => $width,
        ':height' => $height,
        ':z_index' => $nextZ,
        ':is_archived' => 0,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    $moduleId = (int) $pdo->lastInsertId();

    $moduleStmt = $pdo->prepare(
        'SELECT id, panel_id, type, title, content, position_x, position_y, width, height, z_index, is_archived, created_at, updated_at
         FROM modules
         WHERE id = :id
         LIMIT 1'
    );
    $moduleStmt->execute([':id' => $moduleId]);
    $module = $moduleStmt->fetch(PDO::FETCH_ASSOC);

    $pdo->commit();

    if (!is_array($module)) {
        jsonResponse(500, [
            'success' => false,
            'error' => 'Module was inserted but could not be fetched.',
        ]);
    }

    jsonResponse(201, [
        'success' => true,
        'module' => $module,
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
