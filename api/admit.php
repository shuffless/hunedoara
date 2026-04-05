<?php
/**
 * AJAX endpoint: Direct patient admission from manual form.
 *
 * POST: { first_name, last_name, cnp, diagnosis, attending_doctor, bed_id }
 *
 * Extracts sex and date of birth from CNP, builds HL7 ADT^A01,
 * sends to destination and allocates the bed directly (no pending queue).
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

$input           = json_decode(file_get_contents('php://input'), true);
$firstName       = trim($input['first_name']       ?? '');
$lastName        = trim($input['last_name']        ?? '');
$cnp             = trim($input['cnp']              ?? '');
$diagnosis       = trim($input['diagnosis']        ?? '');
$attendingDoctor = trim($input['attending_doctor'] ?? '');
$bedId           = intval($input['bed_id']         ?? 0);

// --- Validation ---
if (!$firstName || !$lastName || !$cnp || !$diagnosis || !$attendingDoctor || $bedId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Toate campurile sunt obligatorii.']);
    exit;
}

if (!preg_match('/^\d{13}$/', $cnp)) {
    echo json_encode(['success' => false, 'error' => 'CNP invalid. Trebuie sa contina exact 13 cifre.']);
    exit;
}

// --- Extract sex and date of birth from CNP ---
$s  = (int) $cnp[0];
$yy = substr($cnp, 1, 2);
$mm = substr($cnp, 3, 2);
$dd = substr($cnp, 5, 2);

if ($s === 1 || $s === 2)      $century = '19';
elseif ($s === 3 || $s === 4)  $century = '18';
elseif ($s === 5 || $s === 6)  $century = '20';
elseif ($s === 7 || $s === 8)  $century = '19';
else {
    echo json_encode(['success' => false, 'error' => 'CNP invalid: prima cifra necunoscuta.']);
    exit;
}

if ((int) $mm < 1 || (int) $mm > 12 || (int) $dd < 1 || (int) $dd > 31) {
    echo json_encode(['success' => false, 'error' => 'CNP invalid: data nasterii incorecta.']);
    exit;
}

$sex         = ($s % 2 === 1) ? 'M' : 'F';
$dateOfBirth = $century . $yy . $mm . $dd; // YYYYMMDD (HL7 format)

// --- Build patient data array ---
$timestamp   = date('YmdHis');
$controlId   = 'PH' . time() . rand(1000, 9999);
$visitNumber = 'V' . time();

$patientData = [
    'patient_id'            => $cnp,
    'patient_first_name'    => $firstName,
    'patient_last_name'     => $lastName,
    'patient_middle_name'   => '',
    'patient_name'          => $firstName . ' ' . $lastName,
    'date_of_birth'         => $dateOfBirth,
    'sex'                   => $sex,
    'ssn'                   => $cnp,
    'patient_class'         => 'I',
    'attending_doctor'      => $attendingDoctor,
    'referring_doctor'      => '',
    'visit_number'          => $visitNumber,
    'admit_datetime'        => $timestamp,
    'diagnosis_code'        => '',
    'diagnosis_description' => $diagnosis,
    'message_type'          => 'ADT^A01',
    'message_control_id'    => $controlId,
    'message_datetime'      => $timestamp,
    'sending_facility'      => 'PatientHub',
];

$result = directAdmitPatient($patientData, $bedId);
echo json_encode($result);
