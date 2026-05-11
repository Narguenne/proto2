<?php
session_start();

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

$error = '';

$adminUsername = 'admin';
$adminPasswordHash = '$2y$10$jklLRIKKSyGnleJna6kUu.5gjHKtIkkffOg8l9cy61uk09UvvmpAq';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === $adminUsername && password_verify($password, $adminPasswordHash)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        header('Location: admin.php');
        exit;
    }

    $error = 'Identifiants invalides.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion administrateur</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            color: #1f2937;
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            padding: 32px;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        h1 {
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 28px;
        }

        p {
            margin-top: 0;
            margin-bottom: 24px;
            color: #6b7280;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        button {
            width: 100%;
            border: none;
            background: #2563eb;
            color: white;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .hint {
            margin-top: 16px;
            font-size: 14px;
            color: #6b7280;
        }

        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Connexion administrateur</h1>
        <p>Accès à l’espace admin et à l’import des résultats.</p>

        <?php if ($error !== ''): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="username">Nom d’utilisateur</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Se connecter</button>
        </form>

        <div class="hint">
            Identifiant : <code>admin</code><br>
            Mot de passe : <code>le mot de passe correspondant au hash</code>
        </div>
    </div>
</body>
</html>