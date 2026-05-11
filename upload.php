<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/upload_helpers.php';
require_once __DIR__ . '/includes/upload_actions.php';

handleUploadActions($pdo);

$message = $_SESSION['upload_message'] ?? '';
$error = $_SESSION['upload_error'] ?? '';

unset($_SESSION['upload_message'], $_SESSION['upload_error']);

ensureImportsSchema($pdo);
ensureRaceResultsSchema($pdo);

$imports = $pdo->query("
    SELECT id, source_name, display_name, filename, uploaded_at, row_count, skipped_count
    FROM imports
    ORDER BY uploaded_at DESC, id DESC
")->fetchAll();

$returnQuery = http_build_query([
    'epreuve' => $_GET['epreuve'] ?? 'all',
    'course'  => $_GET['course'] ?? 'all',
    'sexe'    => $_GET['sexe'] ?? 'all',
    'age'     => $_GET['age'] ?? 'all',
    'nom'     => $_GET['nom'] ?? '',
    'page'    => $_GET['page'] ?? 1,
]);

require __DIR__ . '/views/upload_page.php';