<?php
// Handles: POST request_ref
// Cancels a request — only if it belongs to the logged-in resident AND is still 'pending'.
// Returns: { success: true } or { success: false, error: "..." }

session_start();
ob_start();
header('Content-Type: application/json');
require __DIR__ . '/config.php';

function respond($httpCode, $data) {
    http_response_code($httpCode);
    ob_clean();
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'error' => 'Method not allowed.']);
}

if (empty($_SESSION['resident_id'])) {
    respond(401, ['success' => false, 'error' => 'Please log in.']);
}

$ref = trim($_POST['reference_number'] ?? '');
if (!$ref) {
    respond(422, ['success' => false, 'error' => 'Missing reference number.']);
}

try {
    // Confirm this request belongs to the logged-in resident and is still pending
    $stmt = $pdo->prepare('SELECT request_id, resident_id, status FROM requests WHERE reference_number = ?');
    $stmt->execute([$ref]);
    $request = $stmt->fetch();

    if (!$request) {
        respond(404, ['success' => false, 'error' => 'Request not found.']);
    }

    if ($request['resident_id'] != $_SESSION['resident_id']) {
        respond(403, ['success' => false, 'error' => 'You are not authorized to cancel this request.']);
    }

    if ($request['status'] !== 'pending') {
        respond(409, ['success' => false, 'error' => 'This request is already being processed and can no longer be cancelled. Please contact the barangay office.']);
    }

    $upd = $pdo->prepare('UPDATE requests SET status = "cancelled" WHERE request_id = ?');
    $upd->execute([$request['request_id']]);
    // trg_requests_history_update logs this automatically

    $notif = $pdo->prepare('
        INSERT INTO notifications (resident_id, request_id, channel, message)
        VALUES (?, ?, "system", ?)
    ');
    $notif->execute([$_SESSION['resident_id'], $request['request_id'], "Your request ({$ref}) has been cancelled."]);

    respond(200, ['success' => true]);
} catch (Exception $e) {
    respond(500, ['success' => false, 'error' => 'Could not cancel the request. Please try again.']);
}
