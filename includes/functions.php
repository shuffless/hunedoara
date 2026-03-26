<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/hl7.php';
require_once __DIR__ . '/xml_parser.php';

/**
 * Detect whether a raw message is HL7 or XML format.
 */
function detectFormat($rawData) {
    $trimmed = ltrim($rawData);
    // Check for MLLP framing or MSH header
    if (strpos($trimmed, 'MSH|') === 0 || strpos($trimmed, "\x0B" . 'MSH|') === 0 || strpos($trimmed, 'MSH') === 0) {
        return 'hl7';
    }
    if (strpos($trimmed, '<') === 0 || strpos($trimmed, '<?xml') === 0) {
        return 'xml';
    }
    // Strip MLLP framing and check again
    $stripped = trim($rawData, "\x0B\x1C\x0D\r\n ");
    if (strpos($stripped, 'MSH') === 0) {
        return 'hl7';
    }
    return 'xml'; // default to xml
}

/**
 * Generate a fingerprint from parsed patient data.
 * Uses key identifying fields to detect duplicates.
 */
function generateFingerprint($patientData) {
    $fields = [
        $patientData['message_control_id'] ?? '',
        $patientData['patient_id'] ?? '',
        $patientData['patient_name'] ?? '',
        $patientData['date_of_birth'] ?? '',
        $patientData['message_datetime'] ?? '',
        $patientData['message_type'] ?? '',
    ];
    return hash('sha256', implode('|', $fields));
}

/**
 * Process incoming patient data:
 * 1. Parse and generate fingerprint
 * 2. Check for duplicates
 * 3. Log raw request
 * 4. Store in EAV table
 * 5. Add to pending queue
 */
