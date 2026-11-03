<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../db_connect.php';

/* ============================================================
   SESSION VALIDATION
============================================================ */
if (
    !isset($_SESSION['client_id']) ||
    !isset($_SESSION['ticket_id']) ||
    empty($_SESSION['client_id']) ||
    empty($_SESSION['ticket_id'])
) {
    // clean route – index.php will encrypt it
    header("Location: /fiber");
    exit;
}

$clientId = $_SESSION['client_id'];
$ticketId = $_SESSION['ticket_id'];

/* ============================================================
   FETCH TICKET STATUS
============================================================ */
try {
    $stmt = $conn->prepare("
        SELECT status
        FROM tickets
        WHERE id = :tid AND client_id = :cid
        LIMIT 1
    ");
    $stmt->execute([
        ':tid' => $ticketId,
        ':cid' => $clientId
    ]);

    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        die("⚠ Ticket not found.");
    }

    $ticketStatus = strtoupper($ticket['status']);

} catch (PDOException $e) {
    die("⚠ Database error: " . htmlspecialchars($e->getMessage()));
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">

<title>SkyTruFiber Support</title>

<!-- Disable caching -->
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">

<!-- Font Awesome -->
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
      crossorigin="anonymous"
      referrerpolicy="no-referrer">

<!-- Local CSS -->
<link rel="stylesheet" href="/SKYTRUFIBER/chat/chat_support.css?v=<?php echo time(); ?>">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Local JS -->
<script src="/SKYTRUFIBER/chat/chat_support.js?v=<?php echo time(); ?>" defer></script>

</head>

<body>

<div id="chat-overlay">
    <div class="chat-modal">

        <!-- HEADER -->
        <div class="chat-header">

            <div class="chat-header-left">
                <img src="/SKYTRUFIBER.png" class="chat-header-logo" alt="SkyTruFiber logo">

                <div class="header-info">
                    <h2>SkyTruFiber Support</h2>

                    <span class="status active"><span class="status-dot"></span> Here to help</span>

                    <span id="ticket-status-label"
                          class="ticket-label"
                          style="display:block;margin-top:4px;font-size:13px;color:#fff;opacity:.9;">
                        Status: <?= htmlspecialchars($ticketStatus) ?>
                    </span>
                </div>
            </div>

            <div class="chat-header-right">

                <!-- THEME BUTTON -->
                <button id="theme-toggle" class="theme-toggle" type="button" aria-label="Toggle dark mode" title="Toggle dark mode">
                    <i class="fa-solid fa-moon theme-icon moon-icon"></i>
                    <i class="fa-solid fa-sun theme-icon sun-icon"></i>
                </button>

                <!-- LOGOUT BUTTON -->
                     <button id="logout-btn" class="logout-btn" type="button"> 
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </button>

            </div>
        </div>

        <!-- CHAT MESSAGES AREA -->
        <div id="chat-messages"></div>

        <!-- Scroll to bottom -->
        <button id="scroll-bottom-btn" class="scroll-bottom-btn">
            <i class="fa-solid fa-arrow-down"></i>
        </button>

        <!-- INPUT AREA -->
        <div class="chat-input-area">
            <div class="chat-input-box">
                <input id="message-input"
                       type="text"
                       aria-label="Write a message"
                       placeholder="Type a message..."
                       autocomplete="off">
            </div>

            <button id="send-btn" class="chat-send-btn" type="button" aria-label="Send message" title="Send message">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </div>

        <!-- Message Action Popup -->
        <div id="msg-action-popup" class="msg-action-popup">
            <button class="action-edit"><i class="fa-solid fa-pen"></i> Edit</button>
            <button class="action-unsend"><i class="fa-solid fa-ban"></i> Unsend</button>
            <button class="action-delete"><i class="fa-solid fa-trash"></i> Delete</button>
            <button class="action-cancel" onclick="this.parentElement.style.display='none'">Cancel</button>
        </div>

    </div>
</div>

</body>
</html>
