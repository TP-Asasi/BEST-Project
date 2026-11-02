<?php
    // orders.php lists customer orders with filters, newest first
    // staff open an order on orderView.php to move it along or cancel it

    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/../includes/helpers/orders.php';
    require_once __DIR__ . '/helpers/auth.php';

    $conn = db();
    $account = requireStaff($conn);
    requireRole($account, ['Admin', 'Staff']);

    $filters = [
        'q'      => is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '',
        'status' => array_key_exists($_GET['status'] ?? '', ORDER_STATUSES) ? $_GET['status'] : '',
        'type'   => in_array($_GET['type'] ?? '', ['delivery', 'collection'], true) ? $_GET['type'] : '',
        'store'  => intParam($_GET, 'store'),
        'from'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '',
        'to'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '',
    ];

    $where = ['1 = 1'];
    $params = [];
    if ($filters['q'] !== '') {
        $where[] = "(o.orderNumber LIKE ? OR u.email LIKE ? OR CONCAT(u.firstName, ' ', u.lastName) LIKE ? OR o.recipientName LIKE ?)";
        $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($filters['status']) {
        $where[] = 'o.status = ?';
        $params[] = $filters['status'];
    }
    if ($filters['type']) {
        $where[] = 'o.fulfilmentType = ?';
        $params[] = $filters['type'];
    }
    // an order belongs to a store if it is collected there or any copy was taken from there
    if ($filters['store']) {
        $where[] = '(o.collectionStoreID = ? OR EXISTS (SELECT 1 FROM OrderItem si WHERE si.orderID = o.orderID AND si.storeID = ?))';
        array_push($params, $filters['store'], $filters['store']);
    }
    if ($filters['from']) {
        $where[] = 'o.orderDate >= ?';
        $params[] = $filters['from'] . ' 00:00:00';
    }
    if ($filters['to']) {
        $where[] = 'o.orderDate <= ?';
        $params[] = $filters['to'] . ' 23:59:59';
    }

    $orders = fetchAll($conn,
        "SELECT o.orderID, o.orderNumber, o.orderDate, o.status, o.paymentStatus, o.total, o.fulfilmentType,
                u.firstName, u.lastName, u.email, s.name AS collectionStore,
                (SELECT SUM(quantity) FROM OrderItem oi WHERE oi.orderID = o.orderID) AS itemCount,
                (SELECT GROUP_CONCAT(DISTINCT st.name ORDER BY st.name SEPARATOR ', ') FROM OrderItem oi JOIN Store st ON st.storeID = oi.storeID WHERE oi.orderID = o.orderID) AS fromStores
         FROM CustomerOrder o
         JOIN UserAccount u ON u.accountID = o.customerID
         LEFT JOIN Store s ON s.storeID = o.collectionStoreID
         WHERE " . implode(' AND ', $where) . "
         ORDER BY o.orderDate DESC, o.orderID DESC
         LIMIT 200",
        $params);

    $statusCounts = array_column(fetchAll($conn, "SELECT status, COUNT(*) AS n FROM CustomerOrder GROUP BY status"), 'n', 'status');
    $stores = fetchAll($conn, "SELECT storeID, name FROM Store ORDER BY name");

    $pageTitle = 'Orders';
    $activePage = 'orders';
    include __DIR__ . '/components/layout.php';
?>

<!-- quick status tabs -->
<nav class="status-tabs" aria-label="Filter by status">
    <a href="orders.php" class="<?= $filters['status'] === '' ? 'active' : '' ?>">All <span><?= array_sum($statusCounts) ?></span></a>
    <?php foreach (ORDER_STATUSES as $key => $label): ?>
        <a href="orders.php?status=<?= $key ?>" class="<?= $filters['status'] === $key ? 'active' : '' ?>"><?= $label ?> <span><?= (int) ($statusCounts[$key] ?? 0) ?></span></a>
    <?php endforeach; ?>
</nav>

<form class="filter-bar" method="get">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <div class="filter-field grow">
        <label for="q" class="form-label">Search</label>
        <input type="search" id="q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Order number, customer name or email">
    </div>
    <div class="filter-field">
        <label for="type" class="form-label">Type</label>
        <select id="type" name="type" class="form-select">
            <option value="">All</option>
            <option value="delivery" <?= $filters['type'] === 'delivery' ? 'selected' : '' ?>>Delivery</option>
            <option value="collection" <?= $filters['type'] === 'collection' ? 'selected' : '' ?>>Click &amp; collect</option>
        </select>
    </div>
    <div class="filter-field">
        <label for="store" class="form-label">Store</label>
        <select id="store" name="store" class="form-select">
            <option value="">All</option>
            <?php foreach ($stores as $store): ?>
                <option value="<?= (int) $store['storeID'] ?>" <?= $filters['store'] === (int) $store['storeID'] ? 'selected' : '' ?>><?= e($store['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="from" class="form-label">From</label>
        <input type="date" id="from" name="from" class="form-control" value="<?= e($filters['from']) ?>">
    </div>
    <div class="filter-field">
        <label for="to" class="form-label">To</label>
        <input type="date" id="to" name="to" class="form-control" value="<?= e($filters['to']) ?>">
    </div>
    <button class="btn btn-outline-brand">Filter</button>
</form>

<section class="panel">
    <p class="panel-count"><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?><?= count($orders) === 200 ? ' (showing the latest 200)' : '' ?></p>
    <div class="table-wrap">
        <table class="portal-table responsive-table">
            <thead><tr><th>Order</th><th>Customer</th><th>Fulfilment</th><th>Items</th><th>Status</th><th>Payment</th><th class="text-end">Total</th></tr></thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td data-label="Order"><a href="orderView.php?id=<?= (int) $order['orderID'] ?>" class="fw-semibold"><?= e($order['orderNumber']) ?></a><small class="d-block text-muted"><?= date('j M Y, H:i', strtotime($order['orderDate'])) ?></small></td>
                        <td data-label="Customer"><?= e($order['firstName'] . ' ' . $order['lastName']) ?><small class="d-block text-muted"><?= e($order['email']) ?></small></td>
                        <td data-label="Fulfilment"><?= $order['fulfilmentType'] === 'collection' ? '<i class="bi bi-shop" aria-hidden="true"></i> ' . e($order['collectionStore']) : '<i class="bi bi-truck" aria-hidden="true"></i> Delivery' ?><small class="d-block text-muted">from <?= e($order['fromStores']) ?></small></td>
                        <td data-label="Items"><?= (int) $order['itemCount'] ?></td>
                        <td data-label="Status"><span class="<?= statusBadgeClass($order['status']) ?>"><?= e(ORDER_STATUSES[$order['status']]) ?></span></td>
                        <td data-label="Payment"><span class="status-badge status-pay-<?= e($order['paymentStatus']) ?>"><?= ucfirst(e($order['paymentStatus'])) ?></span></td>
                        <td data-label="Total" class="text-end"><?= money($order['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$orders): ?><p class="empty-note">No orders match those filters.</p><?php endif; ?>
</section>

<?php include __DIR__ . '/components/layoutEnd.php'; ?>
