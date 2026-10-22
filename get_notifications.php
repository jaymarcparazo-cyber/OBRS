<?php
require_once __DIR__ . '/session_init.php';
header('Content-Type: application/json');
require __DIR__ . '/config.php';

if (empty($_SESSION['resident_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}

$residentId = $_SESSION['resident_id'];

$stmt = $pdo->prepare('
    SELECT n.notification_id, n.request_id, n.message, n.is_read, n.created_at,
           r.reference_number, r.status
    FROM notifications n
    LEFT JOIN requests r ON r.request_id = n.request_id
    WHERE n.resident_id = ?
    ORDER BY n.created_at DESC
    LIMIT 15
');
$stmt->execute([$residentId]);
$notifications = $stmt->fetchAll();

$countStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM notifications WHERE resident_id = ? AND is_read = 0');
$countStmt->execute([$residentId]);
$unread = (int)$countStmt->fetch()['c'];

echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => $unread]);
