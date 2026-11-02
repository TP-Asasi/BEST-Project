<?php
    // orderView.php shows one order and lets staff move it to the next status, record payment or cancel it
    // the allowed next statuses come from ORDER_NEXT_STATUSES so an order cannot jump backwards
    // cancelling puts every copy back at the store it came from

    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/../includes/helpers/orders.php';
    require_once __DIR__ . '/helpers/auth.php';

    $conn = db();
    $account = requireStaff($conn);
    requireRole($account, ['Admin', 'Staff']);

    $orderID = intParam($_GET, 'id');
    $order = $orderID ? getOrder($conn, $orderID) : null;
    if (!$order) {
        setFlash('error', 'That order could not be found.');
        header('Location: orders.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $note = postText('note', 255);
        $me = (int) $account['accountID'];

        if ($action === 'status') {
            $next = $_POST['status'] ?? '';
            // ready is for collection orders and shipped for deliveries
            $fits = !($next === 'ready' && $order['fulfilmentType'] !== 'collection') && !($next === 'shipped' && $order['fulfilmentType'] !== 'delivery');

            if ($next === 'cancelled') {
                if (cancelOrder($conn, $orderID, $me, $note ?: 'Cancelled by staff')) {
                    logActivity($conn, 'Order', 'cancelled', "Cancelled order {$order['orderNumber']} and restocked its items", $orderID);
                    setFlash('success', 'Order cancelled and the books were put back in stock.');
                } else {
                    setFlash('error', 'This order can no longer be cancelled.');
                }
            } elseif (in_array($next, ORDER_NEXT_STATUSES[$order['status']], true) && $fits) {
                execute($conn, "UPDATE CustomerOrder SET status = ?, handledBy = ? WHERE orderID = ? AND status = ?", [$next, $me, $orderID, $order['status']]);
                // completing a pay at the store order means the money was taken
                if ($next === 'completed' && $order['paymentMethod'] !== 'eft') {
                    execute($conn, "UPDATE CustomerOrder SET paymentStatus = 'paid' WHERE orderID = ? AND paymentStatus = 'unpaid'", [$orderID]);
                }
                execute($conn, "INSERT INTO OrderStatusHistory (orderID, status, note, changedBy) VALUES (?, ?, ?, ?)", [$orderID, $next, $note ?: null, $me]);
                logActivity($conn, 'Order', 'status', "Order {$order['orderNumber']} moved to " . ORDER_STATUSES[$next], $orderID);
                setFlash('success', 'Order moved to ' . ORDER_STATUSES[$next] . '.');
            } else {
                setFlash('error', 'That status change is not allowed for this order.');
            }
        } elseif ($action === 'payment' && in_array($_POST['paymentStatus'] ?? '', ['unpaid', 'paid', 'refunded'], true)) {
            execute($conn, "UPDATE CustomerOrder SET paymentStatus = ?, handledBy = ? WHERE orderID = ?", [$_POST['paymentStatus'], $me, $orderID]);
            execute($conn, "INSERT INTO OrderStatusHistory (orderID, status, note, changedBy) VALUES (?, ?, ?, ?)",
                [$orderID, $order['status'], 'Payment marked ' . $_POST['paymentStatus'] . ($note ? ": $note" : ''), $me]);
            logActivity($conn, 'Order', 'payment', "Order {$order['orderNumber']} payment marked {$_POST['paymentStatus']}", $orderID);
            setFlash('success', 'Payment status updated.');
        } elseif ($action === 'note' && $note !== '') {
            execute($conn, "INSERT INTO OrderStatusHistory (orderID, status, note, changedBy) VALUES (?, ?, ?, ?)", [$orderID, $order['status'], $note, $me]);
            setFlash('success', 'Note added.');
        }

        header('Location: orderView.php?id=' . $orderID);
        exit;
    }

    $items = getOrderItems($conn, $orderID);
    $history = getOrderHistory($conn, $orderID);
    $nextStatuses = array_filter(ORDER_NEXT_STATUSES[$order['status']], function ($status) use ($order) {
        return !($status === 'ready' && $order['fulfilmentType'] !== 'collection') && !($status === 'shipped' && $order['fulfilmentType'] !== 'delivery');
    });

    // copies to pick at each store
    $pickList = [];
    foreach ($items as $item) {
        $pickList[$item['storeName']][] = $item;
    }

    $pageTitle = 'Order ' . $order['orderNumber'];
    $activePage = 'orders';
    $pageActions = '<a href="orders.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> All orders</a>'
        . ' <button type="button" class="btn btn-outline-secondary" data-print><i class="bi bi-printer" aria-hidden="true"></i> Print</button>';
    include __DIR__ . '/components/layout.php';
?>

<div class="order-head">
    <span class="<?= statusBadgeClass($order['status']) ?> badge-lg"><?= e(ORDER_STATUSES[$order['status']]) ?></span>
    <span class="status-badge status-pay-<?= e($order['paymentStatus']) ?> badge-lg"><?= ucfirst(e($order['paymentStatus'])) ?></span>
    <span class="text-muted">Placed <?= date('l j F Y, H:i', strtotime($order['orderDate'])) ?></span>
</div>

<div class="edit-layout">
    <div class="d-flex flex-column gap-3">
        <!-- items grouped by the store they come from -->
        <section class="panel">
            <div class="panel-head"><h2>Items to pick</h2></div>
            <?php foreach ($pickList as $storeName => $storeItems): ?>
                <h3 class="pick-store"><i class="bi bi-shop" aria-hidden="true"></i> <?= e($storeName) ?></h3>
                <div class="table-wrap">
                    <table class="portal-table">
                        <thead><tr><th>Book</th><th>ISBN</th><th class="text-center">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            <?php foreach ($storeItems as $item): ?>
                                <tr>
                                    <td><a href="bookEdit.php?id=<?= (int) $item['bookID'] ?>"><?= e($item['title']) ?></a><small class="d-block text-muted"><?= e($item['author']) ?></small></td>
                                    <td><code><?= e($item['isbn']) ?></code></td>
                                    <td class="text-center"><?= (int) $item['quantity'] ?></td>
                                    <td class="text-end"><?= money($item['unitPrice']) ?></td>
                                    <td class="text-end"><?= money($item['unitPrice'] * $item['quantity']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            <dl class="totals">
                <div><dt>Subtotal</dt><dd><?= money($order['subtotal']) ?></dd></div>
                <div><dt>Delivery</dt><dd><?= money($order['deliveryFee']) ?></dd></div>
                <div class="totals-grand"><dt>Total</dt><dd><?= money($order['total']) ?></dd></div>
            </dl>
        </section>

        <!-- history -->
        <section class="panel">
            <div class="panel-head"><h2>History</h2></div>
            <ol class="timeline">
                <?php foreach ($history as $entry): ?>
                    <li>
                        <span class="<?= statusBadgeClass($entry['status']) ?>"><?= e(ORDER_STATUSES[$entry['status']] ?? $entry['status']) ?></span>
                        <span class="timeline-meta"><?= date('j M Y, H:i', strtotime($entry['changedAt'])) ?> · <?= e(trim(($entry['firstName'] ?? '') . ' ' . ($entry['lastName'] ?? '')) ?: 'System') ?></span>
                        <?php if ($entry['note']): ?><p><?= e($entry['note']) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <form method="post" class="d-flex gap-2 mt-3 no-print">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="note">
                <label for="addNote" class="visually-hidden">Add a note</label>
                <input id="addNote" name="note" class="form-control" placeholder="Add an internal note" maxlength="255" required>
                <button class="btn btn-outline-brand">Add</button>
            </form>
        </section>
    </div>

    <aside class="edit-side">
        <!-- actions -->
        <?php if ($nextStatuses): ?>
            <section class="panel no-print">
                <h2 class="panel-title">Update status</h2>
                <form method="post" data-confirm-cancel>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="status">
                    <div class="status-choices">
                        <?php foreach ($nextStatuses as $i => $status): ?>
                            <label class="form-check">
                                <input class="form-check-input" type="radio" name="status" value="<?= $status ?>" <?= $i === array_key_first($nextStatuses) ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= e(ORDER_STATUSES[$status]) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <label for="statusNote" class="form-label mt-2 small">Note (optional, the customer can see it)</label>
                    <input id="statusNote" name="note" class="form-control form-control-sm mb-3" maxlength="255">
                    <button class="btn btn-brand w-100">Save status</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="panel no-print">
            <h2 class="panel-title">Payment</h2>
            <p class="small mb-2"><?= e(PAYMENT_METHODS[$order['paymentMethod']]) ?></p>
            <form method="post" class="d-flex gap-2">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="payment">
                <label for="paymentStatus" class="visually-hidden">Payment status</label>
                <select id="paymentStatus" name="paymentStatus" class="form-select form-select-sm">
                    <?php foreach (['unpaid', 'paid', 'refunded'] as $pay): ?>
                        <option value="<?= $pay ?>" <?= $order['paymentStatus'] === $pay ? 'selected' : '' ?>><?= ucfirst($pay) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-brand btn-sm">Save</button>
            </form>
        </section>

        <!-- customer and destination -->
        <section class="panel">
            <h2 class="panel-title">Customer</h2>
            <p class="mb-1"><strong><?= e($order['firstName'] . ' ' . $order['lastName']) ?></strong></p>
            <p class="mb-1"><a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a></p>
            <p class="mb-0"><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $order['contactPhone'])) ?>"><?= e($order['contactPhone']) ?></a></p>
        </section>

        <section class="panel">
            <?php if ($order['fulfilmentType'] === 'collection'): ?>
                <h2 class="panel-title"><i class="bi bi-shop" aria-hidden="true"></i> Click &amp; collect</h2>
                <p class="mb-0"><strong><?= e($order['collectionStoreName']) ?></strong><br><?= e($order['collectionStoreAddress']) ?>, <?= e($order['collectionStoreCity']) ?></p>
                <p class="small text-muted mt-2 mb-0">Collected by: <?= e($order['recipientName']) ?></p>
            <?php else: ?>
                <h2 class="panel-title"><i class="bi bi-truck" aria-hidden="true"></i> Deliver to</h2>
                <p class="mb-0"><strong><?= e($order['recipientName']) ?></strong><br>
                    <?= e($order['deliveryAddress']) ?><?= $order['deliverySuburb'] ? ', ' . e($order['deliverySuburb']) : '' ?><br>
                    <?= e($order['deliveryCity']) ?>, <?= e($order['deliveryProvince']) ?>, <?= e($order['deliveryPostalCode']) ?></p>
            <?php endif; ?>
            <?php if ($order['customerNote']): ?>
                <div class="customer-note"><i class="bi bi-chat-left-quote" aria-hidden="true"></i> <?= e($order['customerNote']) ?></div>
            <?php endif; ?>
            <?php if ($order['handlerFirstName']): ?>
                <p class="small text-muted mt-3 mb-0">Last handled by <?= e($order['handlerFirstName'] . ' ' . $order['handlerLastName']) ?></p>
            <?php endif; ?>
        </section>
    </aside>
</div>

<?php include __DIR__ . '/components/layoutEnd.php'; ?>
