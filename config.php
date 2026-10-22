<?php
// Use environment variables on servers; retain XAMPP defaults for local development.
$configuredHost = getenv('OBRS_DB_HOST');
$configuredDatabase = getenv('OBRS_DB_NAME');
$configuredUser = getenv('OBRS_DB_USER');
$configuredPassword = getenv('OBRS_DB_PASSWORD');

$host    = $configuredHost !== false && $configuredHost !== '' ? $configuredHost : '127.0.0.1';
$db      = $configuredDatabase !== false && $configuredDatabase !== '' ? $configuredDatabase : 'barangay_docs';
$user    = $configuredUser !== false && $configuredUser !== '' ? $configuredUser : 'root';
$pass    = $configuredPassword !== false ? $configuredPassword : '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log('OBRS database connection failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'error' => 'Database connection failed.']));
}
