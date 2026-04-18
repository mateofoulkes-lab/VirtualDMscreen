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

function parsePositiveIntegerField(array $payload, string $field): int
{
    if (!array_key_exists($field, $payload) || filter_var($payload[$field], FILTER_VALIDATE_INT) === false) {
        jsonResponse(400, [
            'success' => false,
            'error' => sprintf('%s must be a positive integer.', $field),
        ]);
    }

    $value = (int) $payload[$field];
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

    $panelStmt = $pdo->prepare('SELECT id, screen_id, is_archive FROM panels WHERE id = :id LIMIT 1');
    $panelStmt->execute([':id' => $panelId]);
    $panel = $panelStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($panel)) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'panel_id does not exist.',
        ]);
    }

    if ((int) $panel['is_archive'] === 1) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Archive panel cannot be deleted.',
        ]);
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM panels WHERE screen_id = :screen_id AND is_archive = 0');
    $countStmt->execute([':screen_id' => (int) $panel['screen_id']]);
    $nonArchiveCount = (int) $countStmt->fetchColumn();

    if ($nonArchiveCount <= 1) {
        jsonResponse(400, [
            'success' => false,
            'error' => 'Debe haber al menos un panel',
        ]);
    }

    $pdo->beginTransaction();

    $deleteModulesStmt = $pdo->prepare('DELETE FROM modules WHERE panel_id = :panel_id');
    $deleteModulesStmt->execute([':panel_id' => $panelId]);

    $deletePanelStmt = $pdo->prepare('DELETE FROM panels WHERE id = :id');
    $deletePanelStmt->execute([':id' => $panelId]);

    $pdo->commit();

    jsonResponse(200, [
        'success' => true,
        'panel_id' => $panelId,
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
