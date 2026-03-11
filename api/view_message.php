<?php
/**
 * AJAX endpoint: Get sent HL7 message details.
 * GET ?id=<message_id>
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
$stmt = $db->prepare('SELECT sm.*, pp.patient_name FROM sent_messages sm LEFT JOIN pending_patients pp ON sm.pending_patient_id = pp.id WHERE sm.id = ?');
$stmt->execute([$id]);
$message = $stmt->fetch();

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'Message not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'hl7_message' => $message['hl7_message'],
    'event_type' => $message['event_type'],
    'allocated_bed' => $message['allocated_bed'],
    'cancel_reason' => $message['cancel_reason'],
    'destination_response' => $message['destination_response'],
    'response_status' => $message['response_status'],
    'sent_at' => $message['sent_at'],
    'patient_name' => $message['patient_name']
]);
