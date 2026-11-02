<?php
    // cart.php is the shopping cart
    // shows each book with a quantity box, the totals and the button to checkout

    require_once __DIR__ . '/includes/security.php';
    require_once __DIR__ . '/includes/helpers/books.php';
    require_once __DIR__ . '/includes/helpers/cart.php';
    require_once __DIR__ . '/includes/helpers/orders.php';

    $conn = db();
    $cart = $conn ? cartDetails($conn) : ['lines' => [], 'subtotal' => 0, 'count' => 0];
    $deliveryFee = deliveryFeeFor($cart['subtotal'], 'delivery');
    $toFreeDelivery = FREE_DELIVERY_FROM - $cart['subtotal'];

    // a line asks for more copies than all the stores hold together
    $shortLines = array_filter($cart['lines'], fn ($line) => $line['quantity'] > (int) $line['totalStock']);

    $pageTitle = 'Your Cart';
?>
<!DOCTYPE html>
<html lang="en">
    <?php include 'includes/components/header.php'; ?>

    <body>
        <?php include 'includes/components/navBar.php'; ?>

        <main id="main" class="container-xl page-pad">
            <h1 class="page-title mb-4">Your cart</h1>

            <?php if (!$cart['lines']): ?>
                <div class="empty-state">
                    <i class="bi bi-bag" aria-hidden="true"></i>
                    <h2>Your cart is empty</h2>
                    <p>Find something good to read and it will show up here.</p>
                    <a href="catalogue.php" class="btn btn-brand">Browse books</a>
                </div>
            <?php else: ?>
                <div class="cart-layout">
                    <section aria-label="Books in your cart">
                        <?php if ($shortLines): ?>
                            <div class="alert alert-warning">Some books have fewer copies available than you asked for. Lower the quantity before checking out.</div>
                        <?php endif; ?>

                        <ul class="cart-list">
                            <?php foreach ($cart['lines'] as $line): ?>
                                <li class="cart-line">
                                    <a href="book.php?id=<?= (int) $line['bookID'] ?>" class="cart-line-cover">
                                        <img src="<?= e(coverSrc($line, 'S')) ?>" alt="" width="64" height="96" data-cover>
                                        <span class="cover-fallback cover-fallback-sm" hidden><span class="cover-fallback-title"><?= e($line['title']) ?></span></span>
                                    </a>
                                    <div class="cart-line-info">
                                        <h2 class="cart-line-title"><a href="book.php?id=<?= (int) $line['bookID'] ?>"><?= e($line['title']) ?></a></h2>
                                        <p class="cart-line-author"><?= e($line['author']) ?> · <?= e($line['format']) ?></p>
                                        <p class="cart-line-unit"><?= money($line['price']) ?> each</p>
                                        <?php if ($line['quantity'] > (int) $line['totalStock']): ?>
                                            <p class="text-danger small mb-0">Only <?= (int) $line['totalStock'] ?> available</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cart-line-actions">
                                        <form action="cart-action.php" method="post" class="cart-qty-form">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="bookID" value="<?= (int) $line['bookID'] ?>">
                                            <label class="visually-hidden" for="qty<?= (int) $line['bookID'] ?>">Quantity for <?= e($line['title']) ?></label>
                                            <select id="qty<?= (int) $line['bookID'] ?>" name="quantity" class="form-select form-select-sm" data-submit-on-change>
                                                <?php for ($q = 1; $q <= max($line['quantity'], min(CART_MAX_PER_LINE, (int) $line['totalStock'])); $q++): ?>
                                                    <option value="<?= $q ?>" <?= $q === $line['quantity'] ? 'selected' : '' ?>><?= $q ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <noscript><button class="btn btn-sm btn-link">Update</button></noscript>
                                        </form>
                                        <p class="cart-line-total"><?= money($line['lineTotal']) ?></p>
                                        <form action="cart-action.php" method="post">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="bookID" value="<?= (int) $line['bookID'] ?>">
                                            <button type="submit" class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash" aria-hidden="true"></i> Remove</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
                            <a href="catalogue.php" class="btn btn-link px-0"><i class="bi bi-arrow-left" aria-hidden="true"></i> Continue shopping</a>
                            <form action="cart-action.php" method="post" data-confirm="Remove every book from your cart?">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="clear">
                                <button class="btn btn-link text-muted">Empty cart</button>
                            </form>
                        </div>
                    </section>

                    <!-- totals -->
                    <aside class="summary-card" aria-labelledby="summaryHeading">
                        <h2 id="summaryHeading" class="h5">Order summary</h2>
                        <dl class="summary-rows">
                            <div><dt>Items (<?= $cart['count'] ?>)</dt><dd><?= money($cart['subtotal']) ?></dd></div>
                            <div><dt>Delivery</dt><dd><?= $deliveryFee > 0 ? money($deliveryFee) : 'Free' ?></dd></div>
                            <div class="summary-total"><dt>Estimated total</dt><dd><?= money($cart['subtotal'] + $deliveryFee) ?></dd></div>
                        </dl>
                        <?php if ($toFreeDelivery > 0): ?>
                            <p class="small text-muted">Add <?= money($toFreeDelivery) ?> more for free delivery. Click &amp; collect is always free.</p>
                        <?php else: ?>
                            <p class="small text-success"><i class="bi bi-check-circle" aria-hidden="true"></i> Your order qualifies for free delivery.</p>
                        <?php endif; ?>
                        <a href="checkout.php" class="btn btn-accent btn-lg w-100<?= $shortLines ? ' disabled' : '' ?>" <?= $shortLines ? 'aria-disabled="true"' : '' ?>>Checkout</a>
                        <p class="small text-muted mt-3 mb-0"><i class="bi bi-shield-lock" aria-hidden="true"></i> Stock is reserved only when you place your order.</p>
                    </aside>
                </div>
            <?php endif; ?>
        </main>

        <?php include 'includes/components/footer.php'; ?>
    </body>
</html>
