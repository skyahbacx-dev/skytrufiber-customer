<?php
ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/php_errors.log");

if (!isset($_SESSION)) session_start();

// IMPORTANT: Only send JSON *when expected*
header("Content-Type: application/json");

require_once __DIR__ . "/../../db_connect.php";

$ticketId = intval($_POST["ticket"] ?? 0);
$message  = trim($_POST["message"] ?? "");

if ($ticketId <= 0) {
    echo json_encode(["status" => "error", "msg" => "invalid_ticket"]);
    exit;
}

/* ============================================================
   FETCH TICKET
============================================================ */
$stmt = $conn->prepare("
    SELECT client_id, status
    FROM tickets
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    echo json_encode(["status" => "error", "msg" => "ticket_not_found"]);
    exit;
}

/* ============================================================
   SECURITY: ENSURE CORRECT CLIENT SESSION
============================================================ */
if (!isset($_SESSION["client_id"]) || $_SESSION["client_id"] != $ticket["client_id"]) {

    // JS expects RAW string, not JSON here!
    echo "FORCE_LOGOUT";
    exit;
}

/* ============================================================
   TICKET RESOLVED → LOCK CHAT + FORCE LOGOUT
============================================================ */
if (strtolower($ticket["status"]) === "resolved") {

    echo json_encode([
        "status" => "blocked",
        "msg"    => "ticket_resolved",
        "force_logout" => true
    ]);

    exit;
}

/* ============================================================
   EMPTY MESSAGE CHECK
============================================================ */
if ($message === "") {
    echo json_encode(["status" => "empty"]);
    exit;
}

/* ============================================================
   CHECK IF THIS IS THE FIRST CLIENT MESSAGE
============================================================ */
$check = $conn->prepare("
    SELECT COUNT(*) FROM chat
    WHERE ticket_id = ? AND sender_type = 'client'
");
$check->execute([$ticketId]);
$isFirst = ($check->fetchColumn() == 0);

/* ============================================================
   INSERT MESSAGE
============================================================ */
$insert = $conn->prepare("
    INSERT INTO chat (ticket_id, client_id, sender_type, message, delivered, seen, created_at)
    VALUES (?, ?, 'client', ?, TRUE, FALSE, NOW())
");
$insert->execute([$ticketId, $ticket["client_id"], $message]);

/* ============================================================
   RETURN RESPONSE
============================================================ */
if ($isFirst) {
    echo json_encode([
        "status" => "ok",
        "first_message" => true
    ]);
    exit;
}

echo json_encode(["status" => "ok"]);
exit;
?>
