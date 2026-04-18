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

    $screenCount = (int) $pdo->query('SELECT COUNT(*) FROM screens')->fetchColumn();
    if ($screenCount <= 1) {
        jsonResponse(400, ['success' => false, 'error' => 'Debe haber al menos una pantalla']);
    }

    $existsStmt = $pdo->prepare('SELECT id FROM screens WHERE id = :id LIMIT 1');
    $existsStmt->execute([':id' => $screenId]);
    if ($existsStmt->fetchColumn() === false) {
        jsonResponse(400, ['success' => false, 'error' => 'screen_id does not exist.']);
    }

    $pdo->beginTransaction();

    $panelIdsStmt = $pdo->prepare('SELECT id FROM panels WHERE screen_id = :screen_id');
    $panelIdsStmt->execute([':screen_id' => $screenId]);
    $panelIds = $panelIdsStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($panelIds)) {
        $placeholders = implode(',', array_fill(0, count($panelIds), '?'));
        $deleteModulesStmt = $pdo->prepare("DELETE FROM modules WHERE panel_id IN ($placeholders)");
        $deleteModulesStmt->execute($panelIds);
    }

    $deletePanelsStmt = $pdo->prepare('DELETE FROM panels WHERE screen_id = :screen_id');
    $deletePanelsStmt->execute([':screen_id' => $screenId]);

    $deleteScreenStmt = $pdo->prepare('DELETE FROM screens WHERE id = :id');
    $deleteScreenStmt->execute([':id' => $screenId]);

    $pdo->commit();

    jsonResponse(200, ['success' => true, 'screen_id' => $screenId]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(500, ['success' => false, 'error' => 'Internal server error.']);
}
