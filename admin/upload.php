<?php
require_once __DIR__ . '/auth.php';
requireAdmin();

$pdo = new PDO(
    "mysql:host=localhost;dbname=Classement VC;charset=utf8mb4",
    "root",
    "",
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$message = '';
$error = '';

if (isset($_SESSION['upload_message'])) {
    $message = $_SESSION['upload_message'];
    unset($_SESSION['upload_message']);
}
if (isset($_SESSION['upload_error'])) {
    $error = $_SESSION['upload_error'];
    unset($_SESSION['upload_error']);
}

function sanitizeTableName(string $name): string {
    $name = preg_replace('/[^a-z0-9_]+/i', '_', trim($name));
    $name = preg_replace('/_+/', '_', $name);
    $name = strtolower(trim($name, '_'));
    if ($name === '') {
        $name = 'import';
    }
    return substr($name, 0, 40);
}

function detectDelimiter(string $headerLine): string {
    $commas = substr_count($headerLine, ',');
    $semis = substr_count($headerLine, ';');
    return $semis >= $commas ? ';' : ',';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['csv']['name'])) {
        $error = 'Veuillez sélectionner un fichier CSV.';
    } elseif ($_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Erreur d upload : ' . $_FILES['csv']['error'] . '. Vérifiez la taille du fichier et réessayez.';
    } else {
        $uploaded = $_FILES['csv'];
        $filename = pathinfo($uploaded['name'], PATHINFO_FILENAME);
        $tableName = 'import_' . sanitizeTableName($filename) . '_' . date('Ymd_His');
        $targetPath = __DIR__ . '/../csv/uploads/' . basename($uploaded['name']);

        if (!is_uploaded_file($uploaded['tmp_name'])) {
            $error = 'Le fichier temporaire uploadé est invalide.';
        } elseif (!move_uploaded_file($uploaded['tmp_name'], $targetPath)) {
            $error = 'Impossible de déplacer le fichier uploadé vers "' . $targetPath . '". Vérifiez les permissions du dossier csv/uploads.';
        } else {
            $handle = fopen($targetPath, 'r');
            if (!$handle) {
                $error = 'Impossible d ouvrir le fichier CSV.';
            } else {
                $firstLine = fgets($handle);
                if ($firstLine === false) {
                    $error = 'Le fichier CSV est vide.';
                } else {
                    $delimiter = detectDelimiter($firstLine);
                    rewind($handle);
                    $columns = fgetcsv($handle, 0, $delimiter);
                    if (!$columns || count($columns) < 3) {
                        $error = 'Le fichier CSV doit contenir au moins trois colonnes.';
                    } else {
                        $mapping = [];
                        foreach ($columns as $i => $column) {
                            $key = strtolower(trim($column));
                            if (in_array($key, ['distance', 'épreuve', 'epreuve'], true)) {
                                $mapping[$i] = 'Distance';
                            } elseif (in_array($key, ['first_name', 'prenom', 'prénom'], true)) {
                                $mapping[$i] = 'First_Name';
                            } elseif (in_array($key, ['last_name', 'nom'], true)) {
                                $mapping[$i] = 'Last_Name';
                            } elseif (in_array($key, ['sex', 'sexe'], true)) {
                                $mapping[$i] = 'Sex';
                            } elseif ($key === 'age' || $key === 'groupe d\'age' || $key === 'age group') {
                                $mapping[$i] = 'Age';
                            } elseif (in_array($key, ['chip_time', 'chip time', 'chip', 'gun time'], true)) {
                                $mapping[$i] = 'Chip_Time';
                            } elseif (in_array($key, ['race', 'course'], true)) {
                                $mapping[$i] = 'Race';
                            } elseif (in_array($key, ['race_date', 'race date', 'date'], true)) {
                                $mapping[$i] = 'Race_Date';
                            }
                        }

                        if (!in_array('Distance', $mapping, true) || !in_array('First_Name', $mapping, true) || !in_array('Last_Name', $mapping, true) || !in_array('Chip_Time', $mapping, true)) {
                            $error = 'Le fichier CSV doit contenir au moins les colonnes Distance, Prénom, Nom et Chip Time.';
                        } else {
                            $pdo->beginTransaction();
                            $pdo->exec("CREATE TABLE `$tableName` (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                Distance VARCHAR(255),
                                First_Name VARCHAR(255),
                                Last_Name VARCHAR(255),
                                Sex VARCHAR(20),
                                Age INT,
                                Chip_Time VARCHAR(50),
                                Race VARCHAR(255),
                                Race_Date VARCHAR(50)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                            $stmt = $pdo->prepare("INSERT INTO `$tableName` (Distance, First_Name, Last_Name, Sex, Age, Chip_Time, Race, Race_Date)
                                VALUES (:Distance, :First_Name, :Last_Name, :Sex, :Age, :Chip_Time, :Race, :Race_Date)");

                            $rows = 0;
                            $skipped = 0;
                            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                                $row = [
                                    'Distance' => null,
                                    'First_Name' => null,
                                    'Last_Name' => null,
                                    'Sex' => null,
                                    'Age' => null,
                                    'Chip_Time' => null,
                                    'Race' => null,
                                ];
                                foreach ($mapping as $index => $columnName) {
                                    $row[$columnName] = $data[$index] ?? null;
                                }
                                if (!$row['Distance'] || !$row['First_Name'] || !$row['Last_Name'] || !$row['Chip_Time']) {
                                    $skipped++;
                                    continue;
                                }
                                $row['Age'] = $row['Age'] !== null ? (int) filter_var($row['Age'], FILTER_SANITIZE_NUMBER_INT) : null;
                                $stmt->execute([ 
                                    ':Distance' => trim($row['Distance']),
                                    ':First_Name' => trim($row['First_Name']),
                                    ':Last_Name' => trim($row['Last_Name']),
                                    ':Sex' => trim($row['Sex'] ?? ''),
                                    ':Age' => $row['Age'],
                                    ':Chip_Time' => trim($row['Chip_Time']),
                                    ':Race' => trim($row['Race'] ?? ''),
                                    ':Race_Date' => trim($row['Race_Date'] ?? ''),
                                ]);
                                $rows++;
                            }

                            $pdo->commit();
                            fclose($handle);
                            $_SESSION['upload_message'] = "Import terminé : $rows lignes ajoutées, $skipped lignes ignorées. Table créée : $tableName.";
                            header('Location: upload.php');
                            exit;
                        }
                    }
                }
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Admin Viens Courir - Import CSV</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{margin:0;background:#f4f6f8;font-family:system-ui,sans-serif;color:#22303f}
.page{max-width:900px;margin:40px auto;padding:30px;background:#fff;border-radius:24px;box-shadow:0 18px 50px rgba(0,0,0,0.08)}
header{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:28px}
header h1{margin:0;font-size:1.9rem;color:#00A3E0}
nav a{color:#00A3E0;text-decoration:none;font-weight:700}
form{display:grid;gap:18px}
label{font-weight:700}
input[type=file]{padding:10px}
input[type=text],button{width:100%;padding:12px 14px;border-radius:12px;border:1px solid #d7e1ed;font-size:1rem}
button{background:#00A3E0;color:#fff;border:none;cursor:pointer}
button:hover{background:#0088bf}
.message{padding:14px 16px;border-radius:14px;background:#e8f9ef;color:#1a5c3b}
.error{padding:14px 16px;border-radius:14px;background:#ffecec;color:#b11f2b}
.help{font-size:0.95rem;color:#556078}
</style>
</head>
<body>
<div class="page">
    <header>
        <div>
            <h1>Import CSV</h1>
            <div class="help">Sélectionnez un fichier CSV pour l'ajouter à la base de données.</div>
        </div>
        <nav><a href="logout.php">Déconnexion</a></nav>
    </header>

    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <label for="csv">Fichier CSV</label>
        <input id="csv" name="csv" type="file" accept=".csv" required>

        <button type="submit">Importer le fichier</button>
    </form>
    <p class="help">Le fichier doit inclure au moins les colonnes Distance, Prénom, Nom et Chip Time. Les autres colonnes sont optionnelles.</p>
</div>
</body>
</html>
