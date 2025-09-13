<?php
// app/csrf.php
// Requires session already started (your admin_auth.php does session_start()).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Return the admin CSRF token, generating it if missing.
 * Use this to print the hidden input in forms.
 */
function admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        // 48 hex chars (24 bytes)
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['admin_csrf'];
}

/**
 * Verify incoming token, returns bool.
 */
function verify_admin_csrf(?string $token): bool
{
    if (empty($_SESSION['admin_csrf']) || empty($token)) return false;
    // use hash_equals for timing-attack safe comparison
    return hash_equals((string)$_SESSION['admin_csrf'], (string)$token);
}
