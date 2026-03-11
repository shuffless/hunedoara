<?php
/**
 * AJAX endpoint: Cancel a pending patient.
 * POST with JSON: { "patient_id": int, "reason": string }
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
$patientId = intval($input['patient_id'] ?? 0);
$reason = trim($input['reason'] ?? '');

if ($patientId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid patient ID']);
    exit;
}

if ($reason === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cancellation reason is required']);
    exit;
}

$result = cancelPatient($patientId, $reason);
echo json_encode($result);
