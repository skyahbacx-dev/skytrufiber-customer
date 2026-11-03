<?php
include '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }

$account_number = trim($_POST['account_number'] ?? '');
$client_name    = trim($_POST['client_name']    ?? '');
$email          = trim($_POST['email']          ?? '');
$district       = trim($_POST['district']       ?? '');
$location       = trim($_POST['location']       ?? ''); // barangay
$date_installed = trim($_POST['date_installed'] ?? '');
$feedback       = trim($_POST['feedback']       ?? '');

if (!$account_number || !$client_name || !$email || !$district || !$location || !$date_installed || !$feedback) {
  echo "<script>alert('⚠️ Please complete all fields.'); history.back();</script>"; exit;
}

try {
  $conn->beginTransaction();

  // Upsert users (account_number as key)
  $check = $conn->prepare("SELECT id FROM users WHERE account_number = :acc LIMIT 1");
  $check->execute([':acc'=>$account_number]);
  $row = $check->fetch(PDO::FETCH_ASSOC);

  if ($row) {
    $conn->prepare("
      UPDATE users
      SET full_name=:n, email=:e, district=:d, barangay=:b, date_installed=:dt, updated_at=NOW()
      WHERE account_number=:acc
    ")->execute([':n'=>$client_name,':e'=>$email,':d'=>$district,':b'=>$location,':dt'=>$date_installed,':acc'=>$account_number]);
    $user_id = (int)$row['id'];
  } else {
    $conn->prepare("
      INSERT INTO users (account_number, full_name, email, district, barangay, date_installed, created_at)
      VALUES (:acc,:n,:e,:d,:b,:dt,NOW())
    ")->execute([':acc'=>$account_number,':n'=>$client_name,':e'=>$email,':d'=>$district,':b'=>$location,':dt'=>$date_installed]);
    $user_id = (int)$conn->lastInsertId();
  }

  // Write to survey_responses
 $conn->prepare("
  INSERT INTO survey_responses (client_name, account_number, email, district, location, feedback, created_at)
  VALUES (:c, :a, :e, :d, :l, :f, NOW())
")->execute([
  ':c' => $client_name,
  ':a' => $account_number,
  ':e' => $email,          // <-- added email here
  ':d' => $district,
  ':l' => $location,
  ':f' => $feedback
]);

  $conn->commit();
  echo "<script>alert('✅ Thank you! Your registration & feedback were submitted.'); window.location.href='skytrufiber.php';</script>";
} catch(PDOException $e) {
  $conn->rollBack();
  echo "<script>alert('❌ Database error: ".addslashes($e->getMessage())."'); history.back();</script>";
}
