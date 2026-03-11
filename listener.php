#!/usr/bin/env php
<?php
/**
 * TCP Socket Listener for incoming HL7/XML messages.
 *
 * Run as a background service:
 *   php listener.php &
 *
 * Or via systemd (see patient-hub-listener.service).
 *
 * Listens on port 5500 (configurable in config.php).
 * Accepts HL7 (MLLP-framed) or XML data, processes it,
 * and sends back an ACK.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$port = LISTEN_PORT;

echo "Patient Hub Listener starting on port $port...\n";

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    die("Could not create socket: " . socket_strerror(socket_last_error()) . "\n");
}

socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

if (socket_bind($socket, '0.0.0.0', $port) === false) {
    die("Could not bind to port $port: " . socket_strerror(socket_last_error($socket)) . "\n");
}

if (socket_listen($socket, 5) === false) {
    die("Could not listen: " . socket_strerror(socket_last_error($socket)) . "\n");
}

echo "Listening on 0.0.0.0:$port\n";

// Handle graceful shutdown
$running = true;
pcntl_signal(SIGTERM, function() use (&$running) { $running = false; });
pcntl_signal(SIGINT, function() use (&$running) { $running = false; });

while ($running) {
    pcntl_signal_dispatch();

    // Non-blocking accept with select
    $read = [$socket];
    $write = null;
    $except = null;
    $changed = socket_select($read, $write, $except, 1);

    if ($changed === false || $changed === 0) {
        continue;
    }

    $client = socket_accept($socket);
    if ($client === false) {
        continue;
    }

    // Get sender IP
    socket_getpeername($client, $senderIp);

    // Read data
    $data = '';
    socket_set_option($client, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 30, 'usec' => 0]);

    while ($buf = @socket_read($client, 8192)) {
        $data .= $buf;
        // Check for MLLP end frame or complete XML
        if (strpos($data, "\x1C\x0D") !== false) break;
        if (preg_match('/<\/[^>]+>\s*$/', $data)) break;
        if (strlen($data) > 1048576) break; // 1MB limit
    }

    if (empty(trim($data))) {
        socket_close($client);
        continue;
    }

    echo date('Y-m-d H:i:s') . " - Received " . strlen($data) . " bytes from $senderIp\n";

    // Process the data
    $result = processIncomingData($data, $senderIp);

    // Build ACK response
    if ($result['success']) {
        $ack = buildACK($data, 'AA'); // Application Accept
        echo "  -> Processed successfully (Request ID: {$result['request_id']}, Patient: {$result['patient_name']})\n";
    } else {
        $ack = buildACK($data, 'AE'); // Application Error
        echo "  -> Processing failed: {$result['error']}\n";
    }

    // Send ACK (MLLP framed)
    $mllpAck = "\x0B" . $ack . "\x1C\x0D";
    @socket_write($client, $mllpAck, strlen($mllpAck));

    socket_close($client);
}

socket_close($socket);
echo "Listener stopped.\n";

/**
 * Build a simple HL7 ACK message.
 */
function buildACK($originalMessage, $ackCode = 'AA') {
    $timestamp = date('YmdHis');
    $controlId = 'ACK' . time();

    // Try to extract original message control ID
    $origControlId = '';
    if (preg_match('/MSH\|[^|]*\|[^|]*\|[^|]*\|[^|]*\|[^|]*\|[^|]*\|[^|]*\|[^|]*\|([^|]*)/', $originalMessage, $matches)) {
        $origControlId = $matches[1];
    }

    $msh = "MSH|^~\\&|PatientHub|PatientHub|Sender|SenderFac|$timestamp||ACK|$controlId|P|2.3";
    $msa = "MSA|$ackCode|$origControlId";

    return $msh . "\r" . $msa . "\r";
}
