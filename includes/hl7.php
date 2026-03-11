<?php
/**
 * HL7 v2.x Parser and Builder
 *
 * Handles parsing incoming HL7 messages and building outgoing ADT^A01 messages.
 * Uses standard HL7 delimiters: | ^ ~ \ &
 */

class HL7Parser {

    private $segments = [];
    private $fieldSeparator = '|';
    private $componentSeparator = '^';

    /**
     * Parse an HL7 message string into segments and fields.
     */
    public function parse($rawMessage) {
        $this->segments = [];
        // Normalize line endings
        $rawMessage = str_replace(["\r\n", "\r"], "\n", trim($rawMessage));
        // Remove MLLP framing if present (0x0B prefix, 0x1C 0x0D suffix)
        $rawMessage = trim($rawMessage, "\x0B\x1C\x0D");

        $lines = explode("\n", $rawMessage);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $fields = explode($this->fieldSeparator, $line);
            $segmentName = $fields[0];
            $this->segments[] = [
                'name' => $segmentName,
                'fields' => $fields
            ];
        }
        return $this->segments;
    }

    /**
     * Extract patient data fields from parsed HL7 message.
     * Returns an associative array of field_name => value.
     */
    public function extractPatientData() {
        $data = [];

        foreach ($this->segments as $segment) {
            $name = $segment['name'];
            $fields = $segment['fields'];

            switch ($name) {
                case 'MSH':
                    $data['message_type'] = $fields[9] ?? '';
                    $data['message_control_id'] = $fields[10] ?? '';
                    $data['sending_facility'] = $fields[4] ?? '';
                    $data['receiving_facility'] = $fields[6] ?? '';
                    $data['message_datetime'] = $fields[7] ?? '';
                    break;

                case 'PID':
                    $data['patient_id'] = $fields[3] ?? '';
                    // PID-5: Patient Name (Last^First^Middle)
                    $nameComponents = explode($this->componentSeparator, $fields[5] ?? '');
                    $data['patient_last_name'] = $nameComponents[0] ?? '';
                    $data['patient_first_name'] = $nameComponents[1] ?? '';
                    $data['patient_middle_name'] = $nameComponents[2] ?? '';
                    $data['patient_name'] = trim(($nameComponents[1] ?? '') . ' ' . ($nameComponents[0] ?? ''));
                    $data['date_of_birth'] = $fields[7] ?? '';
                    $data['sex'] = $fields[8] ?? '';
                    $data['address'] = $fields[11] ?? '';
                    $data['phone'] = $fields[13] ?? '';
                    $data['ssn'] = $fields[19] ?? '';
                    break;

                case 'PV1':
                    $data['patient_class'] = $fields[2] ?? '';
                    $data['assigned_location'] = $fields[3] ?? '';
                    $data['attending_doctor'] = $fields[7] ?? '';
                    $data['referring_doctor'] = $fields[8] ?? '';
                    $data['visit_number'] = $fields[19] ?? '';
                    $data['admit_datetime'] = $fields[44] ?? '';
                    break;

                case 'DG1':
                    $data['diagnosis_code'] = $fields[3] ?? '';
                    $data['diagnosis_description'] = $fields[4] ?? '';
                    break;

                case 'NK1':
                    $data['next_of_kin_name'] = $fields[2] ?? '';
                    $data['next_of_kin_relationship'] = $fields[3] ?? '';
                    $data['next_of_kin_phone'] = $fields[5] ?? '';
                    break;

                case 'IN1':
                    $data['insurance_plan_id'] = $fields[2] ?? '';
                    $data['insurance_company'] = $fields[4] ?? '';
                    $data['insurance_group'] = $fields[8] ?? '';
                    break;

                case 'AL1':
                    $existing = $data['allergies'] ?? '';
                    $allergy = $fields[3] ?? '';
                    $data['allergies'] = $existing ? $existing . '; ' . $allergy : $allergy;
                    break;

                case 'OBX':
                    $obsId = $fields[3] ?? 'unknown';
                    $obsValue = $fields[5] ?? '';
                    $data['observation_' . $obsId] = $obsValue;
                    break;
            }
        }

        return $data;
    }

    /**
     * Build an ADT^A01 HL7 message for bed allocation.
     */
    public static function buildADT_A01($patientData, $bedName) {
        $timestamp = date('YmdHis');
        $controlId = 'PH' . time() . rand(1000, 9999);

        $msh = implode('|', [
            'MSH', '^~\\&',
            'PatientHub', 'PatientHub',
            'DestSystem', 'DestFacility',
            $timestamp, '',
            'ADT^A01', $controlId,
            'P', '2.3'
        ]);

        $evn = implode('|', [
            'EVN', 'A01', $timestamp
        ]);

        $patientName = ($patientData['patient_last_name'] ?? 'Unknown') . '^'
                      . ($patientData['patient_first_name'] ?? '') . '^'
                      . ($patientData['patient_middle_name'] ?? '');

        $pid = implode('|', [
            'PID', '1', '',
            $patientData['patient_id'] ?? '', '',
            $patientName, '',
            $patientData['date_of_birth'] ?? '', $patientData['sex'] ?? '',
            '', '', $patientData['address'] ?? '', '',
            $patientData['phone'] ?? '', '', '', '', '', '',
            $patientData['ssn'] ?? ''
        ]);

        $pv1 = implode('|', [
            'PV1', '1',
            $patientData['patient_class'] ?? 'I',
            $bedName, '', '', '',
            $patientData['attending_doctor'] ?? '',
            $patientData['referring_doctor'] ?? '',
            '', '', '', '', '', '', '', '', '', '',
            $patientData['visit_number'] ?? '',
            '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
            $timestamp
        ]);

        $message = $msh . "\r" . $evn . "\r" . $pid . "\r" . $pv1 . "\r";

        return $message;
    }

    /**
     * Build a cancellation HL7 message (ADT^A11).
     */
    public static function buildADT_A11($patientData, $reason) {
        $timestamp = date('YmdHis');
        $controlId = 'PH' . time() . rand(1000, 9999);

        $msh = implode('|', [
            'MSH', '^~\\&',
            'PatientHub', 'PatientHub',
            'DestSystem', 'DestFacility',
            $timestamp, '',
            'ADT^A11', $controlId,
            'P', '2.3'
        ]);

        $evn = implode('|', [
            'EVN', 'A11', $timestamp
        ]);

        $patientName = ($patientData['patient_last_name'] ?? 'Unknown') . '^'
                      . ($patientData['patient_first_name'] ?? '');

        $pid = implode('|', [
            'PID', '1', '',
            $patientData['patient_id'] ?? '', '',
            $patientName, '',
            $patientData['date_of_birth'] ?? '', $patientData['sex'] ?? ''
        ]);

        $pv1 = implode('|', [
            'PV1', '1', 'I', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
            $timestamp
        ]);

        // Add ZCR (custom) segment for cancel reason
        $zcr = 'ZCR|1|' . str_replace(['|', "\r", "\n"], [' ', ' ', ' '], $reason);

        $message = $msh . "\r" . $evn . "\r" . $pid . "\r" . $pv1 . "\r" . $zcr . "\r";

        return $message;
    }

    /**
     * Send an HL7 message via MLLP to the destination.
     * Returns the response or false on failure.
     */
    public static function sendMessage($hl7Message, $ip = DEST_IP, $port = DEST_PORT) {
        // MLLP framing: 0x0B + message + 0x1C + 0x0D
        $mllpMessage = "\x0B" . $hl7Message . "\x1C\x0D";

        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            logError('Failed to create socket: ' . socket_strerror(socket_last_error()), 'hl7_send');
            return false;
        }

        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 10, 'usec' => 0]);
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 10, 'usec' => 0]);

        $result = @socket_connect($socket, $ip, $port);
        if ($result === false) {
            $err = socket_strerror(socket_last_error($socket));
            logError("Failed to connect to $ip:$port - $err", 'hl7_send');
            socket_close($socket);
            return false;
        }

        $sent = @socket_write($socket, $mllpMessage, strlen($mllpMessage));
        if ($sent === false) {
            logError('Failed to send HL7 message: ' . socket_strerror(socket_last_error($socket)), 'hl7_send');
            socket_close($socket);
            return false;
        }

        // Read response
        $response = '';
        while ($buf = @socket_read($socket, 4096)) {
            $response .= $buf;
            // Check for MLLP end
            if (strpos($response, "\x1C\x0D") !== false) break;
        }

        socket_close($socket);

        // Strip MLLP framing from response
        $response = trim($response, "\x0B\x1C\x0D");

        return $response ?: 'No response received';
    }
}
