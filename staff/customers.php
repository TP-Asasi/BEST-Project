<?php
    // customers.php lists registered customers with their order totals
    // staff can view, only admins can suspend or restore an account
    // POPIA: only the details staff need are shown, passwords are never shown or stored in plain text

    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/../includes/helpers/orders.php';
    require_once __DIR__ . '/helpers/auth.php';

    $conn = db();
    $account = requireStaff($conn);
    requireRole($account, ['Admin', 'Staff']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggleStatus') {
        requireRole($account, 'Admin');
        $customerID = intParam($_POST, 'accountID');
        $customer = $customerID ? fetchOne($conn,
            "SELECT u.accountID, u.firstName, u.lastName, u.accountStatus FROM UserAccount u JOIN Customer c ON c.accountID = u.accountID WHERE u.accountID = ?",
            [$customerID]) : null;
        if ($customer) {
            $new = $customer['accountStatus'] === 'active' ? 'suspended' : 'active';
            execute($conn, "UPDATE UserAccount SET accountStatus = ? WHERE accountID = ?", [$new, $customerID]);
            logActivity($conn, 'Customer', $new, ucfirst($new === 'active' ? 'restored' : 'suspended') . " customer {$customer['firstName']} {$customer['lastName']}", $customerID);
            setFlash('success', "{$customer['firstName']} {$customer['lastName']} is now $new.");
        }
        header('Location: customers.php');
        exit;
    }

    $q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
    $params = [];
    $where = '';
    if ($q !== '') {
        $where = "WHERE (u.email LIKE ? OR CONCAT(u.firstName, ' ', u.lastName) LIKE ? OR u.phoneNumber LIKE ?)";
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $params = [$like, $like, $like];
    }

    $customers = fetchAll($conn,
        "SELECT u.accountID, u.firstName, u.lastName, u.email, u.phoneNumber, u.accountStatus, u.dateCreated, u.lastLogin,
                c.city, c.province, c.marketingConsent,
                COUNT(o.orderID) AS orderCount,
                COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN o.total END), 0) AS totalSpent,
                MAX(o.orderDate) AS lastOrder
         FROM UserAccount u
         JOIN Customer c ON c.accountID = u.accountID
         LEFT JOIN CustomerOrder o ON o.customerID = u.accountID
         $where
         GROUP BY u.accountID, u.firstName, u.lastName, u.email, u.phoneNumber, u.accountStatus, u.dateCreated, u.lastLogin, c.city, c.province, c.marketingConsent
         ORDER BY u.dateCreated DESC
         LIMIT 300",
        $params);

    $pageTitle = 'Customers';
    $activePage = 'customers';
    include __DIR__ . '/components/layout.php';
?>

<form class="filter-bar" method="get">
    <div class="filter-field grow">
        <label for="q" class="form-label">Search</label>
        <input type="search" id="q" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Name, email or phone">
    </div>
    <button class="btn btn-outline-brand">Search</button>
</form>

<section class="panel">
    <p class="panel-count"><?= count($customers) ?> customer<?= count($customers) === 1 ? '' : 's' ?></p>
    <div class="table-wrap">
        <table class="portal-table responsive-table">
            <thead><tr><th>Customer</th><th>Phone</th><th>Area</th><th>Orders</th><th class="text-end">Spent</th><th>Joined</th><th>Marketing</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr class="<?= $customer['accountStatus'] === 'active' ? '' : 'is-muted' ?>">
                        <td data-label="Customer"><strong><?= e($customer['firstName'] . ' ' . $customer['lastName']) ?></strong><small class="d-block"><a href="mailto:<?= e($customer['email']) ?>"><?= e($customer['email']) ?></a></small></td>
                        <td data-label="Phone"><?= e($customer['phoneNumber'] ?? '—') ?></td>
                        <td data-label="Area"><?= e(trim(($customer['city'] ?? '') . ($customer['province'] ? ', ' . $customer['province'] : ''), ', ') ?: '—') ?></td>
                        <td data-label="Orders"><?php if ($customer['orderCount']): ?><a href="orders.php?q=<?= urlencode($customer['email']) ?>"><?= (int) $customer['orderCount'] ?></a><?php else: ?>0<?php endif; ?></td>
                        <td data-label="Spent" class="text-end"><?= money($customer['totalSpent']) ?></td>
                        <td data-label="Joined"><?= date('j M Y', strtotime($customer['dateCreated'])) ?></td>
                        <td data-label="Marketing"><?= $customer['marketingConsent'] ? '<span class="pill pill-success">Opted in</span>' : '<span class="pill">No</span>' ?></td>
                        <td data-label="Status">
                            <?php if (isAdmin($account)): ?>
                                <form method="post" data-confirm="<?= $customer['accountStatus'] === 'active' ? 'Suspend this customer? They will not be able to sign in.' : 'Restore this customer?' ?>">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggleStatus">
                                    <input type="hidden" name="accountID" value="<?= (int) $customer['accountID'] ?>">
                                    <button class="pill-btn <?= $customer['accountStatus'] === 'active' ? 'pill-success' : 'pill-danger' ?>" title="Click to <?= $customer['accountStatus'] === 'active' ? 'suspend' : 'restore' ?>"><?= ucfirst(e($customer['accountStatus'])) ?></button>
                                </form>
                            <?php else: ?>
                                <span class="pill <?= $customer['accountStatus'] === 'active' ? 'pill-success' : 'pill-danger' ?>"><?= ucfirst(e($customer['accountStatus'])) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$customers): ?><p class="empty-note">No customers found.</p><?php endif; ?>
</section>

<?php include __DIR__ . '/components/layoutEnd.php'; ?>
