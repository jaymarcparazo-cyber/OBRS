<?php
require __DIR__ . '/admin_auth.php';
header('Content-Type: application/json');
require __DIR__ . '/config.php';

function queue_respond(int $statusCode, array $data): void {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    queue_respond(405, ['success' => false, 'error' => 'Method not allowed.']);
}
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf_token'] ?? '')) {
    queue_respond(403, ['success' => false, 'error' => 'Invalid request. Refresh the page and try again.']);
}

$action = $_POST['action'] ?? '';
$queueNumber = filter_var($_POST['queue_number'] ?? null, FILTER_VALIDATE_INT);
$staffId = (int)$_SESSION['staff_id'];

try {
    $pdo->beginTransaction();
    $queueDate = $pdo->query('SELECT CURRENT_DATE')->fetchColumn();
    $createDailyQueue = $pdo->prepare('INSERT IGNORE INTO service_queue_daily (queue_date) VALUES (?)');
    $createDailyQueue->execute([$queueDate]);

    $dailyStmt = $pdo->prepare('SELECT last_number, current_number, last_served FROM service_queue_daily WHERE queue_date = ? FOR UPDATE');
    $dailyStmt->execute([$queueDate]);
    $daily = $dailyStmt->fetch();
    $lastNumber = (int)$daily['last_number'];
    $currentNumber = $daily['current_number'] !== null ? (int)$daily['current_number'] : null;

    if ($action === 'repeat') {
        if ($currentNumber === null) {
            $pdo->rollBack();
            queue_respond(409, ['success' => false, 'error' => 'There is no number currently being served.']);
        }
        $repeatCall = $pdo->prepare('
            UPDATE service_queue_daily SET updated_by = ?
            WHERE queue_date = ?
        ');
        $repeatCall->execute([$staffId, $queueDate]);
        $pdo->commit();
        queue_respond(200, ['success' => true, 'current_number' => $currentNumber, 'message' => 'Current number repeated.']);
    }

    if ($action === 'served') {
        if ($currentNumber === null) {
            $pdo->rollBack();
            queue_respond(409, ['success' => false, 'error' => 'There is no current number to mark as served.']);
        }
        $markServed = $pdo->prepare('
            UPDATE service_queue_entries SET status = "served"
            WHERE queue_date = ? AND queue_number = ? AND status = "serving"
        ');
        $markServed->execute([$queueDate, $currentNumber]);

        $nextStmt = $pdo->prepare('
            SELECT q.request_id, q.queue_number
            FROM service_queue_entries q
            JOIN requests r ON r.request_id = q.request_id
            WHERE q.queue_date = ? AND q.status = "waiting" AND r.status <> "cancelled"
            ORDER BY q.queue_number
            LIMIT 1
            FOR UPDATE
        ');
        $nextStmt->execute([$queueDate]);
        $next = $nextStmt->fetch();
        $nextNumber = $next ? (int)$next['queue_number'] : null;

        if ($next) {
            $setServing = $pdo->prepare('UPDATE service_queue_entries SET status = "serving" WHERE request_id = ?');
            $setServing->execute([$next['request_id']]);
        }

        $updateDaily = $pdo->prepare('
            UPDATE service_queue_daily
            SET current_number = ?, last_served = ?, updated_by = ?
            WHERE queue_date = ?
        ');
        $updateDaily->execute([$nextNumber, $currentNumber, $staffId, $queueDate]);
        $pdo->commit();
        queue_respond(200, [
            'success' => true,
            'current_number' => $nextNumber,
            'message' => $nextNumber === null
                ? 'Current number marked as served. No waiting requests remain.'
                : 'Current number marked as served. Now serving queue number '
                    . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT) . '.',
        ]);
    }

    if ($action === 'set') {
        if (!$queueNumber || $queueNumber > $lastNumber) {
            $pdo->rollBack();
            queue_respond(422, ['success' => false, 'error' => 'Enter a queue number that has been issued today.']);
        }
        $targetStmt = $pdo->prepare('
            SELECT q.request_id
            FROM service_queue_entries q
            JOIN requests r ON r.request_id = q.request_id
            WHERE q.queue_date = ? AND q.queue_number = ?
                AND q.status IN ("waiting", "serving")
                AND r.status <> "cancelled"
            FOR UPDATE
        ');
        $targetStmt->execute([$queueDate, $queueNumber]);
        $target = $targetStmt->fetch();
        if (!$target) {
            $pdo->rollBack();
            queue_respond(404, ['success' => false, 'error' => 'That queue number is not waiting or has already been served.']);
        }

        $clearServing = $pdo->prepare('
            UPDATE service_queue_entries
            SET status = "waiting"
            WHERE queue_date = ? AND status = "serving"
        ');
        $clearServing->execute([$queueDate]);
        $skipEarlier = $pdo->prepare('
            UPDATE service_queue_entries
            SET status = "skipped"
            WHERE queue_date = ? AND queue_number < ? AND status = "waiting"
        ');
        $skipEarlier->execute([$queueDate, $queueNumber]);
        $setServing = $pdo->prepare('
            UPDATE service_queue_entries SET status = "serving"
            WHERE request_id = ?
        ');
        $setServing->execute([$target['request_id']]);
        $updateDaily = $pdo->prepare('
            UPDATE service_queue_daily SET current_number = ?, updated_by = ?
            WHERE queue_date = ?
        ');
        $updateDaily->execute([$queueNumber, $staffId, $queueDate]);
        $pdo->commit();
        queue_respond(200, ['success' => true, 'current_number' => $queueNumber, 'message' => 'Serving number updated.']);
    }

    if ($action !== 'next') {
        $pdo->rollBack();
        queue_respond(422, ['success' => false, 'error' => 'Select a valid queue action.']);
    }

    if ($currentNumber !== null) {
        $markServed = $pdo->prepare('
            UPDATE service_queue_entries SET status = "served"
            WHERE queue_date = ? AND queue_number = ? AND status = "serving"
        ');
        $markServed->execute([$queueDate, $currentNumber]);
    }
    $nextStmt = $pdo->prepare('
        SELECT q.request_id, q.queue_number
        FROM service_queue_entries q
        JOIN requests r ON r.request_id = q.request_id
        WHERE q.queue_date = ? AND q.status = "waiting" AND r.status <> "cancelled"
        ORDER BY q.queue_number
        LIMIT 1
        FOR UPDATE
    ');
    $nextStmt->execute([$queueDate]);
    $next = $nextStmt->fetch();
    $nextNumber = $next ? (int)$next['queue_number'] : null;

    if ($next) {
        $setServing = $pdo->prepare('UPDATE service_queue_entries SET status = "serving" WHERE request_id = ?');
        $setServing->execute([$next['request_id']]);
    }
    $lastServed = $currentNumber ?? (int)$daily['last_served'];
    $updateDaily = $pdo->prepare('
        UPDATE service_queue_daily
        SET current_number = ?, last_served = ?, updated_by = ?
        WHERE queue_date = ?
    ');
    $updateDaily->execute([$nextNumber, $lastServed, $staffId, $queueDate]);

    $pdo->commit();
    queue_respond(200, [
        'success' => true,
        'current_number' => $nextNumber,
        'message' => $nextNumber === null ? 'No waiting requests are in today’s queue.' : 'Now serving queue number ' . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT) . '.',
    ]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Queue action failed: ' . $error->getMessage());
    queue_respond(500, ['success' => false, 'error' => 'Could not update the service queue.']);
}
