<?php
if (!isset($_SESSION)) session_start();
require_once __DIR__ . "/../../db_connect.php";

if (empty($_SESSION["client_id"])) exit("NO_USER");

// Only greet the authenticated customer session.
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
$stmt->execute([(int)$_SESSION["client_id"]]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) exit("NO_CLIENT");

$clientId = $user["id"];

// Check CSR messages exist
$check = $conn->prepare("
    SELECT COUNT(*) FROM chat
    WHERE client_id = ? AND sender_type = 'csr'
");
$check->execute([$clientId]);

if ($check->fetchColumn() > 0) exit("NO_GREETING");

// Choose greeting
$hour = date("H");
$greet = ($hour < 12)
    ? "Good morning! How may we assist you today?"
    : (($hour < 18)
        ? "Good afternoon! How may we assist you today?"
        : "Good evening! How may we assist you today?");

// Insert greeting
$insert = $conn->prepare("
    INSERT INTO chat (client_id, sender_type, message, delivered, seen, created_at)
    VALUES (?, 'csr', ?, TRUE, FALSE, NOW())
");
$insert->execute([$clientId, $greet]);

echo "GREETED";
