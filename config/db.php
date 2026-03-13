<?php
// ── Database Configuration ─────────────────────────────────────────────────
// Automatically uses Railway environment variables when deployed online.
// Falls back to localhost (XAMPP) when testing locally.

$host   = getenv('MYSQLHOST')     ?: getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'smartq_db';
$user   = getenv('MYSQLUSER')     ?: getenv('DB_USER') ?: 'root';
$pass   = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '';
$port   = getenv('MYSQLPORT')     ?: '3306';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Show a clean error instead of crashing
    http_response_code(500);
    die(json_encode([
        'error'   => true,
        'message' => 'Database connection failed. Please check your configuration.'
    ]));
}