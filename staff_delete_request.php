<?php
require __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/queue_helpers.php';
header('Content-Type: application/json');
require __DIR__ . '/config.php';

function respond($httpCode, $data) {
    http_response_code($httpCode);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'error' => 'Method not allowed.']);
}

if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf_token'] ?? '')) {
    respond(403, ['success' => false, 'error' => 'Invalid request. Refresh the page and try again.']);
}

$requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
if (!$requestId) {
    respond(422, ['success' => false, 'error' => 'Invalid request.']);
}

try {
    $pdo->beginTransaction();
    $requestStmt = $pdo->prepare('
        SELECT r.status, r.resident_id, r.reference_number, dt.name AS document_name
        FROM requests r
        JOIN document_types dt ON dt.document_type_id = r.document_type_id
        WHERE r.request_id = ?
        FOR UPDATE
    ');
    $requestStmt->execute([$requestId]);
    $request = $requestStmt->fetch();
    if (!$request) {
        $pdo->rollBack();
        respond(404, ['success' => false, 'error' => 'Request not found.']);
    }
    if (in_array($request['status'], ['released', 'cancelled'], true)) {
        $pdo->rollBack();
        respond(409, ['success' => false, 'error' => 'Released or already cancelled requests cannot be cancelled again.']);
    }

    $cancel = $pdo->prepare('
        UPDATE requests
        SET status = "cancelled", handled_by = ?, remarks = "Cancelled by staff"
        WHERE request_id = ?
    ');
    $cancel->execute([$_SESSION['staff_id'], $requestId]);
    skip_request_queue_entry($pdo, $requestId);

    $notification = $pdo->prepare('
        INSERT INTO notifications (resident_id, request_id, channel, message)
        VALUES (?, ?, "system", ?)
    ');
    $notification->execute([
        $request['resident_id'],
        $requestId,
        "Your {$request['document_name']} request ({$request['reference_number']}) was cancelled by barangay staff.",
    ]);

    $pdo->commit();
    respond(200, ['success' => true]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Staff request cancellation failed: ' . $error->getMessage());
    respond(500, ['success' => false, 'error' => 'Could not cancel the request. Please try again.']);
}
