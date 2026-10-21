<?php
// Handles: POST document_code, purpose
// Resident identity now comes from the logged-in session, not the form.
// Returns: { success: true, reference_number: "BC-2026-0007" } or { success: false, error: "..." }

session_start();
ob_start(); // buffer output so a stray PHP warning can't corrupt the JSON response
header('Content-Type: application/json');
require __DIR__ . '/config.php';

function respond($httpCode, $data) {
    http_response_code($httpCode);
    ob_clean(); // discard anything accidentally printed before this point
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'error' => 'Method not allowed.']);
}

if (empty($_SESSION['resident_id'])) {
    respond(401, ['success' => false, 'error' => 'Please log in to submit a request.', 'redirect' => 'resident_login.php']);
}

$residentId = $_SESSION['resident_id'];
$docCode    = trim($_POST['document_code'] ?? '');
$purpose    = trim($_POST['purpose'] ?? '');

$onBehalfOf       = !empty($_POST['on_behalf_of']);
$beneficiaryName  = trim($_POST['beneficiary_name'] ?? '');
$beneficiaryCivil = trim($_POST['beneficiary_civil_status'] ?? '');
$relationship     = trim($_POST['relationship'] ?? '');

if ($onBehalfOf && (!$beneficiaryName || !$relationship)) {
    respond(422, ['success' => false, 'error' => 'Please provide the name of the person you\'re requesting for, and your relationship to them.']);
}

if (!$docCode || !$purpose) {
    respond(422, ['success' => false, 'error' => 'Please fill in every field.']);
}

// --- File upload validation (valid ID / supporting document) ---
$allowedExt  = ['jpg', 'jpeg', 'png', 'pdf'];
$maxBytes    = 5 * 1024 * 1024; // 5 MB
$uploadedFile = $_FILES['attachment'] ?? null;

if (!$uploadedFile || $uploadedFile['error'] === UPLOAD_ERR_NO_FILE) {
    respond(422, ['success' => false, 'error' => 'Please attach a valid ID or supporting document.']);
}

if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
    respond(422, ['success' => false, 'error' => 'File upload failed. Please try again.']);
}

if ($uploadedFile['size'] > $maxBytes) {
    respond(422, ['success' => false, 'error' => 'File is too large. Maximum size is 5MB.']);
}

$ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    respond(422, ['success' => false, 'error' => 'Only JPG, PNG, or PDF files are allowed.']);
}

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// unique, unguessable filename — never trust the original filename
$storedName = bin2hex(random_bytes(16)) . '.' . $ext;
$destPath   = $uploadDir . $storedName;
$publicPath = 'uploads/' . $storedName;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT document_type_id FROM document_types WHERE code = ? AND is_active = 1');
    $stmt->execute([$docCode]);
    $docType = $stmt->fetch();

    if (!$docType) {
        throw new Exception('Unknown document type.');
    }

    $tempRef = 'TMP-' . uniqid();
    $ins = $pdo->prepare('
        INSERT INTO requests
            (reference_number, resident_id, document_type_id, purpose, status,
             on_behalf_of, beneficiary_name, beneficiary_civil_status, relationship)
        VALUES (?, ?, ?, ?, "pending", ?, ?, ?, ?)
    ');
    $ins->execute([
        $tempRef, $residentId, $docType['document_type_id'], $purpose,
        $onBehalfOf ? 1 : 0,
        $onBehalfOf ? $beneficiaryName : null,
        $onBehalfOf && $beneficiaryCivil ? $beneficiaryCivil : null,
        $onBehalfOf ? $relationship : null,
    ]);
    $requestId = $pdo->lastInsertId();

    $refNumber = sprintf('%s-%s-%04d', $docCode, date('Y'), $requestId);
    $upd = $pdo->prepare('UPDATE requests SET reference_number = ? WHERE request_id = ?');
    $upd->execute([$refNumber, $requestId]);

    // move the file only after the DB rows we depend on are ready to commit
    if (!move_uploaded_file($uploadedFile['tmp_name'], $destPath)) {
        throw new Exception('Could not save the uploaded file.');
    }

    $attIns = $pdo->prepare('
        INSERT INTO request_attachments (request_id, file_path, file_label)
        VALUES (?, ?, ?)
    ');
    $attIns->execute([$requestId, $publicPath, 'Valid ID']);

    $docNameStmt = $pdo->prepare('SELECT name FROM document_types WHERE document_type_id = ?');
    $docNameStmt->execute([$docType['document_type_id']]);
    $docName = $docNameStmt->fetch()['name'];

    $notif = $pdo->prepare('
        INSERT INTO notifications (resident_id, request_id, channel, message)
        VALUES (?, ?, "system", ?)
    ');
    $notif->execute([$residentId, $requestId, "Your {$docName} request ({$refNumber}) has been submitted and is pending review."]);

    $pdo->commit();

    respond(200, ['success' => true, 'reference_number' => $refNumber]);
} catch (Exception $e) {
    $pdo->rollBack();
    if (isset($destPath) && file_exists($destPath)) {
        unlink($destPath); // clean up the orphaned file if the DB transaction failed
    }
    respond(500, ['success' => false, 'error' => 'Could not submit your request. Please try again.', 'debug' => $e->getMessage()]);
}