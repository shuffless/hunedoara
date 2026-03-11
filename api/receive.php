<?php
/**
 * HTTP API endpoint for receiving HL7/XML patient data.
 *
 * POST /api/receive.php
 *
 * Accepts raw HL7 or XML in the request body.
 * Content-Type: application/hl7-v2  or  application/xml  or  text/plain
 *
 * Returns JSON with processing result.
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$rawData = file_get_contents('php://input');

if (empty(trim($rawData))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Empty request body.']);
    exit;
}

$senderIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$result = processIncomingData($rawData, $senderIp);

if ($result['success']) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'request_id' => $result['request_id'],
        'patient_name' => $result['patient_name'],
        'message' => 'Data received and processed successfully.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $result['error']
    ]);
}
