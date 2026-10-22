<?php
function assign_request_queue_number(PDO $pdo, int $requestId): array {
    $queueDate = $pdo->query('SELECT CURRENT_DATE')->fetchColumn();
    if (!$queueDate) {
        throw new RuntimeException('Could not determine the service queue date.');
    }

    $createDailyQueue = $pdo->prepare('
        INSERT IGNORE INTO service_queue_daily (queue_date) VALUES (?)
    ');
    $createDailyQueue->execute([$queueDate]);

    $allocateNumber = $pdo->prepare('
        UPDATE service_queue_daily
        SET last_number = LAST_INSERT_ID(last_number + 1)
        WHERE queue_date = ?
    ');
    $allocateNumber->execute([$queueDate]);
    $queueNumber = (int)$pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
    if ($queueNumber < 1) {
        throw new RuntimeException('Could not allocate a service queue number.');
    }

    $insertEntry = $pdo->prepare('
        INSERT INTO service_queue_entries (request_id, queue_date, queue_number)
        VALUES (?, ?, ?)
    ');
    $insertEntry->execute([$requestId, $queueDate, $queueNumber]);

    return ['queue_date' => $queueDate, 'queue_number' => $queueNumber];
}

function skip_request_queue_entry(PDO $pdo, int $requestId): void {
    $clearCurrent = $pdo->prepare('
        UPDATE service_queue_daily d
        JOIN service_queue_entries q ON q.queue_date = d.queue_date
        SET d.current_number = NULL
        WHERE q.request_id = ? AND q.status = "serving"
            AND d.current_number = q.queue_number
    ');
    $clearCurrent->execute([$requestId]);

    $skipEntry = $pdo->prepare('
        UPDATE service_queue_entries
        SET status = "skipped"
        WHERE request_id = ? AND status IN ("waiting", "serving")
    ');
    $skipEntry->execute([$requestId]);
}
