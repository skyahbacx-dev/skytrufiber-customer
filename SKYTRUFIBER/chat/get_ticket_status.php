<?php
if (!isset($_SESSION)) session_start();
require_once __DIR__ . "/../../db_connect.php";

// Disable caching so client ALWAYS gets fresh status
header("Content-Type: text/plain");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$ticketId = intval($_POST["ticket"] ?? 0);

if ($ticketId <= 0) {
    echo "unresolved";
    exit;
}

// Always fetch latest ticket status
$stmt = $conn->prepare("
    SELECT status 
    FROM tickets 
    WHERE id = ? AND client_id = ?
    LIMIT 1
");
$stmt->execute([$ticketId, (int)($_SESSION['client_id'] ?? 0)]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

// Normalize + return latest status
$status = strtolower($ticket["status"] ?? "unresolved");

// Client UI expects uppercase
echo strtoupper($status);
exit;
