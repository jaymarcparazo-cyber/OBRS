<?php
require __DIR__ . '/session_init.php';
header('Content-Type: application/json');
require __DIR__ . '/config.php';

try {
    $queueDate = $pdo->query('SELECT CURRENT_DATE')->fetchColumn();
    $dailyStmt = $pdo->prepare('
        SELECT last_number, current_number, last_served, updated_at
        FROM service_queue_daily
        WHERE queue_date = ?
    ');
    $dailyStmt->execute([$queueDate]);
    $daily = $dailyStmt->fetch() ?: [
        'last_number' => 0,
        'current_number' => null,
        'last_served' => 0,
        'updated_at' => null,
    ];

    $waitingStmt = $pdo->prepare('
        SELECT COUNT(*) AS waiting_count, MIN(q.queue_number) AS next_number
        FROM service_queue_entries q
        JOIN requests r ON r.request_id = q.request_id
        WHERE q.queue_date = ? AND q.status = "waiting" AND r.status <> "cancelled"
    ');
    $waitingStmt->execute([$queueDate]);
    $waiting = $waitingStmt->fetch();

    echo json_encode([
        'success' => true,
        'queue_date' => $queueDate,
        'current_number' => $daily['current_number'] !== null ? (int)$daily['current_number'] : null,
        'last_served' => (int)$daily['last_served'],
        'called_at' => $daily['updated_at'],
        'last_number' => (int)$daily['last_number'],
        'waiting_count' => (int)$waiting['waiting_count'],
        'next_number' => $waiting['next_number'] !== null ? (int)$waiting['next_number'] : null,
    ]);
} catch (Throwable $error) {
    error_log('Could not load service queue status: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Queue status is temporarily unavailable.']);
}
