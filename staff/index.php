<?php
// staff/index.php sends logged in staff to the dashboard and everyone else to the login page
require_once __DIR__ . '/../includes/security.php';

header('Location: ' . (isset($_SESSION['accountID']) ? 'dashboard.php' : '../staffLogin.php'));
exit;
