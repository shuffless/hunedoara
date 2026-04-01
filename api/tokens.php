<?php
/**
 * AJAX endpoint: API Token management.
 * Only managers can manage tokens.
 *
 * POST to add token:    { "action": "add", "comment": string }
 * POST to delete token: { "action": "delete", "token_id": int }
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

if (!isManager()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only managers can manage API tokens']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$db = getDB();

if ($action === 'add') {
    $comment = trim($input['comment'] ?? '');

    // Generate a cryptographically secure random token (64 hex chars = 256 bits)
    $token = bin2hex(random_bytes(32));

    $stmt = $db->prepare('INSERT INTO api_tokens (token, comment) VALUES (?, ?)');
    $stmt->execute([$token, $comment !== '' ? $comment : null]);

    echo json_encode([
        'success'    => true,
        'token_id'   => (int) $db->lastInsertId(),
        'token'      => $token,
        'comment'    => $comment,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

} elseif ($action === 'delete') {
    $tokenId = intval($input['token_id'] ?? 0);

    if ($tokenId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid token ID']);
        exit;
    }

    $stmt = $db->prepare('DELETE FROM api_tokens WHERE id = ?');
    $stmt->execute([$tokenId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Token not found']);
        exit;
    }

    echo json_encode(['success' => true]);

} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
