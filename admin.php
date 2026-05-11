<?php
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            color: #1f2937;
        }

        .container {
            max-width: 900px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 10px 28px rgba(0,0,0,0.08);
            padding: 28px;
        }

        h1 {
            margin-top: 0;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
        }

        .btn {
            text-decoration: none;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 700;
            display: inline-block;
        }

        .btn-primary {
            background: #2563eb;
            color: #fff;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #111827;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Espace Admin</h1>
            <p>Bienvenue <?= htmlspecialchars($_SESSION['admin_username'] ?? 'admin', ENT_QUOTES, 'UTF-8') ?>.</p>

            <div class="actions">
                <a href="upload.php" class="btn btn-primary">Importer un CSV</a>
                <a href="testdb.php" class="btn btn-secondary">Voir le classement</a>
                <a href="logout.php" class="btn btn-secondary">Se déconnecter</a>
            </div>
        </div>
    </div>
</body>
</html>