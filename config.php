<?php
/**
 * Patient Hub Configuration
 */

// Database settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'patienthub');
define('DB_USER', 'patientaccess');
define('DB_PASS', 'XJ7j6sl2bkLNIUbfvF33');

// Destination HL7 system
define('DEST_IP', '192.168.20.80');
define('DEST_PORT', 6600);

// Listener port for incoming HL7/XML
define('LISTEN_PORT', 5500);

// Application settings
define('APP_NAME', 'Patient Hub');
define('TOTAL_BEDS', 10);

// Session settings
define('SESSION_TIMEOUT', 3600); // 1 hour
