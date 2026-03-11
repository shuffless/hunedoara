<?php
require_once __DIR__ . '/../config.php';

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            logError('Database connection failed: ' . $e->getMessage(), 'db');
            die('Database connection error.');
        }
    }
    return $pdo;
}

function logError($message, $context = null) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS
        );
        $stmt = $pdo->prepare('INSERT INTO error_log (error_message, error_context) VALUES (?, ?)');
        $stmt->execute([$message, $context]);
    } catch (Exception $e) {
        error_log('Patient Hub Error: ' . $message . ' | Context: ' . $context);
    }
}
