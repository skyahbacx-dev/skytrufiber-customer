<?php
// Shared PDO connection. All credentials are supplied through Render environment variables.
try {
    $host = getenv('PGHOST') ?: '';
    $database = getenv('PGDATABASE') ?: '';
    $username = getenv('PGUSER') ?: '';
    $password = getenv('PGPASSWORD') ?: '';
    $port = getenv('PGPORT') ?: '5432';

    // Also accept a standard PostgreSQL connection URL if configured.
    $url = getenv('DATABASE_URL') ?: '';
    if ($url !== '') {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host']) || empty($parts['path'])) {
            throw new RuntimeException('DATABASE_URL is invalid.');
        }
        $host = $parts['host'];
        $port = (string)($parts['port'] ?? 5432);
        $database = ltrim($parts['path'], '/');
        $username = rawurldecode($parts['user'] ?? '');
        $password = rawurldecode($parts['pass'] ?? '');
    }

    if (!$host || !$database || !$username || !$password) {
        throw new RuntimeException('Database environment variables are incomplete.');
    }

    $conn = new PDO(
        "pgsql:host={$host};port={$port};dbname={$database};sslmode=require",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    $pdo = $conn; // compatibility for legacy portal endpoints
} catch (Throwable $e) {
    error_log('SkyTruFiber database connection failed: ' . $e->getMessage());
    http_response_code(503);
    exit('Customer portal temporarily unavailable. Please try again later.');
}
