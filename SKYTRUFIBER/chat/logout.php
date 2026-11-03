<?php
session_start();
session_unset();
session_destroy();

// Redirect to /fiber (index.php will encrypt automatically)
header("Location: /fiber");
exit;
