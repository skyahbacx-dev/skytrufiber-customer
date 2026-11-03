<?php
if (!isset($_SESSION)) session_start();
require_once __DIR__ . "/../../db_connect.php";

$username = trim($_POST["username"] ?? "");
if (!$username) exit("NO_USER");

// Find client
$stmt = $conn->prepare("
    SELECT id FROM users
    WHERE email ILIKE ? OR full_name ILIKE ?
    LIMIT 1
");
$stmt->execute([$username, $username]);
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
