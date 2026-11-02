<?php
// dashboardData.php gets the numbers and lists shown on the dashboard
// returns data only, the page decides how to show it

require_once __DIR__ . '/../../includes/helpers/database.php';

function getDashboardStats(mysqli $conn): array
{
    $row = fetchOne($conn,
        "SELECT
            (SELECT COUNT(*) FROM CustomerOrder WHERE DATE(orderDate) = CURDATE()) AS ordersToday,
            (SELECT COUNT(*) FROM CustomerOrder WHERE status = 'pending') AS pendingOrders,
            (SELECT COUNT(*) FROM CustomerOrder WHERE status IN ('processing', 'ready', 'shipped')) AS inProgressOrders,
            (SELECT COALESCE(SUM(total), 0) FROM CustomerOrder
               WHERE status <> 'cancelled' AND orderDate >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS revenueMonth,
            (SELECT COUNT(*) FROM Book WHERE isActive = TRUE) AS activeBooks,
            (SELECT COALESCE(SUM(quantity), 0) FROM Inventory i JOIN Store s ON s.storeID = i.storeID AND s.isActive = TRUE) AS unitsInStock,
            (SELECT COUNT(*) FROM Customer) AS customers,
            (SELECT COUNT(*) FROM ContactMessage WHERE status = 'new') AS newMessages");

    return array_map('floatval', $row ?? []);
}

// sales per store this month, from the store each copy was taken from
function getSalesByStore(mysqli $conn): array
{
    return fetchAll($conn,
        "SELECT s.name, COALESCE(SUM(CASE WHEN o.orderID IS NOT NULL THEN oi.quantity * oi.unitPrice END), 0) AS sales,
                COALESCE(SUM(CASE WHEN o.orderID IS NOT NULL THEN oi.quantity END), 0) AS units
         FROM Store s
         LEFT JOIN OrderItem oi ON oi.storeID = s.storeID
         LEFT JOIN CustomerOrder o ON o.orderID = oi.orderID AND o.status <> 'cancelled'
                                   AND o.orderDate >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
         WHERE s.isActive = TRUE
         GROUP BY s.storeID, s.name
         ORDER BY sales DESC, s.name");
}

// orders per day for the last 7 days, days with no orders are filled in with zero
function getOrdersLastWeek(mysqli $conn): array
{
    $rows = fetchAll($conn,
        "SELECT DATE(orderDate) AS day, COUNT(*) AS orders, COALESCE(SUM(total), 0) AS revenue
         FROM CustomerOrder
         WHERE orderDate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND status <> 'cancelled'
         GROUP BY DATE(orderDate)");
    $byDay = array_column($rows, null, 'day');

    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $days[] = ['day' => $day, 'orders' => (int) ($byDay[$day]['orders'] ?? 0), 'revenue' => (float) ($byDay[$day]['revenue'] ?? 0)];
    }
    return $days;
}

function getRecentOrders(mysqli $conn, int $limit = 8): array
{
    return fetchAll($conn,
        "SELECT o.orderID, o.orderNumber, o.orderDate, o.status, o.total, o.fulfilmentType, u.firstName, u.lastName
         FROM CustomerOrder o JOIN UserAccount u ON u.accountID = o.customerID
         ORDER BY o.orderDate DESC, o.orderID DESC LIMIT " . (int) $limit);
}

// stock rows at or under their reorder level, a staff member with a store sees their store only
function getLowStock(mysqli $conn, ?int $storeID = null, int $limit = 8): array
{
    return fetchAll($conn,
        "SELECT b.bookID, b.title, b.author, s.storeID, s.name AS storeName, i.quantity, i.reorderLevel
         FROM Inventory i
         JOIN Book b ON b.bookID = i.bookID AND b.isActive = TRUE
         JOIN Store s ON s.storeID = i.storeID AND s.isActive = TRUE
         WHERE i.quantity <= i.reorderLevel" . ($storeID ? " AND i.storeID = ?" : "") . "
         ORDER BY i.quantity ASC, b.title LIMIT " . (int) $limit,
        $storeID ? [$storeID] : []);
}

function getTopSellers(mysqli $conn, int $limit = 5): array
{
    return fetchAll($conn,
        "SELECT b.bookID, b.title, b.author, SUM(oi.quantity) AS units, SUM(oi.quantity * oi.unitPrice) AS sales
         FROM OrderItem oi
         JOIN CustomerOrder o ON o.orderID = oi.orderID AND o.status <> 'cancelled'
         JOIN Book b ON b.bookID = oi.bookID
         GROUP BY b.bookID, b.title, b.author
         ORDER BY units DESC, sales DESC LIMIT " . (int) $limit);
}
