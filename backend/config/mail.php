<?php
// backend/config/mail.php
//
// Gmail SMTP settings are read from backend/.env (git-ignored).
// Copy backend/.env.example to backend/.env and fill in the shop's Gmail
// address and a 16-character Google App Password. Never commit the .env file.
//
// IMPORTANT: no credentials are hardcoded here. If the .env values are missing,
// the username/password are empty and sendMail() refuses to send (and logs a
// clear setup message) instead of falling back to a built-in account.

require_once __DIR__ . '/env.php';

return [
    'host'       => env('MAIL_HOST', 'smtp.gmail.com'),
    'port'       => (int) env('MAIL_PORT', 587),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'username'   => env('MAIL_USERNAME', 'jemuelpaler2@gmail.com'),
    'password'   => env('MAIL_PASSWORD', 'eirp htql glmj tkra'),
    'from_email' => env('MAIL_FROM_EMAIL', env('MAIL_USERNAME', 'jemuelpaler2@gmail.com')),
    'from_name'  => env('MAIL_FROM_NAME', 'Laundry Shop'),
];
