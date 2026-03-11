<?php
/**
 * AJAX endpoint: Get current pending patients and available beds.
 * Used for real-time refresh of the pending list.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
header('Content-Type: application/json');

echo json_encode([
    'pending' => getPendingPatients(),
    'beds' => getAvailableBeds()
]);
