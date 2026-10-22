<?php
// Include at the top of any resident-only page (e.g. my_requests.php)
require_once __DIR__ . '/session_init.php';

if (empty($_SESSION['resident_id'])) {
    header('Location: resident_login.php');
    exit;
}
