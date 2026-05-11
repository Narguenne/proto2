<?php

function detectDelimiter(string $headerLine): string
{
    $commas = substr_count($headerLine, ',');
    $semis = substr_count($headerLine, ';');
    return $semis > $commas ? ';' : ',';
}

function ensureImportsSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS imports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source_name VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NULL,
            filename VARCHAR(255) NOT NULL,
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            row_count INT NOT NULL DEFAULT 0,
            skipped_count INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $columns = $pdo->query("SHOW COLUMNS FROM imports")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('display_name', $columns, true)) {
        $pdo->exec("ALTER TABLE imports ADD COLUMN display_name VARCHAR(255) NULL AFTER source_name");
    }
}

function ensureRaceResultsSchema(PDO $pdo): void
{
    $columns = $pdo->query("SHOW COLUMNS FROM race_results")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('id', $columns, true)) {
        $pdo->exec("ALTER TABLE race_results ADD COLUMN id INT NOT NULL FIRST");
        $pdo->exec("SET @i = 0");
        $pdo->exec("UPDATE race_results SET id = (@i := @i + 1)");
        $pdo->exec("ALTER TABLE race_results MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
    }

    if (!in_array('import_id', $columns, true)) {
        $pdo->exec("ALTER TABLE race_results ADD COLUMN import_id INT NULL AFTER id");
    }

    if (!in_array('Race', $columns, true)) {
        $pdo->exec("ALTER TABLE race_results ADD COLUMN Race VARCHAR(255) NULL AFTER Chip_Time");
    }

    if (!in_array('created_at', $columns, true)) {
        $pdo->exec("ALTER TABLE race_results ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER Race");
    }
}