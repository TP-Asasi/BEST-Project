<?php
    // dashboard.php is the first page after login, shared by admin and staff
    // summary cards, sales charts, recent orders, low stock and best sellers

    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/../includes/helpers/orders.php';
    require_once __DIR__ . '/helpers/auth.php';
    require_once __DIR__ . '/helpers/dashboardData.php';

    $conn = db();
    $account = requireStaff($conn);

    $stats = getDashboardStats($conn);
    $salesByStore = getSalesByStore($conn);
    $week = getOrdersLastWeek($conn);
    $recentOrders = getRecentOrders($conn);
    $lowStock = getLowStock($conn, isAdmin($account) ? null : ($account['storeID'] ? (int) $account['storeID'] : null));
    $topSellers = getTopSellers($conn);

    $maxStoreSales = max(1, ...array_map(fn ($row) => (float) $row['sales'], $salesByStore ?: [['sales' => 0]]));
    $maxDayOrders = max(1, ...array_column($week, 'orders'));

    $pageTitle = 'Good ' . (date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening')) . ', ' . $account['firstName'];
    $activePage = 'dashboard';
    include __DIR__ . '/components/layout.php';
?>

<p class="portal-subtitle"><?= date('l, j F Y') ?><?= $account['storeName'] ? ' · ' . e($account['storeName']) : '' ?></p>

<!-- summary cards -->
<section class="stat-grid" aria-label="Summary">
    <?php
        $cardTitle = 'Pending orders'; $cardValue = (int) $stats['pendingOrders']; $cardIcon = 'hourglass-split';
        $cardMeta = (int) $stats['inProgressOrders'] . ' in progress'; $cardLink = 'orders.php?status=pending'; $cardClass = 'stat-warning';
        include __DIR__ . '/components/statCard.php';

        $cardTitle = 'Orders today'; $cardValue = (int) $stats['ordersToday']; $cardIcon = 'bag-check';
        $cardLink = 'orders.php';
        include __DIR__ . '/components/statCard.php';

        $cardTitle = 'Sales this month'; $cardValue = money($stats['revenueMonth']); $cardIcon = 'cash-coin';
        $cardMeta = 'Excludes cancelled orders'; $cardClass = 'stat-success';
        include __DIR__ . '/components/statCard.php';

        $cardTitle = 'Low stock lines'; $cardValue = count(getLowStock($conn, isAdmin($account) ? null : ($account['storeID'] ? (int) $account['storeID'] : null), 1000)); $cardIcon = 'exclamation-triangle';
        $cardMeta = (int) $stats['unitsInStock'] . ' units on shelves'; $cardLink = 'inventory.php?low=1'; $cardClass = 'stat-danger';
        include __DIR__ . '/components/statCard.php';

        $cardTitle = 'Books for sale'; $cardValue = (int) $stats['activeBooks']; $cardIcon = 'book';
        $cardLink = 'books.php';
        include __DIR__ . '/components/statCard.php';

        $cardTitle = 'Customers'; $cardValue = (int) $stats['customers']; $cardIcon = 'people';
        $cardMeta = (int) $stats['newMessages'] . ' new messages'; $cardLink = 'customers.php';
        include __DIR__ . '/components/statCard.php';
    ?>
</section>

<div class="panel-grid">
    <!-- orders this week -->
    <section class="panel" aria-labelledby="weekHeading">
        <div class="panel-head"><h2 id="weekHeading">Orders in the last 7 days</h2></div>
        <div class="column-chart" role="img" aria-label="Orders per day for the last seven days">
            <?php foreach ($week as $day): ?>
                <div class="column">
                    <span class="column-value"><?= $day['orders'] ?></span>
                    <span class="column-bar" style="height: <?= round($day['orders'] / $maxDayOrders * 100) ?>%"></span>
                    <span class="column-label"><?= date('D', strtotime($day['day'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- sales by store -->
    <section class="panel" aria-labelledby="storeSalesHeading">
        <div class="panel-head"><h2 id="storeSalesHeading">Sales by store this month</h2></div>
        <ul class="bar-list">
            <?php foreach ($salesByStore as $row): ?>
                <li>
                    <div class="bar-list-label"><span><?= e($row['name']) ?></span><span><?= money($row['sales']) ?> · <?= (int) $row['units'] ?> units</span></div>
                    <div class="progress-track"><span class="progress-fill" style="width: <?= round((float) $row['sales'] / $maxStoreSales * 100) ?>%"></span></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<div class="panel-grid panel-grid-wide">
    <!-- recent orders -->
    <section class="panel" aria-labelledby="recentHeading">
        <div class="panel-head">
            <h2 id="recentHeading">Recent orders</h2>
            <a href="orders.php" class="panel-link">All orders</a>
        </div>
        <?php if ($recentOrders): ?>
            <div class="table-wrap">
                <table class="portal-table">
                    <thead><tr><th>Order</th><th>Customer</th><th>Type</th><th>Status</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><a href="orderView.php?id=<?= (int) $order['orderID'] ?>"><?= e($order['orderNumber']) ?></a><br><small class="text-muted"><?= date('j M, H:i', strtotime($order['orderDate'])) ?></small></td>
                                <td><?= e($order['firstName'] . ' ' . $order['lastName']) ?></td>
                                <td><?= $order['fulfilmentType'] === 'collection' ? 'Collect' : 'Delivery' ?></td>
                                <td><span class="<?= statusBadgeClass($order['status']) ?>"><?= e(ORDER_STATUSES[$order['status']]) ?></span></td>
                                <td class="text-end"><?= money($order['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="empty-note">No orders yet.</p>
        <?php endif; ?>
    </section>

    <div class="panel-stack">
        <!-- low stock -->
        <section class="panel" aria-labelledby="lowHeading">
            <div class="panel-head">
                <h2 id="lowHeading">Low stock<?= !isAdmin($account) && $account['storeName'] ? ' at ' . e($account['storeName']) : '' ?></h2>
                <a href="inventory.php?low=1" class="panel-link">Restock</a>
            </div>
            <?php if ($lowStock): ?>
                <ul class="compact-list">
                    <?php foreach ($lowStock as $row): ?>
                        <li>
                            <span><strong><?= e($row['title']) ?></strong><small><?= e($row['storeName']) ?></small></span>
                            <span class="qty-chip<?= (int) $row['quantity'] === 0 ? ' is-zero' : '' ?>"><?= (int) $row['quantity'] ?> left</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty-note">Every line is above its reorder level.</p>
            <?php endif; ?>
        </section>

        <!-- best sellers -->
        <section class="panel" aria-labelledby="topHeading">
            <div class="panel-head"><h2 id="topHeading">Best sellers</h2></div>
            <?php if ($topSellers): ?>
                <ol class="compact-list ranked">
                    <?php foreach ($topSellers as $row): ?>
                        <li><span><strong><?= e($row['title']) ?></strong><small><?= e($row['author']) ?></small></span><span><?= (int) $row['units'] ?> sold</span></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p class="empty-note">Sales will show here once orders come in.</p>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php include __DIR__ . '/components/layoutEnd.php'; ?>
