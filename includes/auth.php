<?php
session_start();
require_once __DIR__ . '/db.php';

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

function isManager() {
    return isset($_SESSION['is_manager']) && $_SESSION['is_manager'] == 1;
}

function getCurrentUser() {
    return $_SESSION['username'] ?? null;
}

function login($username, $password) {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, username, password_hash, is_manager FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_manager'] = $user['is_manager'];
        return true;
    }
    return false;
}

function logout() {
    session_destroy();
    header('Location: index.php');
    exit;
}
