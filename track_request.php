<?php
// Handles: GET ?ref=BC-2026-0007
// Returns: { success: true, request: {...}, history: [...] } or { success: false, error: "..." }

header('Content-Type: application/json');
require __DIR__ . '/config.php';

$ref = trim($_GET['ref'] ?? '');

if (!$ref) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a reference number.']);
    exit;
}

$stmt = $pdo->prepare('
    SELECT r.request_id, r.reference_number, r.status, r.purpose,
           r.requested_at, r.updated_at,
           dt.name AS document_name,
           res.first_name, res.last_name
    FROM requests r
    JOIN document_types dt ON dt.document_type_id = r.document_type_id
    JOIN residents res ON res.resident_id = r.resident_id
    WHERE r.reference_number = ?
');
$stmt->execute([$ref]);
$request = $stmt->fetch();

if (!$request) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'No request found with that reference number.']);
    exit;
}

$histStmt = $pdo->prepare('
    SELECT status, remarks, changed_at
    FROM request_status_history
    WHERE request_id = ?
    ORDER BY changed_at ASC
');
$histStmt->execute([$request['request_id']]);
$history = $histStmt->fetchAll();

unset($request['request_id']); // internal only

echo json_encode(['success' => true, 'request' => $request, 'history' => $history]);
