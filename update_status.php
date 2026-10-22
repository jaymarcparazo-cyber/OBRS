<?php
require __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/queue_helpers.php';
header('Content-Type: application/json');
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$requestId = (int)($_POST['request_id'] ?? 0);
$status    = trim($_POST['status'] ?? '');

$validStatuses = ['pending', 'processing', 'approved', 'rejected', 'ready_for_pickup', 'released'];

if (!$requestId || !in_array($status, $validStatuses, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $releasedAt = $status === 'released' ? ', released_at = NOW()' : '';
    $stmt = $pdo->prepare("UPDATE requests SET status = ?, handled_by = ? $releasedAt WHERE request_id = ?");
    $stmt->execute([$status, $_SESSION['staff_id'], $requestId]);
    if (in_array($status, ['rejected', 'released'], true)) {
        skip_request_queue_entry($pdo, $requestId);
    }
    // the trg_requests_history_update trigger logs this change automatically

    // notify the resident in-app
    $info = $pdo->prepare('
        SELECT r.resident_id, r.reference_number, dt.name AS document_name
        FROM requests r
        JOIN document_types dt ON dt.document_type_id = r.document_type_id
        WHERE r.request_id = ?
    ');
    $info->execute([$requestId]);
    $reqInfo = $info->fetch();

    if ($reqInfo) {
        $statusLabels = [
            'pending' => 'Pending', 'processing' => 'Processing', 'approved' => 'Approved',
            'rejected' => 'Rejected', 'ready_for_pickup' => 'Ready for pickup', 'released' => 'Released'
        ];
        $message = "Your {$reqInfo['document_name']} request ({$reqInfo['reference_number']}) is now {$statusLabels[$status]}.";
        $notif = $pdo->prepare('
            INSERT INTO notifications (resident_id, request_id, channel, message)
            VALUES (?, ?, "system", ?)
        ');
        $notif->execute([$reqInfo['resident_id'], $requestId, $message]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not update status.']);
}