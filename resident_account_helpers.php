<?php
function resident_account_is_active(PDO $pdo, int $residentId): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM residents WHERE resident_id = ? AND is_deleted = 0');
    $stmt->execute([$residentId]);
    return (bool)$stmt->fetchColumn();
}

function clear_resident_session(): void {
    unset(
        $_SESSION['resident_id'],
        $_SESSION['resident_name'],
        $_SESSION['identity_verification'],
        $_SESSION['identity_review']
    );
}
