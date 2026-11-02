<?php
// auth.php checks the staff member is logged in on every portal page
// two roles: Admin (full access, manages stores and staff accounts) and Staff (products, stock, orders)
// hiding a link is not enough, so every page also calls requireRole()

require_once __DIR__ . '/../../includes/helpers/database.php';
require_once __DIR__ . '/activityLog.php';

const CHANGE_PASSWORD_PAGE = 'profile.php';

// reload the account on every request so a suspended account is logged out straight away
function requireStaff(?mysqli $conn): array
{
    if (!$conn) {
        http_response_code(503);
        exit('The database is unavailable right now. Please try again in a few minutes.');
    }

    $account = isset($_SESSION['accountID']) ? fetchOne($conn,
        "SELECT u.accountID, u.firstName, u.lastName, u.email, u.phoneNumber, u.accountStatus, u.mustChangePassword, u.lastLogin,
                CASE WHEN a.accountID IS NOT NULL THEN 'Admin' WHEN s.accountID IS NOT NULL THEN 'Staff' END AS role,
                s.storeID, s.jobTitle, st.name AS storeName
         FROM UserAccount u
         LEFT JOIN Admin a ON a.accountID = u.accountID
         LEFT JOIN Staff s ON s.accountID = u.accountID
         LEFT JOIN Store st ON st.storeID = s.storeID
         WHERE u.accountID = ?",
        [(int) $_SESSION['accountID']]) : null;

    if (!$account || $account['accountStatus'] !== 'active' || $account['role'] === null) {
        unset($_SESSION['accountID'], $_SESSION['role'], $_SESSION['staffName']);
        header('Location: ../staffLogin.php');
        exit;
    }

    $_SESSION['role'] = $account['role'];
    $_SESSION['staffName'] = $account['firstName'] . ' ' . $account['lastName'];

    // block every other page until a required password change is done
    if ($account['mustChangePassword'] && basename($_SERVER['SCRIPT_NAME']) !== CHANGE_PASSWORD_PAGE) {
        setFlash('info', 'Please choose a new password before you continue.');
        header('Location: ' . CHANGE_PASSWORD_PAGE);
        exit;
    }

    // every post form in the portal must carry the csrf token
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrf()) {
        setFlash('error', 'Your session expired. Please try again.');
        header('Location: ' . basename($_SERVER['SCRIPT_NAME']));
        exit;
    }

    return $account;
}

// send staff without the right role back to the dashboard with a message
function requireRole(array $account, string|array $roles): void
{
    if (!in_array($account['role'], (array) $roles, true)) {
        setFlash('error', 'You do not have access to that page.');
        header('Location: dashboard.php');
        exit;
    }
}

function isAdmin(array $account): bool
{
    return $account['role'] === 'Admin';
}

// staff may only change stock at their own store, admins and staff without a store can change any
function canEditStore(array $account, int $storeID): bool
{
    return isAdmin($account) || $account['storeID'] === null || (int) $account['storeID'] === $storeID;
}
