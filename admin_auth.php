<?php
// Include this at the top of any staff-only page.
// Starts the session and redirects to login.php if not authenticated.
require_once __DIR__ . '/session_init.php';

if (empty($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

if (basename(parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: '') === 'admin_auth.php') {
    header('Location: dashboard.php');
    exit;
}
