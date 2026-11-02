<?php
// orders.php places and reads orders, shared by the shop and the staff portal
// placing an order takes the copies out of a store's Inventory in one transaction,
// so two customers can never buy the same last copy. Cancelling puts them back.

require_once __DIR__ . '/database.php';

const DELIVERY_FEE = 75.00;
const FREE_DELIVERY_FROM = 500.00;

const ORDER_STATUSES = [
    'pending'    => 'Pending',
    'processing' => 'Processing',
    'ready'      => 'Ready for collection',
    'shipped'    => 'Out for delivery',
    'completed'  => 'Completed',
    'cancelled'  => 'Cancelled',
];

const PAYMENT_METHODS = [
    'eft'                => 'EFT (bank transfer)',
    'card_on_delivery'   => 'Card on delivery',
    'card_on_collection' => 'Card at the store',
    'cash_on_collection' => 'Cash at the store',
];

// which payment methods go with which fulfilment type
const PAYMENT_METHODS_FOR = [
    'delivery'   => ['eft', 'card_on_delivery'],
    'collection' => ['eft', 'card_on_collection', 'cash_on_collection'],
];

// the order a status can move to next, staff can only pick from these
const ORDER_NEXT_STATUSES = [
    'pending'    => ['processing', 'cancelled'],
    'processing' => ['ready', 'shipped', 'cancelled'],
    'ready'      => ['completed', 'cancelled'],
    'shipped'    => ['completed'],
    'completed'  => [],
    'cancelled'  => [],
];

function deliveryFeeFor(float $subtotal, string $fulfilmentType): float
{
    return $fulfilmentType === 'delivery' && $subtotal < FREE_DELIVERY_FROM ? DELIVERY_FEE : 0.0;
}

// check the checkout form, returns a list of problems
function validateCheckout(array $input, array $stores): array
{
    $errors = [];

    if ($input['recipientName'] === '') {
        $errors[] = 'Enter the name of the person receiving the order.';
    }
    if (!isValidPhone($input['contactPhone'])) {
        $errors[] = 'Enter a valid contact phone number.';
    }
    if (!in_array($input['fulfilmentType'], ['delivery', 'collection'], true)) {
        $errors[] = 'Choose delivery or click and collect.';
        return $errors;
    }

    if ($input['fulfilmentType'] === 'collection') {
        if (!in_array($input['collectionStoreID'], array_map('intval', array_column($stores, 'storeID')), true)) {
            $errors[] = 'Choose the store you will collect from.';
        }
    } else {
        if ($input['deliveryAddress'] === '' || $input['deliveryCity'] === '') {
            $errors[] = 'Enter your street address and city for delivery.';
        }
        if (!in_array($input['deliveryProvince'], PROVINCES, true)) {
            $errors[] = 'Choose your province.';
        }
        if (!isValidPostalCode($input['deliveryPostalCode'])) {
            $errors[] = 'Enter a 4 digit postal code.';
        }
    }

    if (!in_array($input['paymentMethod'], PAYMENT_METHODS_FOR[$input['fulfilmentType']], true)) {
        $errors[] = 'Choose a payment method.';
    }

    return $errors;
}

// work out which store each copy comes from, locking the stock rows until the order is saved
// collection orders must come from the chosen store, delivery orders use whichever stores have the most copies
// returns [allocations, errors]
function allocateStock(mysqli $conn, array $lines, string $fulfilmentType, ?int $collectionStoreID): array
{
    $allocations = [];
    $errors = [];

    foreach ($lines as $line) {
        $needed = (int) $line['quantity'];

        $sql = "SELECT i.storeID, i.quantity, s.name
                FROM Inventory i JOIN Store s ON s.storeID = i.storeID AND s.isActive = TRUE
                WHERE i.bookID = ? AND i.quantity > 0" .
               ($fulfilmentType === 'collection' ? " AND i.storeID = ?" : "") .
               " ORDER BY i.quantity DESC, i.storeID
                FOR UPDATE";
        $params = $fulfilmentType === 'collection' ? [$line['bookID'], $collectionStoreID] : [$line['bookID']];
        $rows = fetchAll($conn, $sql, $params);

        foreach ($rows as $row) {
            if ($needed <= 0) {
                break;
            }
            $take = min($needed, (int) $row['quantity']);
            $allocations[] = ['bookID' => (int) $line['bookID'], 'storeID' => (int) $row['storeID'],
                              'quantity' => $take, 'unitPrice' => (float) $line['price']];
            $needed -= $take;
        }

        if ($needed > 0) {
            $have = (int) $line['quantity'] - $needed;
            if ($fulfilmentType === 'collection') {
                $errors[] = "\"{$line['title']}\": only $have available at the store you chose. Try another store or choose delivery.";
            } else {
                $errors[] = "\"{$line['title']}\": only $have copies are available across all our stores.";
            }
        }
    }

    return [$allocations, $errors];
}

