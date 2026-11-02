<?php
// inventoryData.php reads and changes stock levels per book per store
// every change is written to StockMovement so staff can see why a number moved

require_once __DIR__ . '/../../includes/helpers/database.php';

const INVENTORY_PER_PAGE = 25;

function inventoryStores(mysqli $conn): array
{
    return fetchAll($conn, "SELECT storeID, name, isActive FROM Store ORDER BY isActive DESC, name");
}

// one page of books with their stock at every store, returns [rows, total]
function inventoryMatrix(mysqli $conn, array $filters, array $stores): array
{
    $where = ['b.isActive = TRUE'];
    $params = [];

    if ($filters['q'] !== '') {
        $where[] = '(b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)';
        $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
        array_push($params, $like, $like, $like);
    }
    if ($filters['category']) {
        $where[] = 'b.categoryID = ?';
        $params[] = $filters['category'];
    }
    if ($filters['low']) {
        $where[] = 'EXISTS (SELECT 1 FROM Inventory li JOIN Store ls ON ls.storeID = li.storeID AND ls.isActive = TRUE
                            WHERE li.bookID = b.bookID AND li.quantity <= li.reorderLevel' . ($filters['store'] ? ' AND li.storeID = ?' : '') . ')';
        if ($filters['store']) {
            $params[] = $filters['store'];
        }
    }

    $whereSql = implode(' AND ', $where);
    $total = (int) fetchValue($conn, "SELECT COUNT(*) FROM Book b WHERE $whereSql", $params);
    $offset = ($filters['page'] - 1) * INVENTORY_PER_PAGE;

    $books = fetchAll($conn,
        "SELECT b.bookID, b.isbn, b.title, b.author, b.coverImageURL FROM Book b WHERE $whereSql
         ORDER BY b.title LIMIT " . INVENTORY_PER_PAGE . " OFFSET " . (int) $offset, $params);

    if (!$books) {
        return [[], $total];
    }

    $ids = array_map('intval', array_column($books, 'bookID'));
    $stock = [];
    foreach (fetchAll($conn, "SELECT bookID, storeID, quantity, reorderLevel FROM Inventory WHERE bookID IN (" . placeholders($ids) . ")", $ids) as $row) {
        $stock[$row['bookID']][$row['storeID']] = $row;
    }

    foreach ($books as &$book) {
        $book['stock'] = [];
        $book['total'] = 0;
        foreach ($stores as $store) {
            $cell = $stock[$book['bookID']][$store['storeID']] ?? ['quantity' => 0, 'reorderLevel' => 3];
            $book['stock'][$store['storeID']] = $cell;
            if ($store['isActive']) {
                $book['total'] += (int) $cell['quantity'];
            }
        }
    }

    return [$books, $total];
}

// set a stock level to an exact number and record the difference
// returns the change made, or null when nothing changed or it failed
function setStockLevel(mysqli $conn, int $bookID, int $storeID, int $newQuantity, string $reason, ?string $note, int $accountID): ?int
{
    $conn->begin_transaction();
    try {
        $current = fetchValue($conn, "SELECT quantity FROM Inventory WHERE bookID = ? AND storeID = ? FOR UPDATE", [$bookID, $storeID]);
        $change = $newQuantity - (int) ($current ?? 0);

        if ($change === 0) {
            $conn->rollback();
            return null;
        }

        execute($conn,
            "INSERT INTO Inventory (bookID, storeID, quantity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)",
            [$bookID, $storeID, $newQuantity]);

        // a positive restock is recorded as a restock, anything else is an adjustment (count correction, damage, loss)
        $reason = $reason === 'restock' && $change > 0 ? 'restock' : 'adjustment';
        execute($conn,
            "INSERT INTO StockMovement (bookID, storeID, quantityChange, reason, accountID, note) VALUES (?, ?, ?, ?, ?, ?)",
            [$bookID, $storeID, $change, $reason, $accountID, $note ?: null]);

        $conn->commit();
        return $change;
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('setStockLevel failed: ' . $error->getMessage());
        return null;
    }
}

function recentMovements(mysqli $conn, ?int $storeID, int $limit = 15): array
{
    return fetchAll($conn,
        "SELECT m.*, b.title, s.name AS storeName, u.firstName, u.lastName, o.orderNumber
         FROM StockMovement m
         JOIN Book b ON b.bookID = m.bookID
         JOIN Store s ON s.storeID = m.storeID
         LEFT JOIN UserAccount u ON u.accountID = m.accountID
         LEFT JOIN CustomerOrder o ON o.orderID = m.orderID
         " . ($storeID ? "WHERE m.storeID = ?" : "") . "
         ORDER BY m.createdAt DESC, m.movementID DESC LIMIT " . (int) $limit,
        $storeID ? [$storeID] : []);
}
