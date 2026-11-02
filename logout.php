<?php
    // logout.php signs the customer out but keeps their cart
    require_once __DIR__ . '/includes/security.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
        unset($_SESSION['customerID']);
        session_regenerate_id(true);
        setFlash('success', 'You have been signed out.');
    }

    header('Location: index.php');
    exit;
