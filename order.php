<?php
    // order.php lets a customer follow one of their orders and cancel it while it is still pending

    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/books.php';
    require_once __DIR__ . '/includes/helpers/orders.php';
    require_once __DIR__ . '/includes/helpers/customerAuth.php';

    $conn = db();
    $orderNumber = is_string($_GET['order'] ?? null) ? $_GET['order'] : '';
    $customer = requireCustomer($conn, 'order.php?order=' . rawurlencode($orderNumber));
    $order = getCustomerOrderByNumber($conn, (int) $customer['accountID'], $orderNumber);

    if (!$order) {
        setFlash('error', 'We could not find that order on your account.');
        header('Location: account.php');
        exit;
    }

    // customers can cancel until staff start working on it
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
        if (verifyCsrf() && $order['status'] === 'pending'
            && cancelOrder($conn, (int) $order['orderID'], (int) $customer['accountID'], 'Cancelled by the customer')) {
            setFlash('success', 'Your order was cancelled.');
        } else {
            setFlash('error', 'This order can no longer be cancelled online. Please contact us.');
        }
        header('Location: order.php?order=' . urlencode($order['orderNumber']));
        exit;
    }

    $items = getOrderItems($conn, (int) $order['orderID']);
    $history = getOrderHistory($conn, (int) $order['orderID']);

    // the steps shown on the tracker, collection and delivery end differently
    $trackSteps = $order['fulfilmentType'] === 'collection'
        ? ['pending', 'processing', 'ready', 'completed']
        : ['pending', 'processing', 'shipped', 'completed'];
    $currentStep = array_search($order['status'], $trackSteps, true);

    $pageTitle = 'Order ' . $order['orderNumber'];
?>
<!DOCTYPE html>
<html lang="en">
    <?php include 'includes/components/header.php'; ?>

    <body>
        <?php include 'includes/components/navBar.php'; ?>

        <main id="main" class="container-xl page-pad narrow-page">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="account.php">My account</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= e($order['orderNumber']) ?></li>
                </ol>
            </nav>
            <h1 class="page-title mb-4">Order <?= e($order['orderNumber']) ?></h1>

            <?php if ($order['status'] === 'cancelled'): ?>
                <div class="alert alert-secondary">This order was cancelled.</div>
            <?php else: ?>
                <ol class="order-tracker" aria-label="Order progress">
                    <?php foreach ($trackSteps as $i => $step): ?>
                        <li class="<?= $currentStep !== false && $i < $currentStep ? 'done' : ($i === $currentStep ? 'current' : '') ?>">
                            <span class="tracker-dot" aria-hidden="true"></span>
                            <span><?= e(ORDER_STATUSES[$step]) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <?php include 'includes/components/orderSummary.php'; ?>

            <h2 class="h5 mt-4">History</h2>
            <ul class="history-list">
                <?php foreach ($history as $entry): ?>
                    <li>
                        <span class="<?= statusBadgeClass($entry['status']) ?>"><?= e(ORDER_STATUSES[$entry['status']] ?? $entry['status']) ?></span>
                        <span class="text-muted small"><?= date('j M Y, H:i', strtotime($entry['changedAt'])) ?></span>
                        <?php if ($entry['note']): ?><span class="small"><?= e($entry['note']) ?></span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($order['status'] === 'pending'): ?>
                <form method="post" class="mt-4" data-confirm="Cancel this order? The books will go back on the shelf.">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="cancel">
                    <button class="btn btn-outline-danger">Cancel order</button>
                </form>
            <?php endif; ?>
        </main>

        <?php include 'includes/components/footer.php'; ?>
    </body>
</html>
