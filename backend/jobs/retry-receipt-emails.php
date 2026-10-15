<?php
// This script is meant to run from the command line (Task Scheduler), not a browser.
// It retries every automatic PayMongo receipt email that previously failed.
// Safe to run repeatedly: retries update the existing email_logs row instead
// of creating a new one, and no payment is ever touched.

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/ReceiptEmail.php';

$limit = 50;
if (isset($argv[1]) && ctype_digit((string) $argv[1])) {
    $limit = max(1, min(500, (int) $argv[1]));
}

$result = retryFailedReceiptEmails($pdo, $limit);

echo "Retried {$result['tried']} failed automatic receipt(s); {$result['sent']} sent.\n";
