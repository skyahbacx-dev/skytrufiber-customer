<?php
if (!isset($_SESSION)) session_start();
require_once "../../db_connect.php";

header("Content-Type: text/plain; charset=utf-8");

$ticketId = (int)($_POST["ticket"] ?? 0);
if ($ticketId <= 0) {
    echo "error";
    exit;
}

/* --------------------------------------------------
   FETCH TICKET
-------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT client_id
    FROM tickets
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket || empty($_SESSION["client_id"]) || (int)$ticket["client_id"] !== (int)$_SESSION["client_id"]) {
    echo "no-ticket";
    exit;
}

/* --------------------------------------------------
   COUNT MESSAGES FOR THIS TICKET
-------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT id, sender_type
    FROM chat
    WHERE ticket_id = ?
    ORDER BY id ASC
");
$stmt->execute([$ticketId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($messages);

/* --------------------------------------------------
   AUTO-GREET RULE:
   Trigger only when:
   - There is EXACTLY 1 message
   - That message came from the client (login concern)
-------------------------------------------------- */
if ($total === 1 && $messages[0]["sender_type"] === "client") {
    echo "first-login-message";
} else {
    echo "no-greet";
}
?>
