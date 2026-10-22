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

$residentId = filter_var($_POST['resident_id'] ?? null, FILTER_VALIDATE_INT);
$documentTypeId = filter_var($_POST['document_type_id'] ?? null, FILTER_VALIDATE_INT);
$purpose = trim($_POST['purpose'] ?? '');
$attachment = $_FILES['attachment'] ?? null;

if (!$residentId || !$documentTypeId || $purpose === '' || mb_strlen($purpose) > 255) {
    respond(422, ['success' => false, 'error' => 'Select a resident and document type, and enter a purpose of 255 characters or fewer.']);
}

if (!$attachment || $attachment['error'] !== UPLOAD_ERR_OK || $attachment['size'] > 5 * 1024 * 1024) {
    respond(422, ['success' => false, 'error' => 'Attach a JPG or PNG ID image no larger than 5MB.']);
}

$fileInfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $fileInfo->file($attachment['tmp_name']);
if (!in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
    respond(422, ['success' => false, 'error' => 'The attached ID must be a JPG or PNG image.']);
}
$imageInfo = getimagesize($attachment['tmp_name']);
if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== $mimeType) {
    respond(422, ['success' => false, 'error' => 'The attached ID image could not be read.']);
}

$storedName = bin2hex(random_bytes(16)) . ($mimeType === 'image/png' ? '.png' : '.jpg');
$uploadDir = __DIR__ . '/uploads/';
$destination = $uploadDir . $storedName;
$publicPath = 'uploads/' . $storedName;

try {
    $residentStmt = $pdo->prepare('SELECT resident_id FROM residents WHERE resident_id = ?');
    $residentStmt->execute([$residentId]);
    if (!$residentStmt->fetch()) {
        respond(422, ['success' => false, 'error' => 'The selected resident does not exist.']);
    }

    $documentStmt = $pdo->prepare('SELECT code, name FROM document_types WHERE document_type_id = ? AND is_active = 1');
    $documentStmt->execute([$documentTypeId]);
    $document = $documentStmt->fetch();
    if (!$document) {
        respond(422, ['success' => false, 'error' => 'The selected document type is unavailable.']);
    }

    $pdo->beginTransaction();
    $insert = $pdo->prepare('
        INSERT INTO requests
            (reference_number, resident_id, document_type_id, purpose, status, handled_by)
        VALUES (?, ?, ?, ?, "pending", ?)
    ');
    $insert->execute([
        'TMP-' . bin2hex(random_bytes(8)),
        $residentId,
        $documentTypeId,
        $purpose,
        $_SESSION['staff_id'],
    ]);
    $requestId = (int)$pdo->lastInsertId();
    $queueTicket = assign_request_queue_number($pdo, $requestId);
    $reference = sprintf('%s-%s-%04d', $document['code'], date('Y'), $requestId);
    $updateReference = $pdo->prepare('UPDATE requests SET reference_number = ? WHERE request_id = ?');
    $updateReference->execute([$reference, $requestId]);

    if (!move_uploaded_file($attachment['tmp_name'], $destination)) {
        throw new RuntimeException('Could not save the attached ID image.');
    }

    $attachmentStmt = $pdo->prepare('
        INSERT INTO request_attachments (request_id, file_path, file_label)
        VALUES (?, ?, ?)
    ');
    $attachmentStmt->execute([
        $requestId,
        $publicPath,
        'Staff-created request (ID authenticity review required)',
    ]);

    $notification = $pdo->prepare('
        INSERT INTO notifications (resident_id, request_id, channel, message)
        VALUES (?, ?, "system", ?)
    ');
    $formattedQueueNumber = str_pad((string)$queueTicket['queue_number'], 4, '0', STR_PAD_LEFT);
    $notification->execute([
        $residentId,
        $requestId,
        "Your {$document['name']} request ({$reference}) was entered by barangay staff. Your queue number is {$formattedQueueNumber}.",
    ]);

    $pdo->commit();
    respond(201, [
        'success' => true,
        'reference_number' => $reference,
        'queue_number' => $queueTicket['queue_number'],
    ]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (is_file($destination)) {
        unlink($destination);
    }
    error_log('Staff request creation failed: ' . $error->getMessage());
    respond(500, ['success' => false, 'error' => 'Could not create the request. Please try again.']);
}
