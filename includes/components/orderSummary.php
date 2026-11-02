<?php
    // orderSummary.php shows an order's items, totals and where it is going
    // used on the confirmation page and the customer's order page, expects $order and $items
?>
<div class="order-summary">
    <div class="order-summary-grid">
        <div>
            <h3 class="h6 text-muted text-uppercase small">Order</h3>
            <p class="mb-1"><strong><?= e($order['orderNumber']) ?></strong></p>
            <p class="mb-1"><?= date('j F Y, H:i', strtotime($order['orderDate'])) ?></p>
            <span class="<?= statusBadgeClass($order['status']) ?>"><?= e(ORDER_STATUSES[$order['status']] ?? $order['status']) ?></span>
        </div>
        <div>
            <?php if ($order['fulfilmentType'] === 'collection'): ?>
                <h3 class="h6 text-muted text-uppercase small">Click &amp; collect from</h3>
                <p class="mb-1"><strong><?= e($order['collectionStoreName']) ?></strong></p>
                <p class="mb-0"><?= e($order['collectionStoreAddress']) ?>, <?= e($order['collectionStoreCity']) ?></p>
            <?php else: ?>
                <h3 class="h6 text-muted text-uppercase small">Delivering to</h3>
                <p class="mb-1"><strong><?= e($order['recipientName']) ?></strong></p>
                <p class="mb-0"><?= e($order['deliveryAddress']) ?><?= $order['deliverySuburb'] ? ', ' . e($order['deliverySuburb']) : '' ?><br>
                    <?= e($order['deliveryCity']) ?>, <?= e($order['deliveryProvince']) ?>, <?= e($order['deliveryPostalCode']) ?></p>
            <?php endif; ?>
        </div>
        <div>
            <h3 class="h6 text-muted text-uppercase small">Payment</h3>
            <p class="mb-1"><?= e(PAYMENT_METHODS[$order['paymentMethod']] ?? $order['paymentMethod']) ?></p>
            <span class="status-badge status-pay-<?= e($order['paymentStatus']) ?>"><?= ucfirst(e($order['paymentStatus'])) ?></span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table order-items-table align-middle">
            <thead>
                <tr><th scope="col">Book</th><th scope="col" class="text-center">Qty</th><th scope="col" class="text-end">Price</th><th scope="col" class="text-end">Total</th></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <a href="book.php?id=<?= (int) $item['bookID'] ?>"><?= e($item['title']) ?></a>
                            <div class="small text-muted"><?= e($item['author']) ?> · from <?= e($item['storeName']) ?></div>
                        </td>
                        <td class="text-center"><?= (int) $item['quantity'] ?></td>
                        <td class="text-end"><?= money($item['unitPrice']) ?></td>
                        <td class="text-end"><?= money($item['unitPrice'] * $item['quantity']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><th colspan="3" class="text-end fw-normal">Subtotal</th><td class="text-end"><?= money($order['subtotal']) ?></td></tr>
                <tr><th colspan="3" class="text-end fw-normal">Delivery</th><td class="text-end"><?= (float) $order['deliveryFee'] > 0 ? money($order['deliveryFee']) : 'Free' ?></td></tr>
                <tr><th colspan="3" class="text-end">Total</th><td class="text-end fw-bold"><?= money($order['total']) ?></td></tr>
            </tfoot>
        </table>
    </div>
</div>
