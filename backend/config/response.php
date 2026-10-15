<?php
// backend/config/response.php

/**
 * Sends a consistent JSON response and terminates the script.
 *
 * @param int $code The HTTP status code (e.g., 200, 400, 404, 500)
 * @param array $data The data to be encoded as JSON
 */
function sendResponse(int $code, array $data): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Shortcut for successful responses
 */
function sendSuccess(array $data, string $message = 'Success', int $code = 200): void
{
    sendResponse($code, array_merge([
        'success' => true,
        'message' => $message
    ], $data));
}

/**
 * Shortcut for error responses
 */
function sendError(string $message, int $code = 400, array $extra = []): void
{
    sendResponse($code, array_merge([
        'success' => false,
        'message' => $message
    ], $extra));
}
?>