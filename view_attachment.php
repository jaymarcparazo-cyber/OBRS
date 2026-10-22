<?php
require_once __DIR__ . '/session_init.php';

$isStaff = !empty($_SESSION['staff_id']);
$residentId = $_SESSION['resident_id'] ?? null;
if (!$isStaff && !$residentId) {
    http_response_code(401);
    exit('Authentication required.');
}

$attachmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$attachmentId || $attachmentId < 1) {
    http_response_code(404);
    exit('Attachment not found.');
}

require __DIR__ . '/config.php';

if ($isStaff) {
    $stmt = $pdo->prepare('
        SELECT ra.file_path
        FROM request_attachments ra
        WHERE ra.attachment_id = ?
    ');
    $stmt->execute([$attachmentId]);
} else {
    $stmt = $pdo->prepare('
        SELECT ra.file_path
        FROM request_attachments ra
        JOIN requests r ON r.request_id = ra.request_id
        WHERE ra.attachment_id = ? AND r.resident_id = ?
    ');
    $stmt->execute([$attachmentId, $residentId]);
}

$attachment = $stmt->fetch();
if (!$attachment) {
    http_response_code(404);
    exit('Attachment not found.');
}

$relativePath = str_replace('\\', '/', $attachment['file_path']);
if (!preg_match('~^uploads/[a-f0-9]{32}\.(?:jpg|png)$~i', $relativePath)) {
    error_log('Rejected invalid request attachment path for attachment ID ' . $attachmentId);
    http_response_code(404);
    exit('Attachment not found.');
}

$uploadDirectory = realpath(__DIR__ . '/uploads');
$filePath = realpath(__DIR__ . '/' . $relativePath);
if (
    $uploadDirectory === false
    || $filePath === false
    || dirname($filePath) !== $uploadDirectory
    || !is_file($filePath)
    || !is_readable($filePath)
) {
    http_response_code(404);
    exit('Attachment not found.');
}

$mimeType = strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'png'
    ? 'image/png'
    : 'image/jpeg';
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="request-attachment.' . ($mimeType === 'image/png' ? 'png' : 'jpg') . '"');
header('Content-Length: ' . (string)filesize($filePath));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
