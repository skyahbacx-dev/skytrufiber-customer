<?php
ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/php_errors.log");

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once "../../db_connect.php";

header("Content-Type: application/json; charset=utf-8");

$msgID  = (int)($_POST["id"] ?? 0);
$ticket = (int)($_POST["ticket"] ?? 0);

// ----------------------------------------------------------
// VALIDATE INPUT
// ----------------------------------------------------------
if ($msgID <= 0 || $ticket <= 0) {
    echo json_encode(["status" => "error", "msg" => "Invalid input"]);
    exit;
}

// ----------------------------------------------------------
// VALIDATE TICKET & CHECK STATUS (prevent deleting on resolved)
// ----------------------------------------------------------
$ticketCheck = $conn->prepare("
    SELECT status, client_id
    FROM tickets
    WHERE id = ?
    LIMIT 1
");
$ticketCheck->execute([$ticket]);
$ticketRow = $ticketCheck->fetch(PDO::FETCH_ASSOC);

if (!$ticketRow || empty($_SESSION["client_id"]) || (int)$ticketRow["client_id"] !== (int)$_SESSION["client_id"]) {
    echo json_encode(["status" => "error", "msg" => "Ticket not found"]);
    exit;
}

if (strtolower((string)$ticketRow["status"]) === "resolved") {
    echo json_encode(["status" => "blocked", "msg" => "Cannot delete messages from a resolved ticket"]);
    exit;
}

// ----------------------------------------------------------
// VALIDATE MESSAGE BELONGS TO THIS TICKET AND IS CLIENT-SENT
// ----------------------------------------------------------
$stmt = $conn->prepare("
    SELECT id, sender_type, deleted, message
    FROM chat
    WHERE id = ? AND ticket_id = ?
    LIMIT 1
");
$stmt->execute([$msgID, $ticket]);
$msg = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$msg) {
    echo json_encode(["status" => "error", "msg" => "Message not found"]);
    exit;
}

if ($msg["sender_type"] !== "client") {
    echo json_encode(["status" => "error", "msg" => "Not your message"]);
    exit;
}

// ----------------------------------------------------------
// Already deleted?
// ----------------------------------------------------------
if (!empty($msg["deleted"])) {
    echo json_encode(["status" => "ok", "type" => "already-deleted"]);
    exit;
}

// Extra safety: avoid re-clearing already empty text
if (trim($msg["message"]) === "") {
    echo json_encode(["status" => "ok", "type" => "already-empty"]);
    exit;
}

// ----------------------------------------------------------
// SOFT DELETE MESSAGE
// ----------------------------------------------------------
$upd = $conn->prepare("
    UPDATE chat
    SET message = '', deleted = TRUE, deleted_at = NOW(), edited = FALSE, updated_at = NOW()
    WHERE id = ? AND ticket_id = ? AND sender_type = 'client'
");
$upd->execute([$msgID, $ticket]);

echo json_encode(["status" => "ok", "type" => "deleted"]);
exit;
?>
