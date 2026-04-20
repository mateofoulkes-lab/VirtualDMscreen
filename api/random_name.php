<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/init_db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    respond(405, [
        'success' => false,
        'error' => 'Method not allowed. Use GET.',
    ]);
}

$category = trim((string) ($_GET['category'] ?? ''));
$race = trim((string) ($_GET['race'] ?? ''));
$gender = trim((string) ($_GET['gender'] ?? ''));

$allowedCategories = ['names'];
$allowedRaces = ['cualquiera', 'Human', 'Elf', 'Dwarf', 'Tiefling'];
$allowedGenders = ['cualquiera', 'male', 'female'];

if (!in_array($category, $allowedCategories, true)) {
    respond(400, [
        'success' => false,
        'error' => 'Invalid category value.',
    ]);
}

if (!in_array($race, $allowedRaces, true)) {
    respond(400, [
        'success' => false,
        'error' => 'Invalid race value.',
    ]);
}

if (!in_array($gender, $allowedGenders, true)) {
    respond(400, [
        'success' => false,
        'error' => 'Invalid gender value.',
    ]);
}

try {
    $pdo = getDatabaseConnection();
    initializeDatabase($pdo);

    $firstNameQuery = 'SELECT race, gender, value FROM name_entries WHERE category = :category';
    $firstNameParams = [':category' => 'first_name'];

    if ($race !== 'cualquiera') {
        $firstNameQuery .= ' AND race = :race';
        $firstNameParams[':race'] = $race;
    }

    $selectedGender = $gender;
    if ($selectedGender === 'cualquiera') {
        $selectedGender = random_int(0, 1) === 0 ? 'male' : 'female';
    }

    $firstNameQuery .= ' AND gender = :gender ORDER BY RANDOM() LIMIT 1';
    $firstNameParams[':gender'] = $selectedGender;

    $firstNameStmt = $pdo->prepare($firstNameQuery);
    $firstNameStmt->execute($firstNameParams);
    $firstNameRow = $firstNameStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($firstNameRow)) {
        respond(404, [
            'success' => false,
            'error' => 'No first names found for the selected filters.',
        ]);
    }

    $surnameQuery = 'SELECT race, value FROM name_entries WHERE category = :category';
    $surnameParams = [':category' => 'surname'];

    if ($race !== 'cualquiera') {
        $surnameQuery .= ' AND race = :race';
        $surnameParams[':race'] = $race;
    }

    $surnameQuery .= ' ORDER BY RANDOM() LIMIT 1';

    $surnameStmt = $pdo->prepare($surnameQuery);
    $surnameStmt->execute($surnameParams);
    $surnameRow = $surnameStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($surnameRow)) {
        respond(404, [
            'success' => false,
            'error' => 'No surnames found for the selected filters.',
        ]);
    }

    $firstName = (string) ($firstNameRow['value'] ?? '');
    $surname = (string) ($surnameRow['value'] ?? '');
    $resolvedRace = (string) ($firstNameRow['race'] ?? '');
    $resolvedGender = (string) ($firstNameRow['gender'] ?? '');

    respond(200, [
        'success' => true,
        'result' => [
            'category' => 'names',
            'race' => $resolvedRace,
            'gender' => $resolvedGender,
            'first_name' => $firstName,
            'surname' => $surname,
            'full_name' => trim($firstName . ' ' . $surname),
        ],
    ]);
} catch (Throwable $exception) {
    respond(500, [
        'success' => false,
        'error' => 'Internal server error.',
    ]);
}
