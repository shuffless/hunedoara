<?php
/**
 * AJAX endpoint: Get raw request data and parsed fields.
 * GET ?id=<request_id>
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

$db = getDB();
$stmt = $db->prepare('SELECT raw_data, data_format, sender_ip, received_at FROM raw_requests WHERE id = ?');
$stmt->execute([$id]);
$request = $stmt->fetch();

if (!$request) {
    echo json_encode(['success' => false, 'error' => 'Request not found']);
    exit;
}

$fields = getPatientData($id);

echo json_encode([
    'success' => true,
    'raw_data' => $request['raw_data'],
    'data_format' => $request['data_format'],
    'sender_ip' => $request['sender_ip'],
    'received_at' => $request['received_at'],
    'fields' => $fields
]);
