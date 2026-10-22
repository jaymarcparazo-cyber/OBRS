<?php
require __DIR__ . '/admin_auth.php';
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
$documentTypeId = filter_var($_POST['document_type_id'] ?? null, FILTER_VALIDATE_INT);
$purpose = trim($_POST['purpose'] ?? '');
if (!$requestId || !$documentTypeId || $purpose === '' || mb_strlen($purpose) > 255) {
    respond(422, ['success' => false, 'error' => 'Select a document type and enter a purpose of 255 characters or fewer.']);
}

try {
    $pdo->beginTransaction();
    $currentStmt = $pdo->prepare('SELECT status FROM requests WHERE request_id = ? FOR UPDATE');
    $currentStmt->execute([$requestId]);
    $current = $currentStmt->fetch();
    if (!$current) {
        $pdo->rollBack();
        respond(404, ['success' => false, 'error' => 'Request not found.']);
    }
    if (in_array($current['status'], ['released', 'cancelled'], true)) {
        $pdo->rollBack();
        respond(409, ['success' => false, 'error' => 'Released or cancelled requests cannot be edited.']);
    }

    $documentStmt = $pdo->prepare('SELECT document_type_id FROM document_types WHERE document_type_id = ? AND is_active = 1');
    $documentStmt->execute([$documentTypeId]);
    if (!$documentStmt->fetch()) {
        $pdo->rollBack();
        respond(422, ['success' => false, 'error' => 'The selected document type is unavailable.']);
    }

    $update = $pdo->prepare('
        UPDATE requests
        SET document_type_id = ?, purpose = ?, handled_by = ?
        WHERE request_id = ?
    ');
    $update->execute([$documentTypeId, $purpose, $_SESSION['staff_id'], $requestId]);

    $audit = $pdo->prepare('
        INSERT INTO request_status_history (request_id, status, changed_by, remarks)
        VALUES (?, ?, ?, "Request details edited by staff")
    ');
    $audit->execute([$requestId, $current['status'], $_SESSION['staff_id']]);

    $pdo->commit();
    respond(200, ['success' => true]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Staff request update failed: ' . $error->getMessage());
    respond(500, ['success' => false, 'error' => 'Could not update the request. Please try again.']);
}
