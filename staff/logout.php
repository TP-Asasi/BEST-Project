<?php
// logout.php logs the staff member out and sends them back to the login page
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/helpers/database.php';
require_once __DIR__ . '/helpers/activityLog.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_SESSION['accountID'])) {
    if ($conn = db()) {
        logActivity($conn, 'Login', 'logout', 'Logged out');
    }
    // keep a customer's session and cart if they were also shopping in this browser
    unset($_SESSION['accountID'], $_SESSION['role'], $_SESSION['staffName']);
    session_regenerate_id(true);
}

header('Location: ../staffLogin.php');
exit;
