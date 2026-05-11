<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$epreuve = trim($_GET['epreuve'] ?? 'all');
$course = trim($_GET['course'] ?? 'all');
$sexe = trim($_GET['sexe'] ?? 'all');
$age = trim($_GET['age'] ?? 'all');
$nom = trim($_GET['nom'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 100;
$offset = ($page - 1) * $limit;

$where = ' WHERE 1=1 ';
$params = [];

if ($epreuve !== 'all' && $epreuve !== '') {
    $where .= ' AND Distance = :epreuve';
    $params['epreuve'] = $epreuve;
}

if ($course !== 'all' && $course !== '') {
    $where .= ' AND Race = :course';
    $params['course'] = $course;
}

if ($sexe !== 'all' && $sexe !== '') {
    $where .= ' AND Sex = :sexe';
    $params['sexe'] = $sexe;
}

if ($age !== 'all' && strpos($age, '-') !== false) {
    [$minAge, $maxAge] = array_map('intval', explode('-', $age, 2));
    $where .= ' AND Age BETWEEN :min_age AND :max_age';
    $params['min_age'] = $minAge;
    $params['max_age'] = $maxAge;
}

if ($nom !== '') {
    $where .= ' AND (First_Name LIKE :nom OR Last_Name LIKE :nom)';
    $params['nom'] = '%' . $nom . '%';
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM race_results {$where}");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

$statsStmt = $pdo->prepare("SELECT Chip_Time FROM race_results {$where}");
$statsStmt->execute($params);
$allRawTimes = $statsStmt->fetchAll(PDO::FETCH_COLUMN);
$allTimes = array_values(array_filter(array_map('timeToSeconds', $allRawTimes)));

$mean = empty($allTimes) ? '00:00:00' : secondsToTime(array_sum($allTimes) / count($allTimes));
$median = medianFromTimes($allTimes);

$variance = 0.0;
if (!empty($allTimes)) {
    $average = array_sum($allTimes) / count($allTimes);
    foreach ($allTimes as $time) {
        $variance += ($time - $average) ** 2;
    }
    $variance /= count($allTimes);
}
$std = secondsToTime(sqrt($variance));

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportStmt = $pdo->prepare("SELECT Distance, Race, First_Name, Last_Name, Sex, Age, Chip_Time FROM race_results {$where} ORDER BY Chip_Time ASC");
    $exportStmt->execute($params);
    $exportRows = $exportStmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=\"race_results.csv\"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Rang', 'Prénom', 'Nom', 'Genre', 'Âge', 'Temps', 'Distance', 'Course']);

    $rank = 1;
    foreach ($exportRows as $row) {
        fputcsv($output, [
            $rank++,
            $row['First_Name'],
            $row['Last_Name'],
            $row['Sex'],
            $row['Age'],
            substr((string) $row['Chip_Time'], 0, 8),
            $row['Distance'],
            $row['Race'],
        ]);
    }

    fclose($output);
    exit;
}

$listStmt = $pdo->prepare("SELECT Distance, Race, First_Name, Last_Name, Sex, Age, Chip_Time FROM race_results {$where} ORDER BY Chip_Time ASC LIMIT :limit OFFSET :offset");
foreach ($params as $key => $value) {
    $listStmt->bindValue(':' . $key, $value);
}
$listStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$rows = $listStmt->fetchAll();

$hist = [];
foreach ($allTimes as $time) {
    $bucket = (int) floor($time / 60);
    $hist[$bucket] = ($hist[$bucket] ?? 0) + 1;
}
ksort($hist);

$chartLabels = array_map(
    fn($bucket) => sprintf('%02d:%02d', floor($bucket / 60), $bucket % 60),
    array_keys($hist)
);
$chartValues = array_values($hist);

$epreuves = $pdo->query("SELECT DISTINCT Distance FROM race_results WHERE Distance IS NOT NULL AND Distance <> '' ORDER BY Distance")->fetchAll(PDO::FETCH_COLUMN);
$courses = $pdo->query("SELECT DISTINCT Race FROM race_results WHERE Race IS NOT NULL AND Race <> '' ORDER BY Race")->fetchAll(PDO::FETCH_COLUMN);

$queryWithoutPage = $_GET;
unset($queryWithoutPage['page']);
$resetQuery = http_build_query(array_filter($_GET, fn($key) => $key !== 'page', ARRAY_FILTER_USE_KEY));

require __DIR__ . '/views/classement.view.php';