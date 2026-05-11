<?php
session_start();

const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD_HASH = '$2y$10$NMcHv7s3iLz4HV4GdMGF9ukRWEYsLrzNO.51qzPFW5RrBCqYnFRsG';

function isAdmin(): bool {
    return !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: login.php');
        exit;
    }
}

function loginAdmin(string $username, string $password): bool {
    if ($username !== ADMIN_USERNAME) {
        return false;
    }
    if (!password_verify($password, ADMIN_PASSWORD_HASH)) {
        return false;
    }
    $_SESSION['admin'] = true;
    return true;
}

function logoutAdmin(): void {
    $_SESSION = [];
    if (session_id() !== '') {
        session_destroy();
    }
}
