<?php
require_once __DIR__ . '/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (loginAdmin($username, $password)) {
        header('Location: upload.php');
        exit;
    }
    $error = 'Identifiants invalides.';
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Admin Viens Courir - Connexion</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{margin:0;background:#f4f6f8;font-family:system-ui,sans-serif;color:#22303f}
.container{max-width:420px;margin:80px auto;padding:32px;background:#fff;border-radius:22px;box-shadow:0 18px 50px rgba(0,0,0,0.08)}
h1{margin:0 0 18px;font-size:1.9rem;color:#00A3E0}
.form-group{margin-bottom:18px}
label{display:block;margin-bottom:8px;font-size:0.95rem}
input{width:100%;padding:12px 14px;border:1px solid #d7e1ed;border-radius:12px;font-size:1rem}
button{width:100%;padding:12px 14px;border:none;border-radius:12px;background:#00A3E0;color:#fff;font-size:1rem;cursor:pointer}
.error{margin:0 0 16px;padding:12px 14px;background:#ffecec;color:#b11f2b;border-radius:12px}
.footer{margin-top:14px;font-size:0.9rem;color:#667085;text-align:center}
</style>
</head>
<body>
<div class="container">
    <h1>Admin Viens Courir</h1>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif ?>
    <form method="post">
        <div class="form-group">
            <label for="username">Nom d'utilisateur</label>
            <input id="username" name="username" type="text" autocomplete="username" required>
        </div>
        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button type="submit">Se connecter</button>
    </form>
    <div class="footer">Identifiant : <strong>admin</strong> / Mot de passe : <strong>admin123</strong></div>
</div>
</body>
</html>
