<?php
    // order-confirmation.php thanks the customer and shows what they ordered
    // only the customer who placed the order can open it

    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/books.php';
    require_once __DIR__ . '/includes/helpers/orders.php';
    require_once __DIR__ . '/includes/helpers/customerAuth.php';

    $conn = db();
    $orderNumber = is_string($_GET['order'] ?? null) ? $_GET['order'] : '';
    $customer = requireCustomer($conn, 'order-confirmation.php?order=' . rawurlencode($orderNumber));
    $order = getCustomerOrderByNumber($conn, (int) $customer['accountID'], $orderNumber);

    if (!$order) {
        header('Location: account.php');
        exit;
    }
    $items = getOrderItems($conn, (int) $order['orderID']);

    $pageTitle = 'Order Confirmed';
?>
<!DOCTYPE html>
<html lang="en">
    <?php include 'includes/components/header.php'; ?>

    <body>
        <?php include 'includes/components/navBar.php'; ?>

        <main id="main" class="container-xl page-pad narrow-page">
            <ol class="checkout-steps" aria-label="Checkout progress">
                <li class="done">Cart</li>
                <li class="done">Details</li>
                <li class="current" aria-current="step">Confirmation</li>
            </ol>

            <div class="confirmation-hero">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <h1 class="page-title">Thank you, <?= e($customer['firstName']) ?>!</h1>
                <p>Your order <strong><?= e($order['orderNumber']) ?></strong> has been placed. A copy is saved in <a href="account.php">your account</a>.</p>
            </div>

            <?php if ($order['paymentMethod'] === 'eft' && $order['paymentStatus'] === 'unpaid'): ?>
                <div class="eft-box">
                    <h2 class="h6"><i class="bi bi-bank" aria-hidden="true"></i> Pay by EFT</h2>
                    <dl>
                        <div><dt>Bank</dt><dd>First National Bank</dd></div>
                        <div><dt>Account name</dt><dd>Books-Extraordinary-Supply-Town</dd></div>
                        <div><dt>Account number</dt><dd>62000000000</dd></div>
                        <div><dt>Branch code</dt><dd>250655</dd></div>
                        <div><dt>Reference</dt><dd><strong><?= e($order['orderNumber']) ?></strong></dd></div>
                        <div><dt>Amount</dt><dd><strong><?= money($order['total']) ?></strong></dd></div>
                    </dl>
                    <p class="small mb-0">Your books are set aside for you now. We start processing as soon as the payment reflects.</p>
                </div>
            <?php elseif ($order['fulfilmentType'] === 'collection'): ?>
                <div class="alert alert-info">We’ll phone you on <?= e($order['contactPhone']) ?> when your order is ready to collect. Pay when you collect.</div>
            <?php endif; ?>

            <?php include 'includes/components/orderSummary.php'; ?>

            <div class="d-flex gap-2 flex-wrap mt-4">
                <a href="catalogue.php" class="btn btn-brand">Continue shopping</a>
                <a href="order.php?order=<?= urlencode($order['orderNumber']) ?>" class="btn btn-outline-brand">Track this order</a>
            </div>
        </main>

        <?php include 'includes/components/footer.php'; ?>
    </body>
</html>
