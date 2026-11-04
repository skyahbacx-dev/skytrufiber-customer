<?php
ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/php_errors.log");
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header("Content-Type: application/json");
require_once __DIR__ . "/../../db_connect.php";

$ticketId = intval($_POST["ticket"] ?? 0);
$message = trim($_POST["message"] ?? "");
$clientId = (int)($_SESSION["client_id"] ?? 0);

if ($ticketId <= 0) { echo json_encode(["status" => "error", "msg" => "invalid_ticket"]); exit; }
if ($message === "") { echo json_encode(["status" => "empty"]); exit; }
if (mb_strlen($message, "UTF-8") > 2000) { echo json_encode(["status" => "error", "msg" => "message_too_long"]); exit; }

/* Ticket validation, first-message check, and insert in one database statement. */
$stmt = $conn->prepare("
    WITH ticket AS MATERIALIZED (
        SELECT client_id, status FROM tickets WHERE id = ?
    ),
    first_message AS MATERIALIZED (
        SELECT NOT EXISTS (
            SELECT 1 FROM chat WHERE ticket_id = ? AND sender_type = 'client'
        ) AS is_first
    ),
    inserted AS (
        INSERT INTO chat (ticket_id, client_id, sender_type, message, delivered, seen, created_at)
        SELECT ?, t.client_id, 'client', ?, TRUE, FALSE, NOW()
        FROM ticket t
        WHERE t.client_id = ? AND LOWER(t.status) <> 'resolved'
        RETURNING id, created_at
    )
    SELECT t.client_id, t.status, CASE WHEN f.is_first THEN 1 ELSE 0 END AS is_first, i.id AS message_id, i.created_at
    FROM ticket t CROSS JOIN first_message f
    LEFT JOIN inserted i ON TRUE
");
$stmt->execute([$ticketId, $ticketId, $ticketId, $message, $clientId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) { echo json_encode(["status" => "error", "msg" => "ticket_not_found"]); exit; }
if ($clientId <= 0 || (int)$result["client_id"] !== $clientId) { echo "FORCE_LOGOUT"; exit; }
if (strtolower((string)$result["status"]) === "resolved") {
    echo json_encode(["status" => "blocked", "msg" => "ticket_resolved", "force_logout" => true]);
    exit;
}
if (empty($result["message_id"])) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "message_not_saved"]);
    exit;
}
echo json_encode([
    "status" => "ok",
    "first_message" => ((int)$result["is_first"] === 1),
    "message_id" => (int)$result["message_id"],
    "time" => date("g:i A", strtotime($result["created_at"]))
]);
exit;
?>