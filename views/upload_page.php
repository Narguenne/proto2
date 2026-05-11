<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Import CSV</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f5f6fa;
            color: #1f2937;
        }

        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            padding: 24px;
            margin-bottom: 24px;
        }

        h1, h2 {
            margin-top: 0;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .topbar a {
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 8px;
            font-weight: 700;
            display: inline-block;
        }

        .btn-back {
            background: #e5e7eb;
            color: #111827;
        }

        .btn-logout {
            background: #111827;
            color: #fff;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .alert-success {
            background: #e8f7ea;
            color: #166534;
        }

        .alert-error {
            background: #feecec;
            color: #991b1b;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        input[type="text"],
        input[type="file"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            margin-bottom: 16px;
            box-sizing: border-box;
        }

        button {
            border: none;
            background: #2563eb;
            color: #fff;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        button:hover {
            background: #1d4ed8;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        th, td {
            text-align: left;
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        th {
            background: #f9fafb;
        }

        .history-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .history-form input[type="text"] {
            margin-bottom: 0;
            min-width: 220px;
        }

        .help {
            color: #4b5563;
            line-height: 1.6;
        }

        pre, code {
            background: #f3f4f6;
            border-radius: 8px;
        }

        pre {
            padding: 14px;
            overflow-x: auto;
        }

        @media (max-width: 900px) {
            .history-form {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="topbar">
        <a href="testdb.php?<?= htmlspecialchars($returnQuery, ENT_QUOTES, 'UTF-8') ?>" class="btn-back">Retour classement</a>
        <a href="logout.php" class="btn-logout">Déconnexion</a>
    </div>

    <div class="card">
        <h1>Importer un CSV</h1>

        <?php if ($message !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <label for="display_name">Nom à afficher</label>
            <input
                type="text"
                id="display_name"
                name="display_name"
                placeholder="Ex. Pompier Laval"
                required
            >

            <label for="csv">Fichier CSV</label>
            <input
                type="file"
                id="csv"
                name="csv"
                accept=".csv,text/csv"
                required
            >

            <button type="submit">Importer</button>
        </form>
    </div>

    <div class="card">
        <h2>Format CSV attendu</h2>
        <div class="help">
            <p>Le fichier doit utiliser ce format :</p>
            <pre>First_Name,Last_Name,Distance,Chip_Time,Sex,Age</pre>
            <p>Toutes les colonnes ci-dessus sont obligatoires.</p>
            <p>Le champ <strong>Nom à afficher</strong> est saisi dans le formulaire et sera utilisé comme nom de course dans les résultats.</p>
            <p>Le séparateur peut être une virgule ou un point-virgule.</p>
            <pre>First_Name,Last_Name,Distance,Chip_Time,Sex,Age
Jean,Dupont,Marathon,03:08:10,M,42
Marie,Tremblay,10 km,00:45:22,F,31</pre>
        </div>
    </div>

    <div class="card">
        <h2>Historique des imports</h2>

        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Fichier source</th>
                <th>Nom à afficher</th>
                <th>Date</th>
                <th>Lignes</th>
                <th>Ignorées</th>
                <th>Modification</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($imports)): ?>
                <tr>
                    <td colspan="7">Aucun import pour le moment.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($imports as $import): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $import['id'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($import['source_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($import['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($import['uploaded_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $import['row_count'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $import['skipped_count'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <form method="post" class="history-form">
                                <input type="hidden" name="action" value="update_history">
                                <input type="hidden" name="import_id" value="<?= htmlspecialchars((string) $import['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <input
                                    type="text"
                                    name="display_name"
                                    value="<?= htmlspecialchars((string) ($import['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="Nom à afficher"
                                    required
                                >
                                <button type="submit">Enregistrer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>