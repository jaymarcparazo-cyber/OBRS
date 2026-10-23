<?php
// Include at the top of any resident-only page (e.g. my_requests.php)
require_once __DIR__ . '/session_init.php';
require __DIR__ . '/config.php';
require_once __DIR__ . '/resident_account_helpers.php';

if (empty($_SESSION['resident_id'])) {
    header('Location: resident_login.php');
    exit;
}

if (!resident_account_is_active($pdo, (int)$_SESSION['resident_id'])) {
    clear_resident_session();
    header('Location: resident_login.php?deleted=1');
    exit;
}