// save the order, its items and the stock changes in one transaction
// returns ['orderNumber' => ...] or ['errors' => [...]]
function placeOrder(mysqli $conn, int $customerID, array $cart, array $input): array
{
    if (!$cart['lines']) {
        return ['errors' => ['Your cart is empty.']];
    }

    $subtotal = round($cart['subtotal'], 2);
    $deliveryFee = deliveryFeeFor($subtotal, $input['fulfilmentType']);
    $total = $subtotal + $deliveryFee;
    $isCollection = $input['fulfilmentType'] === 'collection';

    $conn->begin_transaction();
    try {
        [$allocations, $errors] = allocateStock($conn, $cart['lines'], $input['fulfilmentType'],
            $isCollection ? $input['collectionStoreID'] : null);

        if ($errors) {
            $conn->rollback();
            return ['errors' => $errors];
        }

        // a temporary unique number, replaced with the readable one once the id is known
        $tempNumber = 'TMP' . bin2hex(random_bytes(8));
        $saved = execute($conn,
            "INSERT INTO CustomerOrder (orderNumber, customerID, fulfilmentType, collectionStoreID, recipientName, contactPhone,
                                        deliveryAddress, deliverySuburb, deliveryCity, deliveryProvince, deliveryPostalCode,
                                        subtotal, deliveryFee, total, paymentMethod, customerNote)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$tempNumber, $customerID, $input['fulfilmentType'], $isCollection ? $input['collectionStoreID'] : null,
             $input['recipientName'], $input['contactPhone'],
             $isCollection ? null : $input['deliveryAddress'], $isCollection ? null : ($input['deliverySuburb'] ?: null),
             $isCollection ? null : $input['deliveryCity'], $isCollection ? null : $input['deliveryProvince'],
             $isCollection ? null : $input['deliveryPostalCode'],
             $subtotal, $deliveryFee, $total, $input['paymentMethod'], $input['customerNote'] ?: null]);
        if (!$saved) {
            throw new RuntimeException('order insert failed');
        }

        $orderID = (int) $conn->insert_id;
        $orderNumber = 'BEST-' . date('ymd') . '-' . str_pad((string) $orderID, 5, '0', STR_PAD_LEFT);
        execute($conn, "UPDATE CustomerOrder SET orderNumber = ? WHERE orderID = ?", [$orderNumber, $orderID]);

        foreach ($allocations as $item) {
            $ok = execute($conn,
                "INSERT INTO OrderItem (orderID, bookID, storeID, quantity, unitPrice) VALUES (?, ?, ?, ?, ?)",
                [$orderID, $item['bookID'], $item['storeID'], $item['quantity'], $item['unitPrice']]);

            // the quantity >= ? check stops stock going below zero even if something slipped past the lock
            $taken = execute($conn,
                "UPDATE Inventory SET quantity = quantity - ? WHERE bookID = ? AND storeID = ? AND quantity >= ?",
                [$item['quantity'], $item['bookID'], $item['storeID'], $item['quantity']]);

            if (!$ok || $taken !== 1) {
                throw new RuntimeException('stock update failed');
            }

            execute($conn,
                "INSERT INTO StockMovement (bookID, storeID, quantityChange, reason, orderID, accountID, note)
                 VALUES (?, ?, ?, 'sale', ?, ?, ?)",
                [$item['bookID'], $item['storeID'], -$item['quantity'], $orderID, $customerID, "Order $orderNumber"]);
        }

        execute($conn,
            "INSERT INTO OrderStatusHistory (orderID, status, note, changedBy) VALUES (?, 'pending', 'Order placed online', ?)",
            [$orderID, $customerID]);

        // keep the delivery address on the customer's profile for next time
        if (!$isCollection) {
            execute($conn,
                "UPDATE Customer SET addressLine = ?, suburb = ?, city = ?, province = ?, postalCode = ? WHERE accountID = ?",
                [$input['deliveryAddress'], $input['deliverySuburb'] ?: null, $input['deliveryCity'],
                 $input['deliveryProvince'], $input['deliveryPostalCode'], $customerID]);
        }

        $conn->commit();
        return ['orderNumber' => $orderNumber, 'orderID' => $orderID];
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('placeOrder failed: ' . $error->getMessage());
        return ['errors' => ['We could not place your order right now. Nothing was charged. Please try again.']];
    }
}