function processIncomingData($rawData, $senderIp) {
    $db = getDB();

    try {
        $format = detectFormat($rawData);

        // Step 1: Parse data first to generate fingerprint
        $patientData = [];
        if ($format === 'hl7') {
            $parser = new HL7Parser();
            $parser->parse($rawData);
            $patientData = $parser->extractPatientData();
        } else {
            $parser = new XMLPatientParser();
            $patientData = $parser->parse($rawData);
        }

        // Step 2: Generate fingerprint and check for duplicates
        $fingerprint = generateFingerprint($patientData);

        $stmt = $db->prepare('SELECT id FROM raw_requests WHERE fingerprint = ?');
        $stmt->execute([$fingerprint]);
        $existing = $stmt->fetch();

        if ($existing) {
            return ['success' => false, 'error' => 'Duplicate request detected (fingerprint match with request #' . $existing['id'] . ').'];
        }

        $db->beginTransaction();

        // Step 3: Log raw request with fingerprint
        $stmt = $db->prepare('INSERT INTO raw_requests (raw_data, data_format, sender_ip, fingerprint, received_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$rawData, $format, $senderIp, $fingerprint]);
        $requestId = $db->lastInsertId();

        // Step 4: Store in EAV table
        $stmt = $db->prepare('INSERT INTO patient_data (id, field_name, field_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)');
        foreach ($patientData as $fieldName => $value) {
            $stmt->execute([$requestId, $fieldName, $value]);
        }

        // Step 5: Add to pending queue
        $patientName = $patientData['patient_name']
            ?? trim(($patientData['patient_first_name'] ?? '') . ' ' . ($patientData['patient_last_name'] ?? ''))
            ?: 'Unknown Patient';

        $stmt = $db->prepare('INSERT INTO pending_patients (request_id, patient_name, status, created_at) VALUES (?, ?, "pending", NOW())');
        $stmt->execute([$requestId, $patientName]);

        $db->commit();

        return ['success' => true, 'request_id' => $requestId, 'patient_name' => $patientName];

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        logError('Failed to process incoming data: ' . $e->getMessage(), 'process_incoming');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get patient data from EAV table for a given request ID.
 */
function getPatientData($requestId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT field_name, field_value FROM patient_data WHERE id = ?');
    $stmt->execute([$requestId]);
    $rows = $stmt->fetchAll();

    $data = [];
    foreach ($rows as $row) {
        $data[$row['field_name']] = $row['field_value'];
    }
    return $data;
}

/**
 * Get list of available (unoccupied) beds.
 */
function getAvailableBeds() {
    $db = getDB();
    $stmt = $db->query('SELECT id, bed_name FROM beds WHERE is_occupied = 0 ORDER BY id');
    return $stmt->fetchAll();
}

/**
 * Get pending patients list.
 */
function getPendingPatients() {
    $db = getDB();
    $stmt = $db->query('SELECT pp.id, pp.request_id, pp.patient_name, pp.created_at FROM pending_patients pp WHERE pp.status = "pending" ORDER BY pp.created_at ASC');
    return $stmt->fetchAll();
}

/**
 * Allocate a patient to a bed.
 * Builds HL7, sends to destination, logs the result.
 */
function allocateBed($pendingPatientId, $bedId) {
    $db = getDB();

    try {
        $db->beginTransaction();

        // Get pending patient info
        $stmt = $db->prepare('SELECT pp.*, b.bed_name FROM pending_patients pp, beds b WHERE pp.id = ? AND b.id = ? AND pp.status = "pending" AND b.is_occupied = 0');
        $stmt->execute([$pendingPatientId, $bedId]);
        $info = $stmt->fetch();

        if (!$info) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Patient or bed not available'];
        }

        // Get patient EAV data
        $patientData = getPatientData($info['request_id']);

        // Build HL7 message
        $hl7Message = HL7Parser::buildADT_A01($patientData, $info['bed_name']);

        // Send to destination
        $response = HL7Parser::sendMessage($hl7Message);
        $responseStatus = ($response !== false && $response !== '') ? 'success' : 'failure';

        if ($response === false) {
            $response = 'Connection failed';
            $responseStatus = 'failure';
        }

        // Log sent message
        $stmt = $db->prepare('INSERT INTO sent_messages (pending_patient_id, hl7_message, event_type, allocated_bed, cancel_reason, destination_response, response_status, sent_at) VALUES (?, ?, "bed_allocation", ?, NULL, ?, ?, NOW())');
        $stmt->execute([$pendingPatientId, $hl7Message, $info['bed_name'], $response, $responseStatus]);

        // Update pending patient status
        $stmt = $db->prepare('UPDATE pending_patients SET status = "allocated" WHERE id = ?');
        $stmt->execute([$pendingPatientId]);

        // Mark bed as occupied
        $stmt = $db->prepare('UPDATE beds SET is_occupied = 1, occupied_by = ? WHERE id = ?');
        $stmt->execute([$pendingPatientId, $bedId]);

        $db->commit();

        return [
            'success' => true,
            'bed_name' => $info['bed_name'],
            'response_status' => $responseStatus,
            'destination_response' => $response
        ];

    } catch (Exception $e) {
        $db->rollBack();
        logError('Bed allocation failed: ' . $e->getMessage(), 'allocate_bed');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Cancel a pending patient with a reason.
 */
function cancelPatient($pendingPatientId, $reason) {
    $db = getDB();

    try {
        $db->beginTransaction();

        // Get pending patient info
        $stmt = $db->prepare('SELECT * FROM pending_patients WHERE id = ? AND status = "pending"');
        $stmt->execute([$pendingPatientId]);
        $info = $stmt->fetch();

        if (!$info) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Patient not found or not pending'];
        }

        // Get patient EAV data
        $patientData = getPatientData($info['request_id']);

        // Build cancellation HL7 message
        $hl7Message = HL7Parser::buildADT_A11($patientData, $reason);

        // Send to destination
        $response = HL7Parser::sendMessage($hl7Message);
        $responseStatus = ($response !== false && $response !== '') ? 'success' : 'failure';

        if ($response === false) {
            $response = 'Connection failed';
            $responseStatus = 'failure';
        }

        // Log sent message
        $stmt = $db->prepare('INSERT INTO sent_messages (pending_patient_id, hl7_message, event_type, allocated_bed, cancel_reason, destination_response, response_status, sent_at) VALUES (?, ?, "cancellation", NULL, ?, ?, ?, NOW())');
        $stmt->execute([$pendingPatientId, $hl7Message, $reason, $response, $responseStatus]);

        // Update pending patient status
        $stmt = $db->prepare('UPDATE pending_patients SET status = "cancelled" WHERE id = ?');
        $stmt->execute([$pendingPatientId]);

        $db->commit();

        return ['success' => true, 'response_status' => $responseStatus];

    } catch (Exception $e) {
        $db->rollBack();
        logError('Patient cancellation failed: ' . $e->getMessage(), 'cancel_patient');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get allocated patients with their bed info.
 */
function getAllocatedPatients() {
    $db = getDB();
    $stmt = $db->query('SELECT pp.id, pp.patient_name, pp.created_at, b.bed_name FROM pending_patients pp INNER JOIN beds b ON b.occupied_by = pp.id WHERE pp.status = "allocated" ORDER BY pp.created_at ASC');
    return $stmt->fetchAll();
}

/**
 * Discharge a patient and free the bed.
 */
function dischargePatient($pendingPatientId) {
    $db = getDB();

    try {
        $db->beginTransaction();

        $stmt = $db->prepare('SELECT pp.id, pp.request_id, pp.patient_name, b.id AS bed_id, b.bed_name FROM pending_patients pp INNER JOIN beds b ON b.occupied_by = pp.id WHERE pp.id = ? AND pp.status = "allocated"');
        $stmt->execute([$pendingPatientId]);
        $info = $stmt->fetch();

        if (!$info) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Patient not found or not allocated.'];
        }

        // Get patient EAV data
        $patientData = getPatientData($info['request_id']);

        // Build and send ADT^A03 discharge message
        $hl7Message = HL7Parser::buildADT_A03($patientData, $info['bed_name']);
        $response = HL7Parser::sendMessage($hl7Message);
        $responseStatus = ($response !== false && $response !== '') ? 'success' : 'failure';

        if ($response === false) {
            $response = 'Connection failed';
            $responseStatus = 'failure';
        }

        // Log sent message
        $stmt = $db->prepare('INSERT INTO sent_messages (pending_patient_id, hl7_message, event_type, allocated_bed, cancel_reason, destination_response, response_status, sent_at) VALUES (?, ?, "discharge", ?, NULL, ?, ?, NOW())');
        $stmt->execute([$pendingPatientId, $hl7Message, $info['bed_name'], $response, $responseStatus]);

        // Free the bed
        $stmt = $db->prepare('UPDATE beds SET is_occupied = 0, occupied_by = NULL WHERE id = ?');
        $stmt->execute([$info['bed_id']]);

        // Update patient status to discharged
        $stmt = $db->prepare('UPDATE pending_patients SET status = "discharged" WHERE id = ?');
        $stmt->execute([$pendingPatientId]);

        $db->commit();

        return ['success' => true, 'bed_name' => $info['bed_name'], 'patient_name' => $info['patient_name'], 'response_status' => $responseStatus, 'destination_response' => $response];

    } catch (Exception $e) {
        $db->rollBack();
        logError('Discharge failed: ' . $e->getMessage(), 'discharge_patient');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get statistics for dashboard.
 */
function getStats() {
    $db = getDB();

    $stats = [];

    // Requests stats
    $stats['requests_total'] = $db->query('SELECT COUNT(*) FROM raw_requests')->fetchColumn();
    $stats['requests_today'] = $db->query("SELECT COUNT(*) FROM raw_requests WHERE DATE(received_at) = CURDATE()")->fetchColumn();
    $stats['requests_month'] = $db->query("SELECT COUNT(*) FROM raw_requests WHERE YEAR(received_at) = YEAR(CURDATE()) AND MONTH(received_at) = MONTH(CURDATE())")->fetchColumn();

    // Messages stats
    $stats['messages_total'] = $db->query('SELECT COUNT(*) FROM sent_messages')->fetchColumn();
    $stats['messages_today'] = $db->query("SELECT COUNT(*) FROM sent_messages WHERE DATE(sent_at) = CURDATE()")->fetchColumn();
    $stats['messages_month'] = $db->query("SELECT COUNT(*) FROM sent_messages WHERE YEAR(sent_at) = YEAR(CURDATE()) AND MONTH(sent_at) = MONTH(CURDATE())")->fetchColumn();

    // Last 10 requests
    $stats['last_requests'] = $db->query('SELECT id, data_format, sender_ip, received_at FROM raw_requests ORDER BY received_at DESC LIMIT 10')->fetchAll();

    // Last 10 sent messages
    $stats['last_messages'] = $db->query('SELECT sm.id, sm.event_type, sm.allocated_bed, sm.response_status, sm.sent_at, pp.patient_name FROM sent_messages sm LEFT JOIN pending_patients pp ON sm.pending_patient_id = pp.id ORDER BY sm.sent_at DESC LIMIT 10')->fetchAll();

    // Last 10 errors
    $stats['last_errors'] = $db->query('SELECT id, error_message, error_context, created_at FROM error_log ORDER BY created_at DESC LIMIT 10')->fetchAll();

    return $stats;
}
