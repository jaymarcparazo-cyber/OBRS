<?php
require_once __DIR__ . '/session_init.php';
header('Content-Type: application/json');
require __DIR__ . '/config.php';
require_once __DIR__ . '/resident_account_helpers.php';

if (empty($_SESSION['resident_id']) || !resident_account_is_active($pdo, (int)$_SESSION['resident_id'])) {
    clear_resident_session();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}

$stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE resident_id = ? AND is_read = 0');
$stmt->execute([$_SESSION['resident_id']]);

echo json_encode(['success' => true]);
