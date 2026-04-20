<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/init_db.php';

header('Content-Type: application/json; charset=utf-8');

function failResponse(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'error' => $message,
    ]);
    exit;
}

function asIntId(mixed $value): ?int
{
    if (is_int($value) && $value > 0) {
        return $value;
    }

    if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
        return (int) $value;
    }

    return null;
}

function normalizeName(mixed $value, string $fallback): string
{
    $name = is_string($value) ? trim($value) : '';
    return $name !== '' ? $name : $fallback;
}

$rawInput = file_get_contents('php://input');
if ($rawInput === false || trim($rawInput) === '') {
    failResponse('Payload vacío.');
}

$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    failResponse('JSON inválido.');
}

if (!array_key_exists('screens', $payload) || !array_key_exists('panels', $payload) || !array_key_exists('modules', $payload)) {
    failResponse('Faltan campos requeridos: screens, panels, modules.');
}

if (!is_array($payload['screens']) || !is_array($payload['panels']) || !is_array($payload['modules'])) {
    failResponse('screens, panels y modules deben ser arrays.');
}

$pdo = getDatabaseConnection();
initializeDatabase($pdo);

try {
    $pdo->beginTransaction();

    $existingScreenIds = $pdo->query('SELECT id FROM screens')->fetchAll(PDO::FETCH_COLUMN, 0);
    $existingPanelIds = $pdo->query('SELECT id FROM panels')->fetchAll(PDO::FETCH_COLUMN, 0);
    $existingModuleIds = $pdo->query('SELECT id FROM modules')->fetchAll(PDO::FETCH_COLUMN, 0);

    $screenExists = array_fill_keys(array_map('intval', $existingScreenIds), true);
    $panelExists = array_fill_keys(array_map('intval', $existingPanelIds), true);
    $moduleExists = array_fill_keys(array_map('intval', $existingModuleIds), true);

    $screenIdMap = [];
    $panelIdMap = [];
    $keptScreenIds = [];
    $keptPanelIds = [];
    $keptModuleIds = [];

    $upsertScreen = $pdo->prepare(
        'INSERT INTO screens (id, name, created_at, updated_at)
         VALUES (:id, :name, :created_at, :updated_at)
         ON CONFLICT(id) DO UPDATE SET
            name = excluded.name,
            updated_at = excluded.updated_at'
    );
    $insertScreen = $pdo->prepare(
        'INSERT INTO screens (name, created_at, updated_at)
         VALUES (:name, :created_at, :updated_at)'
    );

    foreach ($payload['screens'] as $index => $screen) {
        if (!is_array($screen)) {
            failResponse("screens[$index] inválido.");
        }

        $clientIdRaw = $screen['id'] ?? null;
        $clientId = is_scalar($clientIdRaw) ? (string) $clientIdRaw : null;
        $screenName = normalizeName($screen['name'] ?? null, 'Pantalla');
        $id = asIntId($clientIdRaw);
        $now = date('c');

        if ($id !== null && isset($screenExists[$id])) {
            $upsertScreen->execute([
                ':id' => $id,
                ':name' => $screenName,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $screenDbId = $id;
        } else {
            $insertScreen->execute([
                ':name' => $screenName,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $screenDbId = (int) $pdo->lastInsertId();
        }

        $screenIdMap[$clientId ?? (string) $screenDbId] = $screenDbId;
        $keptScreenIds[$screenDbId] = true;
    }

    if ($keptScreenIds === []) {
        throw new RuntimeException('Debe existir al menos una pantalla.');
    }

    $upsertPanel = $pdo->prepare(
        'INSERT INTO panels (id, screen_id, name, sort_order, is_archive, created_at, updated_at)
         VALUES (:id, :screen_id, :name, :sort_order, :is_archive, :created_at, :updated_at)
         ON CONFLICT(id) DO UPDATE SET
            screen_id = excluded.screen_id,
            name = excluded.name,
            sort_order = excluded.sort_order,
            is_archive = excluded.is_archive,
            updated_at = excluded.updated_at'
    );
    $insertPanel = $pdo->prepare(
        'INSERT INTO panels (screen_id, name, sort_order, is_archive, created_at, updated_at)
         VALUES (:screen_id, :name, :sort_order, :is_archive, :created_at, :updated_at)'
    );

    foreach ($payload['panels'] as $index => $panel) {
        if (!is_array($panel)) {
            failResponse("panels[$index] inválido.");
        }

        $clientIdRaw = $panel['id'] ?? null;
        $clientId = is_scalar($clientIdRaw) ? (string) $clientIdRaw : null;

        $screenRefRaw = $panel['screen_id'] ?? null;
        $screenRefKey = is_scalar($screenRefRaw) ? (string) $screenRefRaw : null;
        $screenId = $screenRefKey !== null && array_key_exists($screenRefKey, $screenIdMap)
            ? $screenIdMap[$screenRefKey]
            : asIntId($screenRefRaw);

        if ($screenId === null || !isset($keptScreenIds[$screenId])) {
            failResponse("panels[$index].screen_id inválido.");
        }

        $panelName = normalizeName($panel['name'] ?? null, 'Panel');
        $sortOrder = (int) ($panel['sort_order'] ?? $index);
        $isArchive = !empty($panel['is_archive']) ? 1 : 0;
        $id = asIntId($clientIdRaw);
        $now = date('c');

        if ($id !== null && isset($panelExists[$id])) {
            $upsertPanel->execute([
                ':id' => $id,
                ':screen_id' => $screenId,
                ':name' => $panelName,
                ':sort_order' => $sortOrder,
                ':is_archive' => $isArchive,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $panelDbId = $id;
        } else {
            $insertPanel->execute([
                ':screen_id' => $screenId,
                ':name' => $panelName,
                ':sort_order' => $sortOrder,
                ':is_archive' => $isArchive,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $panelDbId = (int) $pdo->lastInsertId();
        }

        $panelIdMap[$clientId ?? (string) $panelDbId] = $panelDbId;
        $keptPanelIds[$panelDbId] = true;
    }

    $upsertModule = $pdo->prepare(
        'INSERT INTO modules (id, panel_id, type, title, content, position_x, position_y, width, height, z_index, is_archived, created_at, updated_at)
         VALUES (:id, :panel_id, :type, :title, :content, :position_x, :position_y, :width, :height, :z_index, :is_archived, :created_at, :updated_at)
         ON CONFLICT(id) DO UPDATE SET
            panel_id = excluded.panel_id,
            type = excluded.type,
            title = excluded.title,
            content = excluded.content,
            position_x = excluded.position_x,
            position_y = excluded.position_y,
            width = excluded.width,
            height = excluded.height,
            z_index = excluded.z_index,
            is_archived = excluded.is_archived,
            updated_at = excluded.updated_at'
    );
    $insertModule = $pdo->prepare(
        'INSERT INTO modules (panel_id, type, title, content, position_x, position_y, width, height, z_index, is_archived, created_at, updated_at)
         VALUES (:panel_id, :type, :title, :content, :position_x, :position_y, :width, :height, :z_index, :is_archived, :created_at, :updated_at)'
    );

    foreach ($payload['modules'] as $index => $module) {
        if (!is_array($module)) {
            failResponse("modules[$index] inválido.");
        }

        $clientIdRaw = $module['id'] ?? null;

        $panelRefRaw = $module['panel_id'] ?? null;
        $panelRefKey = is_scalar($panelRefRaw) ? (string) $panelRefRaw : null;
        $panelId = $panelRefKey !== null && array_key_exists($panelRefKey, $panelIdMap)
            ? $panelIdMap[$panelRefKey]
            : asIntId($panelRefRaw);

        if ($panelId === null || !isset($keptPanelIds[$panelId])) {
            failResponse("modules[$index].panel_id inválido.");
        }

        $type = normalizeName($module['type'] ?? null, 'notes');
        $title = normalizeName($module['title'] ?? null, $type);
        $content = is_string($module['content'] ?? null) ? $module['content'] : '';
        $positionX = (int) ($module['position_x'] ?? 0);
        $positionY = (int) ($module['position_y'] ?? 0);
        $width = (int) ($module['width'] ?? 320);
        $height = (int) ($module['height'] ?? 220);
        $zIndex = (int) ($module['z_index'] ?? $index + 1);
        $isArchived = !empty($module['is_archived']) ? 1 : 0;
        $id = asIntId($clientIdRaw);
        $now = date('c');

        if ($id !== null && isset($moduleExists[$id])) {
            $upsertModule->execute([
                ':id' => $id,
                ':panel_id' => $panelId,
                ':type' => $type,
                ':title' => $title,
                ':content' => $content,
                ':position_x' => $positionX,
                ':position_y' => $positionY,
                ':width' => $width,
                ':height' => $height,
                ':z_index' => $zIndex,
                ':is_archived' => $isArchived,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $moduleDbId = $id;
        } else {
            $insertModule->execute([
                ':panel_id' => $panelId,
                ':type' => $type,
                ':title' => $title,
                ':content' => $content,
                ':position_x' => $positionX,
                ':position_y' => $positionY,
                ':width' => $width,
                ':height' => $height,
                ':z_index' => $zIndex,
                ':is_archived' => $isArchived,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $moduleDbId = (int) $pdo->lastInsertId();
        }

        $keptModuleIds[$moduleDbId] = true;
    }

    $existingModuleIdsInt = array_map('intval', $existingModuleIds);
    $existingPanelIdsInt = array_map('intval', $existingPanelIds);
    $existingScreenIdsInt = array_map('intval', $existingScreenIds);

    $moduleIdsToDelete = array_values(array_filter(
        $existingModuleIdsInt,
        static fn (int $id): bool => !isset($keptModuleIds[$id])
    ));
    if ($moduleIdsToDelete !== []) {
        $placeholders = implode(',', array_fill(0, count($moduleIdsToDelete), '?'));
        $stmt = $pdo->prepare("DELETE FROM modules WHERE id IN ($placeholders)");
        $stmt->execute($moduleIdsToDelete);
    }

    $panelIdsToDelete = array_values(array_filter(
        $existingPanelIdsInt,
        static fn (int $id): bool => !isset($keptPanelIds[$id])
    ));
    if ($panelIdsToDelete !== []) {
        $placeholders = implode(',', array_fill(0, count($panelIdsToDelete), '?'));
        $stmt = $pdo->prepare("DELETE FROM panels WHERE id IN ($placeholders)");
        $stmt->execute($panelIdsToDelete);
    }

    $screenIdsToDelete = array_values(array_filter(
        $existingScreenIdsInt,
        static fn (int $id): bool => !isset($keptScreenIds[$id])
    ));
    if ($screenIdsToDelete !== []) {
        $placeholders = implode(',', array_fill(0, count($screenIdsToDelete), '?'));
        $stmt = $pdo->prepare("DELETE FROM screens WHERE id IN ($placeholders)");
        $stmt->execute($screenIdsToDelete);
    }

    $pdo->commit();

    $screens = $pdo->query('SELECT id, name, created_at, updated_at FROM screens ORDER BY id ASC')
        ->fetchAll(PDO::FETCH_ASSOC);
    $panels = $pdo->query(
        'SELECT id, screen_id, name, sort_order, is_archive, created_at, updated_at
         FROM panels
         ORDER BY screen_id ASC, sort_order ASC, id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
    $modules = $pdo->query(
        'SELECT id, panel_id, type, title, content, position_x, position_y, width, height, z_index, is_archived, created_at, updated_at
         FROM modules
         ORDER BY panel_id ASC, z_index ASC, id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'screens' => $screens,
        'panels' => $panels,
        'modules' => $modules,
    ]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    failResponse($error->getMessage(), 500);
}