// cancel an order and return every copy to the store it came from
// $accountID is the customer or staff member doing it
function cancelOrder(mysqli $conn, int $orderID, ?int $accountID, string $note): bool
{
    $conn->begin_transaction();
    try {
        $order = fetchOne($conn, "SELECT orderID, orderNumber, status FROM CustomerOrder WHERE orderID = ? FOR UPDATE", [$orderID]);
        if (!$order || in_array($order['status'], ['cancelled', 'completed', 'shipped'], true)) {
            $conn->rollback();
            return false;
        }

        foreach (fetchAll($conn, "SELECT bookID, storeID, quantity FROM OrderItem WHERE orderID = ?", [$orderID]) as $item) {
            // the stock row may have been removed if a store was closed, so put it back if needed
            execute($conn,
                "INSERT INTO Inventory (bookID, storeID, quantity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)",
                [$item['bookID'], $item['storeID'], $item['quantity']]);
            execute($conn,
                "INSERT INTO StockMovement (bookID, storeID, quantityChange, reason, orderID, accountID, note)
                 VALUES (?, ?, ?, 'cancellation', ?, ?, ?)",
                [$item['bookID'], $item['storeID'], $item['quantity'], $orderID, $accountID, "Order {$order['orderNumber']} cancelled"]);
        }

        execute($conn, "UPDATE CustomerOrder SET status = 'cancelled',
                        paymentStatus = IF(paymentStatus = 'paid', 'refunded', paymentStatus) WHERE orderID = ?", [$orderID]);
        execute($conn, "INSERT INTO OrderStatusHistory (orderID, status, note, changedBy) VALUES (?, 'cancelled', ?, ?)",
            [$orderID, $note, $accountID]);

        $conn->commit();
        return true;
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('cancelOrder failed: ' . $error->getMessage());
        return false;
    }
}

// the order with its collection store name
function getOrder(mysqli $conn, int $orderID): ?array
{
    return fetchOne($conn,
        "SELECT o.*, s.name AS collectionStoreName, s.addressLine AS collectionStoreAddress, s.city AS collectionStoreCity,
                u.firstName, u.lastName, u.email,
                h.firstName AS handlerFirstName, h.lastName AS handlerLastName
         FROM CustomerOrder o
         JOIN UserAccount u ON u.accountID = o.customerID
         LEFT JOIN Store s ON s.storeID = o.collectionStoreID
         LEFT JOIN UserAccount h ON h.accountID = o.handledBy
         WHERE o.orderID = ?",
        [$orderID]);
}

// a customer's own order by its number, null if it belongs to someone else
function getCustomerOrderByNumber(mysqli $conn, int $customerID, string $orderNumber): ?array
{
    $orderID = fetchValue($conn, "SELECT orderID FROM CustomerOrder WHERE orderNumber = ? AND customerID = ?", [$orderNumber, $customerID]);
    return $orderID ? getOrder($conn, (int) $orderID) : null;
}

function getOrderItems(mysqli $conn, int $orderID): array
{
    return fetchAll($conn,
        "SELECT oi.*, b.title, b.author, b.isbn, b.coverImageURL, s.name AS storeName
         FROM OrderItem oi
         JOIN Book b ON b.bookID = oi.bookID
         JOIN Store s ON s.storeID = oi.storeID
         WHERE oi.orderID = ?
         ORDER BY b.title, s.name",
        [$orderID]);
}

function getOrderHistory(mysqli $conn, int $orderID): array
{
    return fetchAll($conn,
        "SELECT h.*, u.firstName, u.lastName
         FROM OrderStatusHistory h LEFT JOIN UserAccount u ON u.accountID = h.changedBy
         WHERE h.orderID = ? ORDER BY h.changedAt, h.historyID",
        [$orderID]);
}

function getCustomerOrders(mysqli $conn, int $customerID): array
{
    return fetchAll($conn,
        "SELECT o.orderID, o.orderNumber, o.orderDate, o.status, o.total, o.fulfilmentType,
                (SELECT SUM(quantity) FROM OrderItem oi WHERE oi.orderID = o.orderID) AS itemCount
         FROM CustomerOrder o WHERE o.customerID = ? ORDER BY o.orderDate DESC, o.orderID DESC",
        [$customerID]);
}

// css class for a status badge
function statusBadgeClass(string $status): string
{
    return 'status-badge status-' . preg_replace('/[^a-z]/', '', $status);
}
