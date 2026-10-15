<?php
    // Load the configuration
    $config = require_once __DIR__ . '/config.php';
    $db_conf = $config['db'];

    try {
        $pdo = new PDO(
            "mysql:host={$db_conf['host']};dbname={$db_conf['name']};charset={$db_conf['charset']}",
            $db_conf['user'],
            $db_conf['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());

        http_response_code(500);
        header('Content-Type: application/json');

        // ENHANCEMENT: If in dev_mode, show the real error message
        $message = $config['dev_mode']
            ? 'Database connection failed: ' . $e->getMessage()
            : 'Database connection failed.';

        echo json_encode([
            'success' => false,
            'message' => $message,
            'debug'    => $config['dev_mode'] ? $e->getTraceAsString() : null // Add stack trace for devs
        ]);
        exit;
    }
?>