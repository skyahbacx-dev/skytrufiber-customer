<?php
include '../db_connect.php';

// ✅ Validate API token
$expectedToken = getenv('CRON_SECRET') ?: '';

if ($expectedToken === '' || !isset($_GET['token']) || !hash_equals($expectedToken, (string)$_GET['token'])) {
    http_response_code(403);
    echo "Unauthorized access";
    exit;
}

// ✅ Fetch all users from NeonDB
$stmt = $conn->query("
    SELECT id, full_name, email, date_installed
    FROM users
    WHERE date_installed IS NOT NULL
");

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ reminder schedule
$reminders = [
    5 => "Your 5-day reminder message…",
    15 => "Your 15-day reminder message…",
    30 => "Your 30-day reminder message…",
];

foreach ($users as $u) {
    $uid        = $u['id'];
    $name       = $u['full_name'];
    $email      = $u['email'];
    $installed  = $u['date_installed'];

    if (!$installed) continue;

    $days = (new DateTime())->diff(new DateTime($installed))->days;

    if (!isset($reminders[$days])) {
        continue;
    }

    // ✅ Check if already sent
    $check = $conn->prepare("
        SELECT id FROM reminders
        WHERE user_id = :uid AND day_marker = :day
    ");
    $check->execute([':uid' => $uid, ':day' => $days]);

    if ($check->fetch()) {
        continue; // already sent
    }

    // ✅ Save to reminders table
    $insert = $conn->prepare("
        INSERT INTO reminders (user_id, sent_message, day_marker, created_at)
        VALUES (:uid, :msg, :day, NOW())
    ");
    $insert->execute([
        ':uid' => $uid,
        ':msg' => $reminders[$days],
        ':day' => $days
    ]);

    // ✅ Email sending
    mail(
        $email,
        "Reminder Notification",
        $reminders[$days]
    );
}

echo "✅ Reminder cron executed successfully.";
?>
