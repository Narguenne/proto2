<?php

function handleUploadActions(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $action = $_POST['action'] ?? 'upload';

    if ($action === 'update_history') {
        handleHistoryUpdate($pdo);
        return;
    }

    if ($action === 'delete_import') {
        handleImportDelete($pdo);
        return;
    }

    handleCsvUpload($pdo);
}

function handleHistoryUpdate(PDO $pdo): void
{
    $importId = isset($_POST['import_id']) ? (int) $_POST['import_id'] : 0;
    $displayName = trim($_POST['display_name'] ?? '');

    if ($importId <= 0 || $displayName === '') {
        $_SESSION['upload_error'] = 'Données invalides pour la mise à jour.';
        header('Location: upload.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE imports
            SET display_name = :display_name
            WHERE id = :id
        ");
        $stmt->execute([
            ':display_name' => $displayName,
            ':id' => $importId,
        ]);

        $stmt = $pdo->prepare("
            UPDATE race_results
            SET Race = :race
            WHERE import_id = :import_id
        ");
        $stmt->execute([
            ':race' => $displayName,
            ':import_id' => $importId,
        ]);

        $pdo->commit();
        $_SESSION['upload_message'] = 'Nom à afficher mis à jour avec succès.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['upload_error'] = 'Impossible de mettre à jour l’historique.';
    }

    header('Location: upload.php');
    exit;
}

function handleImportDelete(PDO $pdo): void
{
    $importId = isset($_POST['import_id']) ? (int) $_POST['import_id'] : 0;

    if ($importId <= 0) {
        $_SESSION['upload_error'] = 'Import invalide.';
        header('Location: upload.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM race_results WHERE import_id = :import_id");
        $stmt->execute([
            ':import_id' => $importId,
        ]);

        $stmt = $pdo->prepare("DELETE FROM imports WHERE id = :id");
        $stmt->execute([
            ':id' => $importId,
        ]);

        $pdo->commit();
        $_SESSION['upload_message'] = 'Import supprimé avec succès.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['upload_error'] = 'Impossible de supprimer cet import.';
    }

    header('Location: upload.php');
    exit;
}

function handleCsvUpload(PDO $pdo): void
{
    $displayName = trim($_POST['display_name'] ?? '');

    if ($displayName === '') {
        $_SESSION['upload_error'] = 'Veuillez saisir un Nom à afficher.';
        header('Location: upload.php');
        exit;
    }

    if (empty($_FILES['csv']['name'])) {
        $_SESSION['upload_error'] = 'Veuillez sélectionner un fichier CSV.';
        header('Location: upload.php');
        exit;
    }

    if ($_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['upload_error'] = 'Erreur d’upload : ' . $_FILES['csv']['error'];
        header('Location: upload.php');
        exit;
    }

    $uploaded = $_FILES['csv'];
    $filename = pathinfo($uploaded['name'], PATHINFO_FILENAME);

    if (!is_uploaded_file($uploaded['tmp_name'])) {
        $_SESSION['upload_error'] = 'Le fichier uploadé est invalide.';
        header('Location: upload.php');
        exit;
    }

    $handle = fopen($uploaded['tmp_name'], 'r');

    if (!$handle) {
        $_SESSION['upload_error'] = 'Impossible d’ouvrir le fichier CSV.';
        header('Location: upload.php');
        exit;
    }

    $firstLine = fgets($handle);

    if ($firstLine === false) {
        fclose($handle);
        $_SESSION['upload_error'] = 'Le fichier CSV est vide.';
        header('Location: upload.php');
        exit;
    }

    $delimiter = detectDelimiter($firstLine);
    rewind($handle);

    $columns = fgetcsv($handle, 0, $delimiter);

    if (!$columns) {
        fclose($handle);
        $_SESSION['upload_error'] = 'Impossible de lire les colonnes du CSV.';
        header('Location: upload.php');
        exit;
    }

    $mapping = [];

    foreach ($columns as $i => $column) {
        $column = preg_replace('/^\xEF\xBB\xBF/', '', (string) $column);
        $key = strtolower(trim($column));

        if ($key === 'first_name') {
            $mapping[$i] = 'First_Name';
        } elseif ($key === 'last_name') {
            $mapping[$i] = 'Last_Name';
        } elseif ($key === 'distance') {
            $mapping[$i] = 'Distance';
        } elseif ($key === 'chip_time') {
            $mapping[$i] = 'Chip_Time';
        } elseif ($key === 'sex') {
            $mapping[$i] = 'Sex';
        } elseif ($key === 'age') {
            $mapping[$i] = 'Age';
        }
    }

    $required = ['First_Name', 'Last_Name', 'Distance', 'Chip_Time', 'Sex', 'Age'];
    $missing = array_diff($required, array_values($mapping));

    if (!empty($missing)) {
        fclose($handle);
        $_SESSION['upload_error'] = 'Le fichier CSV doit contenir les colonnes : First_Name, Last_Name, Distance, Chip_Time, Sex, Age.';
        header('Location: upload.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO imports (source_name, display_name, filename)
            VALUES (:source_name, :display_name, :filename)
        ");
        $stmt->execute([
            ':source_name' => basename($uploaded['name']),
            ':display_name' => $displayName,
            ':filename' => $filename,
        ]);

        $importId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO race_results (
                import_id, Distance, First_Name, Last_Name, Sex, Age, Chip_Time, Race
            ) VALUES (
                :import_id, :Distance, :First_Name, :Last_Name, :Sex, :Age, :Chip_Time, :Race
            )
        ");

        $rows = 0;
        $skipped = 0;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $row = [
                'First_Name' => null,
                'Last_Name' => null,
                'Distance' => null,
                'Chip_Time' => null,
                'Sex' => null,
                'Age' => null,
            ];

            foreach ($mapping as $index => $columnName) {
                $row[$columnName] = $data[$index] ?? null;
            }

            if (
                trim((string) $row['First_Name']) === '' ||
                trim((string) $row['Last_Name']) === '' ||
                trim((string) $row['Distance']) === '' ||
                trim((string) $row['Chip_Time']) === '' ||
                trim((string) $row['Sex']) === '' ||
                trim((string) $row['Age']) === ''
            ) {
                $skipped++;
                continue;
            }

            $age = (int) filter_var($row['Age'], FILTER_SANITIZE_NUMBER_INT);

            $stmt->execute([
                ':import_id' => $importId,
                ':Distance' => trim((string) $row['Distance']),
                ':First_Name' => trim((string) $row['First_Name']),
                ':Last_Name' => trim((string) $row['Last_Name']),
                ':Sex' => trim((string) $row['Sex']),
                ':Age' => $age,
                ':Chip_Time' => trim((string) $row['Chip_Time']),
                ':Race' => $displayName,
            ]);

            $rows++;
        }

        fclose($handle);

        $stmt = $pdo->prepare("
            UPDATE imports
            SET row_count = :row_count, skipped_count = :skipped_count
            WHERE id = :id
        ");
        $stmt->execute([
            ':row_count' => $rows,
            ':skipped_count' => $skipped,
            ':id' => $importId,
        ]);

        $pdo->commit();
        $_SESSION['upload_message'] = "Import terminé : $rows lignes ajoutées, $skipped lignes ignorées.";
    } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['upload_error'] = 'Erreur import : ' . $e->getMessage();
    }

    header('Location: upload.php');
    exit;
}