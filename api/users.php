<?php
/**
 * AJAX endpoint: User management.
 * POST to add user: { "action": "add", "username": string, "password": string }
 * POST to delete user: { "action": "delete", "user_id": int }
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$db = getDB();

if ($action === 'add') {
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if ($username === '' || $password === '') {
        echo json_encode(['success' => false, 'error' => 'Username and password are required']);
        exit;
    }

    if (strlen($password) < 4) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 4 characters']);
        exit;
    }

    // Check duplicate
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Username already exists']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO users (username, password_hash, is_manager) VALUES (?, ?, 0)');
    $stmt->execute([$username, $hash]);

    echo json_encode([
        'success' => true,
        'user_id' => $db->lastInsertId(),
        'username' => $username
    ]);

} elseif ($action === 'delete') {
    // Only manager can delete
    if (!isManager()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only manager can delete users']);
        exit;
    }

    $userId = intval($input['user_id'] ?? 0);
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        exit;
    }

    // Cannot delete manager accounts
    $stmt = $db->prepare('SELECT is_manager FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    if ($user['is_manager']) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete manager accounts']);
        exit;
    }

    $stmt = $db->prepare('DELETE FROM users WHERE id = ? AND is_manager = 0');
    $stmt->execute([$userId]);

    echo json_encode(['success' => true]);

} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
