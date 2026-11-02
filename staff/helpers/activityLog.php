<?php
// activityLog.php records what staff do in the portal for the admin activity log
// rows are never edited or deleted, and logging never breaks the action it records

require_once __DIR__ . '/../../includes/helpers/database.php';

const ACTIVITY_CATEGORIES = [
    'Login'     => 'Logins',
    'Book'      => 'Books',
    'Category'  => 'Categories',
    'Inventory' => 'Inventory',
    'Store'     => 'Stores',
    'Order'     => 'Orders',
    'Customer'  => 'Customers',
    'Message'   => 'Messages',
    'Account'   => 'Staff accounts',
    'Profile'   => 'Profile',
];

const ACTIVITY_PER_PAGE = 30;

// pass $actor for actions with no session yet, like a failed login
function logActivity(mysqli $conn, string $category, string $action, string $description, ?int $targetID = null, ?array $actor = null): void
{
    $accountID = $actor['accountID'] ?? ($_SESSION['accountID'] ?? null);
    $name = $actor['name'] ?? ($_SESSION['staffName'] ?? 'Unknown');
    $ip = mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null;

    $saved = execute($conn,
        "INSERT INTO ActivityLog (accountID, accountName, category, action, description, targetID, ipAddress) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$accountID, mb_substr($name, 0, 255), $category, $action, mb_substr($description, 0, 500), $targetID, $ip]);

    if ($saved === false) {
        error_log('logActivity failed for: ' . $description);
    }
}
