<?php
// backend/config/env.php
//
// Tiny .env loader with no external dependency. It reads backend/.env once and
// makes the values available through env() / getenv(). Real Gmail credentials
// live in backend/.env, which is git-ignored. See backend/.env.example.

/**
 * Parse a .env file and load its KEY=VALUE pairs into the environment.
 * Existing environment variables always win, so a real server env can
 * override the file without editing it.
 */
function loadEnv(string $path): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Strip matching surrounding quotes, if any
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last  = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if ($key === '') {
            continue;
        }

        if (getenv($key) === false) {
            putenv("$key=$value");
        }
        $_ENV[$key] = $value;
    }
}

/**
 * Read a configuration value. Returns $default when the key is missing/empty.
 *
 * @return mixed
 */
function env(string $key, $default = null)
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

// Load once, at include time
loadEnv(__DIR__ . '/../.env');
