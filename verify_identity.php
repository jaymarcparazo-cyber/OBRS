<?php
require_once __DIR__ . '/session_init.php';
ob_start();
header('Content-Type: application/json');

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
    respond(401, ['success' => false, 'error' => 'Please log in to continue.']);
}

if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf_token'] ?? '')) {
    respond(403, ['success' => false, 'error' => 'Invalid request. Refresh the page and try again.']);
}

unset($_SESSION['identity_verification'], $_SESSION['identity_review']);

$idUpload = $_FILES['attachment'] ?? null;
if (!$idUpload || $idUpload['error'] !== UPLOAD_ERR_OK) {
    respond(422, ['success' => false, 'error' => 'Please select a JPG or PNG ID image.']);
}

$maxBytes = 5 * 1024 * 1024;
if ($idUpload['size'] > $maxBytes) {
    respond(422, ['success' => false, 'error' => 'ID image must be 5MB or smaller.']);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$idMime = $finfo->file($idUpload['tmp_name']);
if (!in_array($idMime, ['image/jpeg', 'image/png'], true)) {
    respond(422, ['success' => false, 'error' => 'ID must be a JPG or PNG image.']);
}

$idHash = hash_file('sha256', $idUpload['tmp_name']);
$authorizeStaffReview = static function () use ($idHash): void {
    $_SESSION['identity_review'] = [
        'resident_id' => (string)$_SESSION['resident_id'],
        'id_hash' => $idHash,
        'authorized_at' => time(),
    ];
};

if (($_POST['manual_review'] ?? '') === '1') {
    $authorizeStaffReview();
    respond(200, [
        'success' => true,
        'result' => 'review',
        'message' => 'The request will be submitted for staff identity review.',
    ]);
}

if (($_POST['face_consent'] ?? '') !== '1') {
    respond(403, ['success' => false, 'error' => 'Consent is required before face comparison.']);
}

$selfie = $_FILES['selfie'] ?? null;
if (!$selfie || $selfie['error'] !== UPLOAD_ERR_OK || $selfie['size'] > $maxBytes) {
    respond(422, ['success' => false, 'error' => 'Capture a selfie image before starting the face check.']);
}

$selfieMime = $finfo->file($selfie['tmp_name']);
if (!in_array($selfieMime, ['image/jpeg', 'image/png'], true)) {
    respond(422, ['success' => false, 'error' => 'The captured selfie must be a JPG or PNG image.']);
}

$serviceToken = getenv('OBRS_FACE_SERVICE_TOKEN');
if (!$serviceToken && PHP_OS_FAMILY === 'Windows') {
    $tokenConfig = 'C:\\xampp\\apache\\conf\\extra\\obrs-face-verification.conf';
    if (is_readable($tokenConfig)) {
        $configLines = file($tokenConfig, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($configLines as $configLine) {
            if (preg_match('/^\s*SetEnv\s+OBRS_FACE_SERVICE_TOKEN\s+"([a-f0-9]{64})"\s*$/', $configLine, $matches)) {
                $serviceToken = $matches[1];
                break;
            }
        }
    }
}
if (!$serviceToken || !function_exists('curl_init')) {
    error_log('OBRS face verification is not configured; request will require staff identity review.');
    $authorizeStaffReview();
    respond(200, [
        'success' => true,
        'result' => 'review',
        'message' => 'Automatic face check is unavailable. You can continue with staff identity review.',
    ]);
}

$idExtension = $idMime === 'image/png' ? 'png' : 'jpg';
$selfieExtension = $selfieMime === 'image/png' ? 'png' : 'jpg';
$curl = curl_init('http://127.0.0.1:8765/verify');
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => ['X-Verification-Token: ' . $serviceToken],
    CURLOPT_POSTFIELDS => [
        'id_image' => new CURLFile($idUpload['tmp_name'], $idMime, 'id.' . $idExtension),
        'selfie' => new CURLFile($selfie['tmp_name'], $selfieMime, 'selfie.' . $selfieExtension),
    ],
]);

$responseBody = curl_exec($curl);
$curlError = curl_error($curl);
$httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($responseBody === false || $httpCode !== 200) {
    error_log('OBRS face verification service unavailable; staff identity review is required. HTTP status: ' . $httpCode . ($curlError ? '; ' . $curlError : ''));
    $authorizeStaffReview();
    respond(200, [
        'success' => true,
        'result' => 'review',
        'message' => 'The automatic face check is unavailable. You can continue with staff identity review.',
    ]);
}

$serviceResult = json_decode($responseBody, true);
if (!is_array($serviceResult) || !in_array($serviceResult['result'] ?? '', ['match', 'mismatch', 'unavailable'], true)) {
    error_log('OBRS face verification returned an invalid response; staff identity review is required.');
    $authorizeStaffReview();
    respond(200, [
        'success' => true,
        'result' => 'review',
        'message' => 'The automatic face check is unavailable. You can continue with staff identity review.',
    ]);
}

if ($serviceResult['result'] === 'match') {
    unset($_SESSION['identity_review']);
    $_SESSION['identity_verification'] = [
        'resident_id' => (string)$_SESSION['resident_id'],
        'id_hash' => $idHash,
        'verified_at' => time(),
    ];

    respond(200, [
        'success' => true,
        'result' => 'match',
        'message' => 'Government ID text was detected and the face appears to match. Staff must still verify that the ID is genuine.',
    ]);
}

if ($serviceResult['result'] === 'mismatch') {
    unset($_SESSION['identity_verification'], $_SESSION['identity_review']);
    respond(200, [
        'success' => true,
        'result' => 'mismatch',
        'message' => 'The system did not find a match between your face and the ID portrait. Retake the selfie or use a clear image of your own ID.',
    ]);
}

$reason = $serviceResult['reason'] ?? '';
$reviewMessage = match ($reason) {
    'id_document_unrecognized' => 'The system could not reliably identify this image as a government-issued ID. You can submit it only for staff identity review.',
    'verification_unavailable' => 'Automatic ID and face checks are unavailable. You can continue with staff identity review.',
    'one_face_per_image_required' => 'The system could not reliably detect one face in each image. You can continue with staff identity review.',
    default => 'The system could not complete the automatic checks. You can continue with staff identity review.',
};
$authorizeStaffReview();
respond(200, [
    'success' => true,
    'result' => 'review',
    'message' => $reviewMessage,
]);
