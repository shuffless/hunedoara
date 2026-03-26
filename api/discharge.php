<?php
/**
 * AJAX endpoint: Discharge (dismiss) an allocated patient and free the bed.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$patientId = $input['patient_id'] ?? null;

if (!$patientId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing patient_id.']);
    exit;
}

$result = dischargePatient($patientId);
echo json_encode($result);
