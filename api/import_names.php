<?php
declare(strict_types=1);

function importNamesDataset(PDO $pdo): void
{
    $jsonPath = dirname(__DIR__) . '/data/names_200_each.json';
    if (!file_exists($jsonPath)) {
        return;
    }

    $rawJson = file_get_contents($jsonPath);
    if ($rawJson === false) {
        return;
    }

    $decoded = json_decode($rawJson, true);
    if (!is_array($decoded)) {
        return;
    }

    $allowedRaces = ['Human', 'Elf', 'Dwarf', 'Tiefling'];
    $now = date('c');

    $existsStmt = $pdo->prepare(
        'SELECT id
         FROM name_entries
         WHERE category = :category
           AND race = :race
           AND value = :value
           AND ((gender IS NULL AND :gender IS NULL) OR gender = :gender)
         LIMIT 1'
    );

    $insertStmt = $pdo->prepare(
        'INSERT INTO name_entries (category, race, gender, value, created_at)
         VALUES (:category, :race, :gender, :value, :created_at)'
    );

    foreach ($decoded as $race => $raceData) {
        if (!in_array($race, $allowedRaces, true) || !is_array($raceData)) {
            continue;
        }

        foreach (['male', 'female'] as $gender) {
            $firstNames = $raceData[$gender] ?? null;
            if (!is_array($firstNames)) {
                continue;
            }

            foreach ($firstNames as $firstName) {
                $value = trim((string) $firstName);
                if ($value === '') {
                    continue;
                }

                $existsStmt->execute([
                    ':category' => 'first_name',
                    ':race' => $race,
                    ':gender' => $gender,
                    ':value' => $value,
                ]);

                if ($existsStmt->fetchColumn() !== false) {
                    continue;
                }

                $insertStmt->execute([
                    ':category' => 'first_name',
                    ':race' => $race,
                    ':gender' => $gender,
                    ':value' => $value,
                    ':created_at' => $now,
                ]);
            }
        }

        $surnames = $raceData['surnames'] ?? null;
        if (!is_array($surnames)) {
            continue;
        }

        foreach ($surnames as $surname) {
            $value = trim((string) $surname);
            if ($value === '') {
                continue;
            }

            $existsStmt->execute([
                ':category' => 'surname',
                ':race' => $race,
                ':gender' => null,
                ':value' => $value,
            ]);

            if ($existsStmt->fetchColumn() !== false) {
                continue;
            }

            $insertStmt->execute([
                ':category' => 'surname',
                ':race' => $race,
                ':gender' => null,
                ':value' => $value,
                ':created_at' => $now,
            ]);
        }
    }
}
