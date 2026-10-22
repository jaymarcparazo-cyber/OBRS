<?php
require __DIR__ . '/admin_auth.php';
header('Content-Type: application/json');
require __DIR__ . '/config.php';

function respond($httpCode, $data) {
    http_response_code($httpCode);
    echo json_encode($data);
    exit;
}

if (($_SESSION['staff_role'] ?? '') !== 'admin') {
    respond(403, ['success' => false, 'error' => 'Only administrators can permanently delete requests.']);
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

$uploadDirectory = realpath(__DIR__ . '/uploads');
if ($uploadDirectory === false) {
    respond(500, ['success' => false, 'error' => 'The upload directory could not be found.']);
}

try {
    $pdo->beginTransaction();
    $requestStmt = $pdo->prepare('SELECT status FROM requests WHERE request_id = ? FOR UPDATE');
    $requestStmt->execute([$requestId]);
    $request = $requestStmt->fetch();
    if (!$request) {
        $pdo->rollBack();
        respond(404, ['success' => false, 'error' => 'Request not found.']);
    }
    if ($request['status'] !== 'cancelled') {
        $pdo->rollBack();
        respond(409, ['success' => false, 'error' => 'Only cancelled requests can be permanently deleted. Cancel the request first.']);
    }

    $attachmentStmt = $pdo->prepare('SELECT file_path FROM request_attachments WHERE request_id = ? FOR UPDATE');
    $attachmentStmt->execute([$requestId]);
    $attachmentPaths = $attachmentStmt->fetchAll(PDO::FETCH_COLUMN);
    $filesToDelete = [];
    foreach ($attachmentPaths as $attachmentPath) {
        $relativePath = str_replace('\\', '/', $attachmentPath);
        if (str_starts_with($relativePath, '/') || preg_match('/^[A-Za-z]:/', $relativePath)) {
            throw new RuntimeException('Attachment path is not a relative application path.');
        }
        $candidatePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if ($candidatePath === false) {
            continue;
        }
        if (!is_file($candidatePath) || dirname($candidatePath) !== $uploadDirectory) {
            throw new RuntimeException('Attachment path is outside the uploads directory.');
        }
        $filesToDelete[] = $candidatePath;
    }

    $delete = $pdo->prepare('DELETE FROM requests WHERE request_id = ?');
    $delete->execute([$requestId]);
    $pdo->commit();

    $failedFiles = [];
    foreach ($filesToDelete as $filePath) {
        $currentPath = realpath($filePath);
        if ($currentPath === false) {
            continue;
        }
        if (dirname($currentPath) !== $uploadDirectory || !is_file($currentPath) || !unlink($currentPath)) {
            $failedFiles[] = basename($filePath);
        }
    }

    if ($failedFiles) {
        error_log('Request ' . $requestId . ' was deleted, but attachment cleanup failed for: ' . implode(', ', $failedFiles));
        respond(500, [
            'success' => false,
            'error' => 'The request record was permanently deleted, but an ID attachment could not be removed. Contact the system administrator.',
        ]);
    }

    respond(200, ['success' => true]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Permanent request deletion failed: ' . $error->getMessage());
    respond(500, ['success' => false, 'error' => 'Could not permanently delete the request. Please try again.']);
}
