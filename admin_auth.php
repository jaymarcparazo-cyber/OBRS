<?php
// Include this at the top of any staff-only page.
// Starts the session and redirects to login.php if not authenticated.
session_start();

if (empty($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}
