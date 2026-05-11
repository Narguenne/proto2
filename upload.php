<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/upload_helpers.php';
require_once __DIR__ . '/includes/upload_actions.php';

$message = $_SESSION['upload_message'] ?? '';
$error = $_SESSION['upload_error'] ?? '';

unset($_SESSION['upload_message'], $_SESSION['upload_error']);

ensureImportsSchema($pdo);
ensureRaceResultsSchema($pdo);

handleUploadActions($pdo);

$imports = $pdo->query("
    SELECT id, source_name, display_name, filename, uploaded_at, row_count, skipped_count
    FROM imports
    ORDER BY uploaded_at DESC, id DESC
")->fetchAll();

require __DIR__ . '/views/upload_page.php';