<?php
ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/php_errors.log");

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . "/../../db_connect.php";

$ticketId = intval($_POST["ticket"] ?? 0);
if ($ticketId <= 0) exit("");

// ---------------------------------------------
// FETCH TICKET
// ---------------------------------------------
$stmt = $conn->prepare("
    SELECT status, client_id
    FROM tickets
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket || empty($_SESSION["client_id"]) || (int)$ticket["client_id"] !== (int)$_SESSION["client_id"]) exit("");

// AUTO LOGOUT WHEN RESOLVED
if ($ticket["status"] === "resolved") {
    $_SESSION["force_logout"] = true;
    echo "FORCE_LOGOUT";
    exit;
}

// ---------------------------------------------
// FETCH ALL MESSAGES
// ---------------------------------------------
$stmt = $conn->prepare("
    SELECT id, sender_type, message, created_at, deleted, edited
    FROM chat
    WHERE ticket_id = ?
    ORDER BY id ASC
");
$stmt->execute([$ticketId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine first CSR & client message
$greetingMsgId = null;

foreach ($messages as $m) {
    if ($greetingMsgId === null && $m["sender_type"] === "csr") {
        $greetingMsgId = $m["id"];
    }
}

// ---------------------------------------------
// RENDER MESSAGES
// ---------------------------------------------
foreach ($messages as $msg) {

    $id     = $msg["id"];
    $isCSR  = $msg["sender_type"] === "csr";
    $type   = $isCSR ? "received" : "sent";
    $text   = nl2br(htmlspecialchars($msg["message"]));
    $time   = date("g:i A", strtotime($msg["created_at"]));

    $extraClass = ($id == $greetingMsgId) ? " csr-greeting animate-in" : "";

    echo "<div class='message $type$extraClass' data-msg-id='$id'>";

    if ($isCSR) {
        echo "<div class='message-avatar'><img src='/SKYTRUFIBER.png'></div>";
    }

    echo "<div class='message-content'>";

    if ($msg["deleted"]) {
        echo "<div class='message-bubble removed-text'>Message removed</div>";
    } else {
        echo "<div class='message-bubble'>$text</div>";
    }

    echo "<div class='message-time'>$time";
    if ($msg["edited"]) echo " <span class='edited-label'>(edited)</span>";
    echo "</div>";

    if (!$isCSR && !$msg["deleted"]) {
        echo "
        <div class='action-toolbar'>
            <button class='more-btn' data-id='$id'>⋯</button>
        </div>";
    }

    echo "</div></div>";
}

?>
