<?php
/**
 * AJAX endpoint: Allocate a bed to a pending patient.
 * POST with JSON: { "patient_id": int, "bed_id": int }
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
$bedId = intval($input['bed_id'] ?? 0);

if ($patientId <= 0 || $bedId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid patient or bed ID']);
    exit;
}

$result = allocateBed($patientId, $bedId);
echo json_encode($result);
