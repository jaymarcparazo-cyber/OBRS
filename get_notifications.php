<?php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/config.php';

if (empty($_SESSION['resident_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}

$residentId = $_SESSION['resident_id'];

$stmt = $pdo->prepare('
    SELECT notification_id, message, is_read, created_at
    FROM notifications
    WHERE resident_id = ?
    ORDER BY created_at DESC
    LIMIT 15
');
$stmt->execute([$residentId]);
$notifications = $stmt->fetchAll();

$countStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM notifications WHERE resident_id = ? AND is_read = 0');
$countStmt->execute([$residentId]);
$unread = (int)$countStmt->fetch()['c'];

echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => $unread]);
