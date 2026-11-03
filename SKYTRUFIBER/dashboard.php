<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: skytrufiber.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard - SkyTruFiber</title>
</head>
<body>
<h2>Welcome <?= htmlspecialchars($_SESSION['name']) ?> (Account #<?= htmlspecialchars($_SESSION['user']) ?>)</h2>
<p>This is your SkyTruFiber dashboard.</p>
<a href="logout.php">Logout</a>
</body>
</html>

